<?php

namespace App\Services\Subcontractor;

use App\Models\Accounting\Account;
use App\Models\Accounting\TdsCertificate;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use App\Models\SubcontractorRaBill;
use App\Services\Accounting\PartyAccountService;
use App\Services\Accounting\ProjectCostCenterResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubcontractorRaTotalsRepairService
{
    protected array $accountCache = [];

    public function __construct(
        protected SubcontractorRaTotalsCalculator $calculator,
        protected PartyAccountService $partyAccountService
    ) {
    }

    public function dryRun(array $filters = []): array
    {
        return $this->buildSummary($filters);
    }

    public function apply(array $filters = []): array
    {
        $summary = $this->buildSummary($filters);
        $applied = [];

        foreach ($summary['subcontractor_ra_ids'] as $billId) {
            try {
                $bill = $this->repairBill((int) $billId);
                if ($bill) {
                    $applied[] = (string) ($bill->ra_number ?: ('#' . $bill->id));
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = 'RA Bill #' . $billId . ': ' . $e->getMessage();
            }
        }

        $summary['applied_subcontractor_ra_numbers'] = $applied;
        $summary['applied_subcontractor_ra_count'] = count($applied);

        return $summary;
    }

    protected function buildSummary(array $filters): array
    {
        $rows = $this->affectedBills($filters);

        return [
            'subcontractor_ra_rows' => $rows->count(),
            'subcontractor_ra_ids' => $rows->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'subcontractor_ra_numbers' => $rows->pluck('ra_number')->filter()->values()->all(),
            'errors' => [],
        ];
    }

    protected function affectedBills(array $filters): Collection
    {
        $query = SubcontractorRaBill::query()
            ->with('lines')
            ->orderBy('id');

        $billIds = collect($filters['bill_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->all();
        $raNumbers = collect($filters['ra_numbers'] ?? [])->filter()->values()->all();
        $statuses = collect($filters['statuses'] ?? [])
            ->filter(fn ($status) => trim((string) $status) !== '')
            ->map(fn ($status) => strtolower(trim((string) $status)))
            ->unique()
            ->values()
            ->all();

        if (! empty($billIds)) {
            $query->whereIn('id', $billIds);
        }

        if (! empty($raNumbers)) {
            $query->whereIn('ra_number', $raNumbers);
        }

        if (! empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        $affected = collect();

        $query->chunkById(100, function ($bills) use ($affected) {
            foreach ($bills as $bill) {
                $computed = $this->calculator->calculateForBill($bill, $bill->lines);

                if ($this->needsRepair($bill, $computed)) {
                    $affected->push([
                        'id' => (int) $bill->id,
                        'ra_number' => (string) ($bill->ra_number ?? ''),
                    ]);
                }
            }
        });

        return $affected;
    }

    protected function repairBill(int $billId): ?SubcontractorRaBill
    {
        return DB::transaction(function () use ($billId) {
            $bill = SubcontractorRaBill::query()
                ->with(['subcontractor', 'project', 'voucher'])
                ->lockForUpdate()
                ->find($billId);

            if (! $bill) {
                return null;
            }

            $lines = $bill->lines()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $bill->setRelation('lines', $lines);

            $oldValues = $bill->getAttributes();
            $computed = $this->calculator->calculateForBill($bill, $lines);

            if (! $this->needsRepair($bill, $computed)) {
                return $bill;
            }

            if ((float) $computed['header']['net_amount'] < -0.0001 || (float) $computed['header']['total_amount'] < -0.0001) {
                throw new RuntimeException('Repaired totals would make the bill negative. Review deductions before applying the repair.');
            }

            foreach ($computed['lines'] as $lineState) {
                $line = $lineState['model'];
                $line->line_no = (int) $lineState['line_no'];
                $line->cumulative_qty = $lineState['cumulative_qty'];
                $line->previous_amount = $lineState['previous_amount'];
                $line->current_amount = $lineState['current_amount'];
                $line->cumulative_amount = $lineState['cumulative_amount'];
                $line->save();
            }

            DB::table('subcontractor_ra_bills')
                ->where('id', $bill->id)
                ->update(array_merge($computed['header'], [
                    'updated_at' => now(),
                ]));

            $bill = $bill->fresh(['subcontractor', 'project', 'voucher']);

            if ($bill->status === 'posted') {
                if (! $bill->voucher_id || ! $bill->voucher) {
                    throw new RuntimeException('Posted bill is missing its linked accounting voucher.');
                }

                $this->syncPostedVoucher($bill);
            }

            $this->syncPayableTdsCertificate($bill->fresh(['subcontractor', 'voucher']));

            ActivityLog::logUpdated(
                $bill->fresh(),
                $oldValues,
                'Repaired subcontractor RA totals for ' . ($bill->ra_number ?: ('#' . $bill->id))
            );

            ActivityLog::logCustom(
                'subcontractor_ra_repaired',
                'Subcontractor RA totals repaired for ' . ($bill->ra_number ?: ('#' . $bill->id)),
                $bill,
                [
                    'voucher_id' => $bill->voucher_id,
                    'status' => $bill->status,
                ]
            );

            return $bill->fresh(['voucher']);
        });
    }

    protected function syncPostedVoucher(SubcontractorRaBill $bill): void
    {
        $voucher = $bill->voucher;
        if (! $voucher) {
            throw new RuntimeException('Posted bill is missing its linked voucher.');
        }

        $subcontractor = $bill->subcontractor;
        if (! $subcontractor) {
            throw new RuntimeException('Posted bill is missing its subcontractor.');
        }

        $project = $bill->project;
        if (! $project) {
            throw new RuntimeException('Posted bill is missing its project.');
        }

        $companyId = (int) ($bill->company_id ?: Config::get('accounting.default_company_id', 1));
        $costCenterId = ProjectCostCenterResolver::resolveId($companyId, (int) $project->id);
        $subcontractorAccount = $this->partyAccountService->syncAccountForParty($subcontractor, $companyId);

        if (! $subcontractorAccount instanceof Account) {
            throw new RuntimeException('Unable to resolve subcontractor ledger account for repair.');
        }

        $currentAmount = round((float) ($bill->current_amount ?? 0), 2);
        $cgstAmount = round((float) ($bill->cgst_amount ?? 0), 2);
        $sgstAmount = round((float) ($bill->sgst_amount ?? 0), 2);
        $igstAmount = round((float) ($bill->igst_amount ?? 0), 2);
        $retentionAmount = round((float) ($bill->retention_amount ?? 0), 2);
        $securityDepositAmount = round((float) ($bill->security_deposit_amount ?? 0), 2);
        $otherDeductions = round((float) ($bill->other_deductions ?? 0), 2);
        $tdsAmount = round((float) ($bill->tds_amount ?? 0), 2);
        $roundOff = round((float) ($bill->round_off ?? 0), 2);
        $totalAmount = round((float) ($bill->total_amount ?? 0), 2);
        $advanceRecovery = round((float) ($bill->advance_recovery ?? 0), 2);

        $lines = [];

        if ($currentAmount > 0) {
            $lines[] = [
                'account_id' => $this->resolveConfigAccount('accounting.subcontractor.project_wip_account_code', 'WIP-SUBCON')->id,
                'description' => 'Subcontractor Work (Gross) - ' . $bill->ra_number,
                'debit' => $currentAmount,
                'credit' => 0,
            ];
        }

        if ($cgstAmount > 0) {
            $account = $this->resolveGstAccount('cgst_input');
            if (! $account) {
                throw new RuntimeException('Input CGST account not found for repair.');
            }

            $lines[] = [
                'account_id' => $account->id,
                'description' => 'Input CGST - ' . $bill->ra_number,
                'debit' => $cgstAmount,
                'credit' => 0,
            ];
        }

        if ($sgstAmount > 0) {
            $account = $this->resolveGstAccount('sgst_input');
            if (! $account) {
                throw new RuntimeException('Input SGST account not found for repair.');
            }

            $lines[] = [
                'account_id' => $account->id,
                'description' => 'Input SGST - ' . $bill->ra_number,
                'debit' => $sgstAmount,
                'credit' => 0,
            ];
        }

        if ($igstAmount > 0) {
            $account = $this->resolveGstAccount('igst_input');
            if (! $account) {
                throw new RuntimeException('Input IGST account not found for repair.');
            }

            $lines[] = [
                'account_id' => $account->id,
                'description' => 'Input IGST - ' . $bill->ra_number,
                'debit' => $igstAmount,
                'credit' => 0,
            ];
        }

        if ($retentionAmount > 0) {
            $retPct = (float) ($bill->retention_percent ?? 0);
            $retLabel = $retPct > 0
                ? ('Retention @ ' . rtrim(rtrim(number_format($retPct, 2, '.', ''), '0'), '.') . '%')
                : 'Retention';

            $lines[] = [
                'account_id' => $this->resolveConfigAccount('accounting.subcontractor.retention_payable_account_code', 'RETENTION-PAYABLE')->id,
                'description' => $retLabel . ' - ' . $bill->ra_number,
                'debit' => 0,
                'credit' => $retentionAmount,
            ];
        }

        if ($securityDepositAmount > 0) {
            $secPct = (float) ($bill->security_deposit_percent ?? 0);
            $secLabel = $secPct > 0
                ? ('Security Deposit @ ' . rtrim(rtrim(number_format($secPct, 2, '.', ''), '0'), '.') . '%')
                : 'Security Deposit';

            $lines[] = [
                'account_id' => $this->resolveConfigAccount(
                    'accounting.subcontractor.security_deposit_payable_account_code',
                    Config::get('accounting.subcontractor.retention_payable_account_code', 'RETENTION-PAYABLE')
                )->id,
                'description' => $secLabel . ' - ' . $bill->ra_number,
                'debit' => 0,
                'credit' => $securityDepositAmount,
            ];
        }

        if ($otherDeductions > 0) {
            $desc = 'Other Deductions - ' . $bill->ra_number;
            $remark = trim((string) ($bill->deduction_remarks ?? ''));
            if ($remark !== '') {
                $desc .= ' | ' . mb_substr($remark, 0, 180);
            }

            $lines[] = [
                'account_id' => $this->resolveConfigAccount('accounting.subcontractor.other_deductions_account_code', 'SUBCON-DEDUCTIONS')->id,
                'description' => $desc,
                'debit' => 0,
                'credit' => $otherDeductions,
            ];
        }

        if ($tdsAmount > 0) {
            $lines[] = [
                'account_id' => $this->resolveConfigAccount('accounting.tds.tds_payable_account_code', 'TDS-PAYABLE')->id,
                'description' => 'TDS Payable ' . ($bill->tds_section ?? '') . ' - ' . $bill->ra_number,
                'debit' => 0,
                'credit' => $tdsAmount,
            ];
        }

        if (abs($roundOff) > 0.0001) {
            $lines[] = [
                'account_id' => $this->resolveConfigAccount('accounting.round_off.round_off_account_code', 'ROUND-OFF')->id,
                'description' => 'Round Off - ' . $bill->ra_number,
                'debit' => $roundOff > 0 ? $roundOff : 0,
                'credit' => $roundOff < 0 ? abs($roundOff) : 0,
            ];
        }

        $lines[] = [
            'account_id' => $subcontractorAccount->id,
            'description' => 'Subcontractor Payable (Net) - ' . $bill->ra_number,
            'debit' => 0,
            'credit' => $totalAmount,
        ];

        if ($advanceRecovery > 0) {
            $lines[] = [
                'account_id' => $subcontractorAccount->id,
                'description' => 'Advance Recovery - ' . $bill->ra_number,
                'debit' => 0,
                'credit' => $advanceRecovery,
            ];
        }

        $debitTotal = round(collect($lines)->sum('debit'), 2);
        $creditTotal = round(collect($lines)->sum('credit'), 2);
        if (abs($debitTotal - $creditTotal) >= 0.01) {
            throw new RuntimeException('Repaired voucher lines are not balanced.');
        }

        $voucher->project_id = $project->id;
        $voucher->cost_center_id = $costCenterId;
        $voucher->subcontractor_work_order_id = $bill->work_order_id;
        $voucher->reference = $bill->ra_number;
        $voucher->voucher_date = $bill->posting_date ?: $bill->bill_date;
        $voucher->amount_base = round($currentAmount + $cgstAmount + $sgstAmount + $igstAmount + max(0, $roundOff), 2);
        $voucher->narration = $this->buildNarration($bill);
        $voucher->save();

        $voucher->lines()->delete();

        $lineNo = 1;
        foreach ($lines as $line) {
            VoucherLine::create([
                'voucher_id' => $voucher->id,
                'line_no' => $lineNo++,
                'account_id' => $line['account_id'],
                'cost_center_id' => $costCenterId,
                'description' => $line['description'],
                'debit' => round((float) $line['debit'], 2),
                'credit' => round((float) $line['credit'], 2),
                'reference_type' => SubcontractorRaBill::class,
                'reference_id' => $bill->id,
            ]);
        }
    }

    protected function syncPayableTdsCertificate(SubcontractorRaBill $bill): void
    {
        if (! $bill->voucher_id) {
            return;
        }

        $certificate = TdsCertificate::query()
            ->where('company_id', (int) $bill->company_id)
            ->where('direction', 'payable')
            ->where('voucher_id', (int) $bill->voucher_id)
            ->first();

        $tdsAmount = round((float) ($bill->tds_amount ?? 0), 2);
        if ($tdsAmount <= 0.0001) {
            if ($certificate) {
                $certificate->delete();
            }
            return;
        }

        $subcontractor = $bill->subcontractor;
        if (! $subcontractor) {
            throw new RuntimeException('Cannot sync TDS certificate without subcontractor.');
        }

        $partyAccount = $this->partyAccountService->syncAccountForParty($subcontractor, (int) $bill->company_id);
        if (! $partyAccount instanceof Account) {
            throw new RuntimeException('Unable to resolve subcontractor ledger for TDS certificate repair.');
        }

        $certificate ??= new TdsCertificate([
            'company_id' => (int) $bill->company_id,
            'direction' => 'payable',
            'voucher_id' => (int) $bill->voucher_id,
            'created_by' => auth()->id(),
        ]);

        $certificate->party_account_id = $partyAccount->id;
        $certificate->tds_section = $bill->tds_section ?: null;
        $certificate->tds_rate = (float) ($bill->tds_rate ?? 0) > 0 ? (float) $bill->tds_rate : null;
        $certificate->tds_amount = $tdsAmount;
        $certificate->updated_by = auth()->id() ?: $certificate->updated_by;
        $certificate->save();
    }

    protected function needsRepair(SubcontractorRaBill $bill, array $computed): bool
    {
        foreach ($computed['lines'] as $lineState) {
            $line = $lineState['model'];

            if ((int) $line->line_no !== (int) $lineState['line_no']) {
                return true;
            }

            if (! $this->sameQuantity((float) ($line->cumulative_qty ?? 0), (float) $lineState['cumulative_qty'])) {
                return true;
            }

            foreach (['previous_amount', 'current_amount', 'cumulative_amount'] as $field) {
                if (! $this->sameAmount((float) ($line->{$field} ?? 0), (float) $lineState[$field])) {
                    return true;
                }
            }
        }

        foreach (array_keys($computed['header']) as $field) {
            if (! $this->sameAmount((float) ($bill->{$field} ?? 0), (float) $computed['header'][$field])) {
                return true;
            }
        }

        return false;
    }

    protected function sameAmount(float $left, float $right): bool
    {
        return abs($left - $right) < 0.01;
    }

    protected function sameQuantity(float $left, float $right): bool
    {
        return abs($left - $right) < 0.0001;
    }

    protected function resolveConfigAccount(string $configKey, string $fallbackCode): Account
    {
        $code = trim((string) (Config::get($configKey) ?: $fallbackCode));

        if (isset($this->accountCache[$code])) {
            return $this->accountCache[$code];
        }

        $account = Account::query()
            ->where('code', $code)
            ->first();

        if (! $account) {
            throw new RuntimeException('Required account not found for code: ' . $code);
        }

        return $this->accountCache[$code] = $account;
    }

    protected function resolveGstAccount(string $type): ?Account
    {
        $codeCandidates = [];

        switch ($type) {
            case 'cgst_input':
                $codeCandidates = [
                    Config::get('accounting.gst.input_cgst_account_code'),
                    Config::get('accounting.gst.cgst_input_account_code'),
                    'GST-IN-CGST',
                    'GST-CGST-INPUT',
                ];
                break;

            case 'sgst_input':
                $codeCandidates = [
                    Config::get('accounting.gst.input_sgst_account_code'),
                    Config::get('accounting.gst.sgst_input_account_code'),
                    'GST-IN-SGST',
                    'GST-SGST-INPUT',
                ];
                break;

            case 'igst_input':
                $codeCandidates = [
                    Config::get('accounting.gst.input_igst_account_code'),
                    Config::get('accounting.gst.igst_input_account_code'),
                    'GST-IN-IGST',
                    'GST-IGST-INPUT',
                ];
                break;
        }

        foreach ($codeCandidates as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }

            if (isset($this->accountCache[$code])) {
                return $this->accountCache[$code];
            }

            $account = Account::query()->where('code', $code)->first();
            if ($account) {
                return $this->accountCache[$code] = $account;
            }
        }

        return null;
    }

    protected function buildNarration(SubcontractorRaBill $bill): string
    {
        $parts = array_filter([
            'Subcontractor RA Bill ' . $bill->ra_number,
            ($bill->subcontractor->name ?? ''),
            ($bill->work_order_number ? ('WO# ' . $bill->work_order_number) : ''),
            ($bill->bill_number ? ('Bill# ' . $bill->bill_number) : ''),
            trim((string) ($bill->remarks ?? '')),
        ]);

        return trim(implode(' - ', $parts));
    }
}
