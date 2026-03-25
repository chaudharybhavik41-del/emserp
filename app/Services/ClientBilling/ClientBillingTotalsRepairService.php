<?php

namespace App\Services\ClientBilling;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountBillAllocation;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use App\Models\ClientRaBill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClientBillingTotalsRepairService
{
    protected array $accountCache = [];

    public function dryRun(array $filters = []): array
    {
        return $this->buildSummary($filters);
    }

    public function apply(array $filters = []): array
    {
        $summary = $this->buildSummary($filters);
        $applied = [];

        foreach ($summary['client_bill_ids'] as $billId) {
            try {
                $bill = $this->repairBill((int) $billId);
                if ($bill) {
                    $applied[] = (string) ($bill->ra_number ?: ('#' . $bill->id));
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = 'Client bill #' . $billId . ': ' . $e->getMessage();
            }
        }

        $summary['applied_client_bill_numbers'] = $applied;
        $summary['applied_client_bill_count'] = count($applied);

        return $summary;
    }

    protected function buildSummary(array $filters): array
    {
        $rows = $this->affectedBills($filters);

        return [
            'client_bill_rows' => $rows->count(),
            'client_bill_ids' => $rows->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'client_bill_numbers' => $rows->pluck('ra_number')->filter()->values()->all(),
            'errors' => [],
        ];
    }

    protected function affectedBills(array $filters): Collection
    {
        $query = ClientRaBill::query()
            ->select([
                'id',
                'ra_number',
                'status',
                'net_amount',
                'total_gst',
                'tds_rate',
                'tds_amount',
                'round_off',
                'total_amount',
                'receivable_amount',
            ])
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

        return $query->get()->filter(function (ClientRaBill $bill) {
            return $this->needsRepair($bill, $this->computedValues($bill));
        })->values();
    }

    protected function repairBill(int $billId): ?ClientRaBill
    {
        return DB::transaction(function () use ($billId) {
            $bill = ClientRaBill::query()
                ->with('voucher')
                ->lockForUpdate()
                ->find($billId);

            if (! $bill) {
                return null;
            }

            $computed = $this->computedValues($bill);

            if (! $this->needsRepair($bill, $computed)) {
                return $bill;
            }

            if ($bill->status === 'posted') {
                $allocated = $this->postedAllocatedAmount($bill);
                if ($allocated - $computed['receivable_amount'] > 0.0001) {
                    throw new RuntimeException(
                        'Skipping because posted allocations ' . number_format($allocated, 2, '.', '')
                        . ' exceed repaired receivable ' . number_format($computed['receivable_amount'], 2, '.', '')
                    );
                }
            }

            $oldValues = $bill->getAttributes();

            DB::table('client_ra_bills')
                ->where('id', $bill->id)
                ->update([
                    'tds_amount' => $computed['tds_amount'],
                    'round_off' => $computed['round_off'],
                    'total_amount' => $computed['total_amount'],
                    'receivable_amount' => $computed['receivable_amount'],
                    'updated_at' => now(),
                ]);

            $bill = $bill->fresh('voucher');

            if ($bill->status === 'posted' && $bill->voucher_id && $bill->voucher) {
                $this->syncPostedVoucher($bill, $computed);
            }

            ActivityLog::logUpdated(
                $bill,
                $oldValues,
                'Repaired client billing totals for ' . ($bill->ra_number ?: ('#' . $bill->id))
            );

            ActivityLog::logCustom(
                'client_billing_repaired',
                'Client billing totals repaired for ' . ($bill->ra_number ?: ('#' . $bill->id)),
                $bill,
                [
                    'voucher_id' => $bill->voucher_id,
                    'status' => $bill->status,
                    'tds_amount' => $computed['tds_amount'],
                    'round_off' => $computed['round_off'],
                    'total_amount' => $computed['total_amount'],
                    'receivable_amount' => $computed['receivable_amount'],
                ]
            );

            return $bill->fresh('voucher');
        });
    }

    protected function syncPostedVoucher(ClientRaBill $bill, array $computed): void
    {
        $voucher = $bill->voucher;
        if (! $voucher) {
            throw new RuntimeException('Posted client bill is missing its linked voucher.');
        }

        $reference = $bill->invoice_number ?: $bill->ra_number;

        $debtorLine = VoucherLine::query()
            ->where('voucher_id', $voucher->id)
            ->where('description', 'Debtor - ' . $reference)
            ->first();

        if (! $debtorLine) {
            throw new RuntimeException('Debtor voucher line not found.');
        }

        $debtorLine->debit = round((float) $computed['receivable_amount'], 2);
        $debtorLine->credit = 0;
        $debtorLine->save();

        $tdsDescription = 'TDS Receivable ' . (($bill->tds_section ?? '') ? ($bill->tds_section . ' - ') : '') . $reference;
        $tdsLine = VoucherLine::query()
            ->where('voucher_id', $voucher->id)
            ->where('description', $tdsDescription)
            ->first();

        if ((float) $computed['tds_amount'] > 0.0001) {
            $tdsAccount = $this->resolveConfigAccount('accounting.tds.tds_receivable_account_code', 'TDS Receivable');

            if ($tdsLine) {
                $tdsLine->account_id = $tdsAccount->id;
                $tdsLine->debit = round((float) $computed['tds_amount'], 2);
                $tdsLine->credit = 0;
                $tdsLine->save();
            } else {
                $lineNo = (int) VoucherLine::query()
                    ->where('voucher_id', $voucher->id)
                    ->max('line_no') + 1;

                VoucherLine::create([
                    'voucher_id' => $voucher->id,
                    'line_no' => $lineNo,
                    'account_id' => $tdsAccount->id,
                    'cost_center_id' => $voucher->cost_center_id,
                    'description' => $tdsDescription,
                    'debit' => round((float) $computed['tds_amount'], 2),
                    'credit' => 0,
                    'reference_type' => ClientRaBill::class,
                    'reference_id' => $bill->id,
                ]);
            }
        } elseif ($tdsLine) {
            $tdsLine->delete();
        }

        $roundDescription = 'Round Off - ' . $reference;
        $roundOffLine = VoucherLine::query()
            ->where('voucher_id', $voucher->id)
            ->where('description', $roundDescription)
            ->first();

        $roundOff = round((float) $computed['round_off'], 2);

        if (abs($roundOff) > 0.0001) {
            $roundOffAccount = $this->resolveConfigAccount('accounting.round_off.round_off_account_code', 'Round Off');

            if ($roundOffLine) {
                $roundOffLine->account_id = $roundOffAccount->id;
                $roundOffLine->debit = $roundOff < 0 ? round(abs($roundOff), 2) : 0;
                $roundOffLine->credit = $roundOff > 0 ? round($roundOff, 2) : 0;
                $roundOffLine->save();
            } else {
                $lineNo = (int) VoucherLine::query()
                    ->where('voucher_id', $voucher->id)
                    ->max('line_no') + 1;

                VoucherLine::create([
                    'voucher_id' => $voucher->id,
                    'line_no' => $lineNo,
                    'account_id' => $roundOffAccount->id,
                    'cost_center_id' => $voucher->cost_center_id,
                    'description' => $roundDescription,
                    'debit' => $roundOff < 0 ? round(abs($roundOff), 2) : 0,
                    'credit' => $roundOff > 0 ? round($roundOff, 2) : 0,
                ]);
            }
        } elseif ($roundOffLine) {
            $roundOffLine->delete();
        }

        $voucher->amount_base = round((float) VoucherLine::query()->where('voucher_id', $voucher->id)->sum('debit'), 2);
        $voucher->save();
    }

    protected function postedAllocatedAmount(ClientRaBill $bill): float
    {
        return round((float) AccountBillAllocation::query()
            ->join('vouchers', 'vouchers.id', '=', 'account_bill_allocations.voucher_id')
            ->where('account_bill_allocations.company_id', (int) $bill->company_id)
            ->where('account_bill_allocations.bill_type', ClientRaBill::class)
            ->where('account_bill_allocations.bill_id', (int) $bill->id)
            ->where('account_bill_allocations.mode', 'against')
            ->where('vouchers.status', 'posted')
            ->sum('account_bill_allocations.amount'), 2);
    }

    protected function computedValues(ClientRaBill $bill): array
    {
        $netAmount = round((float) ($bill->net_amount ?? 0), 2);
        $totalGst = round((float) ($bill->total_gst ?? 0), 2);
        $tdsRate = (float) ($bill->tds_rate ?? 0);

        $tdsAmount = ($tdsRate > 0 && $netAmount > 0)
            ? (float) round(max(0, ($netAmount * $tdsRate) / 100), 0)
            : 0.0;

        $calculatedTotal = round($netAmount + $totalGst, 2);
        $invoiceTotal = round($calculatedTotal, 0);
        $roundOff = round($invoiceTotal - $calculatedTotal, 2);
        $receivableAmount = round($invoiceTotal - $tdsAmount, 2);

        return [
            'tds_amount' => $tdsAmount,
            'round_off' => $roundOff,
            'total_amount' => $invoiceTotal,
            'receivable_amount' => $receivableAmount,
        ];
    }

    protected function needsRepair(ClientRaBill $bill, array $computed): bool
    {
        return abs((float) ($bill->tds_amount ?? 0) - (float) $computed['tds_amount']) >= 0.01
            || abs((float) ($bill->round_off ?? 0) - (float) $computed['round_off']) >= 0.01
            || abs((float) ($bill->total_amount ?? 0) - (float) $computed['total_amount']) >= 0.01
            || abs((float) ($bill->receivable_amount ?? 0) - (float) $computed['receivable_amount']) >= 0.01;
    }

    protected function resolveConfigAccount(string $configKey, string $label): Account
    {
        if (isset($this->accountCache[$configKey])) {
            return $this->accountCache[$configKey];
        }

        $code = Config::get($configKey);
        if (! $code) {
            throw new RuntimeException($label . ' account code is not configured.');
        }

        $account = Account::query()->where('code', $code)->first();
        if (! $account) {
            throw new RuntimeException($label . ' account not found for code: ' . $code);
        }

        return $this->accountCache[$configKey] = $account;
    }
}
