<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountBillAllocation;
use App\Models\Accounting\VoucherLine;
use App\Models\ClientRaBill;
use App\Models\Company;
use App\Models\Party;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\Project;
use App\Models\SubcontractorRaBill;
use App\Models\SubcontractorWorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LedgerReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.reports.view')->only(['index']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    /**
     * Ledger Statement (per account, period)
     */
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $toDate   = $request->date('to_date') ?: now();
        $fromDate = $request->date('from_date') ?: $toDate->copy()->startOfMonth();

        $projectId = $request->integer('project_id') ?: null;
        $export    = $request->get('export');
        $showBreakdown = $request->boolean('show_breakdown');

        // Show all accounts (inactive accounts may still have balances/movements)
        $accounts = Account::with('group')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        $projects = Project::orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $accountId = $request->integer('account_id') ?: ($accounts->first()?->id ?? null);
        $account   = $accountId ? $accounts->firstWhere('id', $accountId) : null;
        $supportsAnalyticalView = $this->supportsAnalyticalPartyLedger($account);
        $analyticalViewLabel = $this->analyticalLedgerLabel($account);
        $viewMode = $supportsAnalyticalView
            ? ((string) $request->get('view_mode', 'analytical') === 'standard' ? 'standard' : 'analytical')
            : 'standard';

        if (! $account) {
            return view('accounting.reports.ledger', [
                'companyId'      => $companyId,
                'fromDate'       => $fromDate,
                'toDate'         => $toDate,
                'projects'       => $projects,
                'projectId'      => $projectId,
                'accounts'       => $accounts,
                'account'        => null,
                'ledgerEntries'  => [],
                'showBreakdown' => false,
                'voucherLinesByVoucher' => collect(),
                'supportsAnalyticalView' => false,
                'analyticalViewLabel' => null,
                'viewMode' => 'standard',
                'analyticalRows' => collect(),
                'openingBalance' => 0.0,
                'closingBalance' => 0.0,
            ]);
        }

        // Fetch ledger entries (posted vouchers only)
        $ledgerEntriesQuery = VoucherLine::with(['voucher', 'costCenter'])
            ->where('account_id', $account->id)
            ->whereHas('voucher', function ($q) use ($companyId, $fromDate, $toDate, $projectId) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereDate('voucher_date', '>=', $fromDate->toDateString())
                    ->whereDate('voucher_date', '<=', $toDate->toDateString());

                if ($projectId) {
                    $q->where('project_id', $projectId);
                }
            })
            ->orderBy(
                DB::table('vouchers')
                    ->select('voucher_date')
                    ->whereColumn('vouchers.id', 'voucher_lines.voucher_id')
                    ->limit(1)
            )
            ->orderBy('voucher_id')
            ->orderBy('line_no');

        $ledgerEntries = $ledgerEntriesQuery->get();

        // Optional: load full voucher break-up (all voucher lines) for each entry.
        // Useful for party statements (shows TDS/GST/Retention lines along with net payable).
        $voucherLinesByVoucher = collect();
        if ($showBreakdown && $ledgerEntries->count()) {
            $voucherIds = $ledgerEntries->pluck('voucher_id')->unique()->values()->all();

            if (! empty($voucherIds)) {
                $voucherLinesByVoucher = VoucherLine::with(['account', 'costCenter', 'voucher'])
                    ->whereIn('voucher_id', $voucherIds)
                    ->orderBy('voucher_id')
                    ->orderBy('line_no')
                    ->get()
                    ->groupBy('voucher_id');
            }
        }

        // Opening balance:
        // - Company-level: opening master + movements before fromDate.
        // - Project-level: ONLY movements before fromDate for that project (opening master is company-level).
        $openingBalance = 0.0;

        if (! $projectId) {
            $openingBalance = (float) ($account->opening_balance ?? 0.0);

            // Apply only if effective on/before fromDate
            if ($account->opening_balance_date && $account->opening_balance_date->gt($fromDate)) {
                $openingBalance = 0.0;
            }

            if ($openingBalance != 0.0) {
                $openingBalance *= ($account->opening_balance_type === 'cr') ? -1 : 1;
            }
        }

        $movementBeforeQuery = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->where('vl.account_id', $account->id)
            ->whereDate('v.voucher_date', '<', $fromDate->toDateString());

        if ($projectId) {
            $movementBeforeQuery->where('v.project_id', $projectId);
        }

        // Respect opening_balance_date cut-off for company-level opening logic
        if (! $projectId && $account->opening_balance_date) {
            $movementBeforeQuery->whereDate('v.voucher_date', '>=', $account->opening_balance_date->toDateString());
        }

        $movementBefore = (float) $movementBeforeQuery
            ->selectRaw('COALESCE(SUM(vl.debit),0) - COALESCE(SUM(vl.credit),0) as net')
            ->value('net');

        $openingBalance += $movementBefore;

        // Running/closing
        $running = $openingBalance;
        foreach ($ledgerEntries as $entry) {
            $running += ((float) $entry->debit - (float) $entry->credit);
        }

        $closingBalance = $running;

        $analyticalRows = collect();
        if ($viewMode === 'analytical') {
            $analyticalRows = $this->buildAnalyticalPartyRows($account, $ledgerEntries, $openingBalance);
        }

        if ($export === 'csv') {
            return $this->exportCsv(
                companyId: $companyId,
                fromDate: $fromDate,
                toDate: $toDate,
                account: $account,
                ledgerEntries: $ledgerEntries,
                openingBalance: $openingBalance,
                closingBalance: $closingBalance,
                projectId: $projectId,
                projects: $projects,
                includeBreakdown: $showBreakdown,
                viewMode: $viewMode,
                analyticalRows: $analyticalRows,
            );
        }

        if ($export === 'pdf') {
            return $this->exportPdf(
                companyId: $companyId,
                fromDate: $fromDate,
                toDate: $toDate,
                account: $account,
                ledgerEntries: $ledgerEntries,
                openingBalance: $openingBalance,
                closingBalance: $closingBalance,
                includeBreakdown: $showBreakdown,
                viewMode: $viewMode,
                analyticalRows: $analyticalRows,
            );
        }

        return view('accounting.reports.ledger', [
            'companyId'      => $companyId,
            'fromDate'       => $fromDate,
            'toDate'         => $toDate,
            'projects'       => $projects,
            'projectId'      => $projectId,
            'accounts'       => $accounts,
            'account'        => $account,
            'ledgerEntries'  => $ledgerEntries,
            'showBreakdown' => $showBreakdown,
            'voucherLinesByVoucher' => $voucherLinesByVoucher,
            'supportsAnalyticalView' => $supportsAnalyticalView,
            'analyticalViewLabel' => $analyticalViewLabel,
            'viewMode' => $viewMode,
            'analyticalRows' => $analyticalRows,
            'openingBalance' => $openingBalance,
            'closingBalance' => $closingBalance,
        ]);
    }

    protected function resolveAnalyticalParty(?Account $account): ?Party
    {
        if (! $account || $account->related_model_type !== Party::class || empty($account->related_model_id)) {
            return null;
        }

        return Party::query()->find((int) $account->related_model_id);
    }

    protected function supportsAnalyticalPartyLedger(?Account $account): bool
    {
        $party = $this->resolveAnalyticalParty($account);

        return (bool) ($party && ($party->is_supplier || $party->is_client || $party->is_contractor));
    }

    protected function analyticalLedgerLabel(?Account $account): ?string
    {
        $party = $this->resolveAnalyticalParty($account);
        if (! $party) {
            return null;
        }

        $roles = array_filter([
            $party->is_supplier ? 'supplier' : null,
            $party->is_client ? 'client' : null,
            $party->is_contractor ? 'subcontractor' : null,
        ]);

        if (count($roles) === 1) {
            return 'Analytical ' . reset($roles) . ' ledger';
        }

        if (count($roles) > 1) {
            return 'Analytical party ledger';
        }

        return null;
    }

    protected function resolveArBillModelClass(): ?string
    {
        $class = Config::get('accounting.ar_bill_model');

        if (is_string($class) && $class !== '' && class_exists($class)) {
            return $class;
        }

        return class_exists(ClientRaBill::class) ? ClientRaBill::class : null;
    }

    protected function analyticalDeltaRow(
        ?string $date,
        string $entryType,
        string $documentNo,
        string $voucherNo,
        string $particulars,
        float $billAmount,
        float $tdsAmount,
        float $paymentAmount,
        float $delta,
        float $balance,
    ): array {
        return [
            'date' => $date,
            'entry_type' => $entryType,
            'document_no' => $documentNo,
            'voucher_no' => $voucherNo,
            'particulars' => $particulars,
            'bill_amount' => $billAmount,
            'tds_amount' => $tdsAmount,
            'payment_amount' => $paymentAmount,
            'debit_amount' => $delta > 0 ? round($delta, 2) : 0.0,
            'credit_amount' => $delta < 0 ? round(abs($delta), 2) : 0.0,
            'balance' => $balance,
            'balance_type' => $balance >= 0 ? 'Dr' : 'Cr',
        ];
    }

    protected function analyticalSearchText(array $row): string
    {
        return strtolower(trim(implode(' ', [
            $row['date'] ?? '',
            $row['entry_type'] ?? '',
            $row['document_no'] ?? '',
            $row['voucher_no'] ?? '',
            $row['particulars'] ?? '',
        ])));
    }

    protected function buildAnalyticalPartyRows(Account $account, Collection $ledgerEntries, float $openingBalance): Collection
    {
        if ($ledgerEntries->isEmpty()) {
            return collect();
        }

        $party = $this->resolveAnalyticalParty($account);
        if (! $party || (! $party->is_supplier && ! $party->is_client && ! $party->is_contractor)) {
            return collect();
        }

        $companyId = (int) $account->company_id;
        $voucherIds = $ledgerEntries->pluck('voucher_id')->filter()->unique()->values()->all();
        $voucherLineIds = $ledgerEntries->pluck('id')->filter()->unique()->values()->all();

        $purchaseBillsByVoucherId = collect();
        if ($party->is_supplier && ! empty($voucherIds)) {
            $purchaseBillsByVoucherId = PurchaseBill::query()
                ->where('company_id', $companyId)
                ->where('supplier_id', (int) $party->id)
                ->whereIn('voucher_id', $voucherIds)
                ->get()
                ->keyBy('voucher_id');
        }

        $clientBillsByVoucherId = collect();
        $clientBillModelClass = $party->is_client ? $this->resolveArBillModelClass() : null;
        if ($clientBillModelClass && ! empty($voucherIds)) {
            $clientBillsByVoucherId = $clientBillModelClass::query()
                ->where('company_id', $companyId)
                ->where('client_id', (int) $party->id)
                ->whereIn('voucher_id', $voucherIds)
                ->get()
                ->keyBy('voucher_id');
        }

        $subcontractorBillsByVoucherId = collect();
        if ($party->is_contractor && ! empty($voucherIds)) {
            $subcontractorBillsByVoucherId = SubcontractorRaBill::query()
                ->where('company_id', $companyId)
                ->where('subcontractor_id', (int) $party->id)
                ->whereIn('voucher_id', $voucherIds)
                ->get()
                ->keyBy('voucher_id');
        }

        $allocationsByVoucherLineId = AccountBillAllocation::query()
            ->where('company_id', $companyId)
            ->where('account_id', (int) $account->id)
            ->whereIn('voucher_line_id', $voucherLineIds)
            ->whereHas('voucher', function ($query) {
                $query->where('status', 'posted');
            })
            ->orderBy('voucher_line_id')
            ->orderBy('id')
            ->get()
            ->groupBy('voucher_line_id');

        $flatAllocations = $allocationsByVoucherLineId->flatten(1);

        $purchaseBillRefs = PurchaseBill::query()
            ->whereIn('id', $flatAllocations->where('bill_type', PurchaseBill::class)->pluck('bill_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $clientBillRefs = collect();
        if ($clientBillModelClass) {
            $clientBillRefs = $clientBillModelClass::query()
                ->whereIn('id', $flatAllocations->where('bill_type', $clientBillModelClass)->pluck('bill_id')->unique()->values())
                ->get()
                ->keyBy('id');
        }

        $subcontractorBillRefs = SubcontractorRaBill::query()
            ->whereIn('id', $flatAllocations->where('bill_type', SubcontractorRaBill::class)->pluck('bill_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $purchaseOrderRefs = PurchaseOrder::query()
            ->whereIn('id', $flatAllocations->where('bill_type', PurchaseOrder::class)->pluck('bill_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $workOrderRefs = SubcontractorWorkOrder::query()
            ->whereIn('id', $flatAllocations->where('bill_type', SubcontractorWorkOrder::class)->pluck('bill_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $rows = collect();
        $running = round($openingBalance, 2);
        $processedSourceVouchers = [];
        $entryDeltaByVoucherId = $ledgerEntries
            ->groupBy('voucher_id')
            ->map(fn (Collection $voucherRows) => round($voucherRows->sum(fn ($voucherRow) => (float) $voucherRow->debit - (float) $voucherRow->credit), 2));

        foreach ($ledgerEntries as $entry) {
            $entryDate = optional($entry->voucher?->voucher_date)->format('d-m-Y');
            $entryDelta = round((float) $entry->debit - (float) $entry->credit, 2);
            $effectiveEntryDelta = $entryDelta;
            $representedDelta = 0.0;
            $entryRows = [];
            $voucherNo = (string) ($entry->voucher?->voucher_no ?? '');
            $sourceVoucherKey = null;

            /** @var PurchaseBill|null $purchaseBill */
            $purchaseBill = $purchaseBillsByVoucherId->get((int) $entry->voucher_id);
            if ($purchaseBill) {
                $sourceVoucherKey = 'purchase:' . (int) $entry->voucher_id;
                if (isset($processedSourceVouchers[$sourceVoucherKey])) {
                    continue;
                }
                $effectiveEntryDelta = (float) ($entryDeltaByVoucherId->get($entry->voucher_id) ?? $entryDelta);

                $grossBillAmount = round(
                    (float) ($purchaseBill->total_amount ?? 0) + (float) ($purchaseBill->tcs_amount ?? 0),
                    2
                );
                $tdsAmount = round((float) ($purchaseBill->tds_amount ?? 0), 2);

                if ($grossBillAmount > 0) {
                    $delta = round(-1 * $grossBillAmount, 2);
                    $representedDelta += $delta;
                    $running = round($running + $delta, 2);
                    $entryRows[] = $this->analyticalDeltaRow(
                        $entryDate,
                        'Bill',
                        (string) ($purchaseBill->reference_no ?: $purchaseBill->bill_number ?: ''),
                        $voucherNo,
                        'Purchase Bill',
                        $grossBillAmount,
                        0.0,
                        0.0,
                        $delta,
                        $running,
                    );
                }

                if ($tdsAmount > 0) {
                    $delta = $tdsAmount;
                    $representedDelta += $delta;
                    $running = round($running + $delta, 2);
                    $entryRows[] = $this->analyticalDeltaRow(
                        $entryDate,
                        'TDS',
                        (string) ($purchaseBill->reference_no ?: $purchaseBill->bill_number ?: ''),
                        $voucherNo,
                        trim('TDS ' . (string) ($purchaseBill->tds_section ?? '')),
                        0.0,
                        $tdsAmount,
                        0.0,
                        $delta,
                        $running,
                    );
                }
            } elseif (($clientBill = $clientBillsByVoucherId->get((int) $entry->voucher_id)) instanceof Model) {
                $sourceVoucherKey = 'client:' . (int) $entry->voucher_id;
                if (isset($processedSourceVouchers[$sourceVoucherKey])) {
                    continue;
                }
                $effectiveEntryDelta = (float) ($entryDeltaByVoucherId->get($entry->voucher_id) ?? $entryDelta);

                $invoiceAmount = round((float) ($clientBill->total_amount ?? 0), 2);
                if ($invoiceAmount <= 0) {
                    $invoiceAmount = round((float) ($clientBill->receivable_amount ?? 0) + (float) ($clientBill->tds_amount ?? 0), 2);
                }
                $tdsAmount = round((float) ($clientBill->tds_amount ?? 0), 2);
                $documentNo = (string) ($clientBill->invoice_number ?? $clientBill->ra_number ?? ('Bill #' . $clientBill->id));

                if ($invoiceAmount > 0) {
                    $delta = $invoiceAmount;
                    $representedDelta += $delta;
                    $running = round($running + $delta, 2);
                    $entryRows[] = $this->analyticalDeltaRow(
                        $entryDate,
                        'Bill',
                        $documentNo,
                        $voucherNo,
                        'Sales Invoice',
                        $invoiceAmount,
                        0.0,
                        0.0,
                        $delta,
                        $running,
                    );
                }

                if ($tdsAmount > 0) {
                    $delta = round(-1 * $tdsAmount, 2);
                    $representedDelta += $delta;
                    $running = round($running + $delta, 2);
                    $entryRows[] = $this->analyticalDeltaRow(
                        $entryDate,
                        'TDS',
                        $documentNo,
                        $voucherNo,
                        trim('TDS ' . (string) ($clientBill->tds_section ?? '')),
                        0.0,
                        $tdsAmount,
                        0.0,
                        $delta,
                        $running,
                    );
                }
            } elseif (($subcontractorBill = $subcontractorBillsByVoucherId->get((int) $entry->voucher_id)) instanceof SubcontractorRaBill) {
                $sourceVoucherKey = 'subcontractor:' . (int) $entry->voucher_id;
                if (isset($processedSourceVouchers[$sourceVoucherKey])) {
                    continue;
                }
                $effectiveEntryDelta = (float) ($entryDeltaByVoucherId->get($entry->voucher_id) ?? $entryDelta);

                $documentNo = (string) ($subcontractorBill->bill_number ?: $subcontractorBill->ra_number ?: ('RA #' . $subcontractorBill->id));
                $grossBillAmount = round(
                    (float) ($subcontractorBill->current_amount ?? 0)
                    + (float) ($subcontractorBill->total_gst ?? 0)
                    + (float) ($subcontractorBill->round_off ?? 0),
                    2
                );
                $advanceRecovery = round((float) ($subcontractorBill->advance_recovery ?? 0), 2);
                if ($advanceRecovery > 0) {
                    $grossBillAmount = round($grossBillAmount - $advanceRecovery, 2);
                }
                $deductionRows = [
                    ['type' => 'TDS', 'particulars' => trim('TDS ' . (string) ($subcontractorBill->tds_section ?? '')), 'tds' => (float) ($subcontractorBill->tds_amount ?? 0)],
                    ['type' => 'Retention', 'particulars' => 'Retention', 'amount' => (float) ($subcontractorBill->retention_amount ?? 0)],
                    ['type' => 'Security', 'particulars' => 'Security Deposit', 'amount' => (float) ($subcontractorBill->security_deposit_amount ?? 0)],
                    ['type' => 'Other', 'particulars' => 'Other Deductions', 'amount' => (float) ($subcontractorBill->other_deductions ?? 0)],
                ];

                if ($grossBillAmount > 0) {
                    $delta = round(-1 * $grossBillAmount, 2);
                    $representedDelta += $delta;
                    $running = round($running + $delta, 2);
                    $entryRows[] = $this->analyticalDeltaRow(
                        $entryDate,
                        'Bill',
                        $documentNo,
                        $voucherNo,
                        'Subcontractor RA Bill',
                        $grossBillAmount,
                        0.0,
                        0.0,
                        $delta,
                        $running,
                    );
                }

                foreach ($deductionRows as $deductionRow) {
                    $amount = round((float) ($deductionRow['amount'] ?? $deductionRow['tds'] ?? 0), 2);
                    if ($amount <= 0) {
                        continue;
                    }

                    $delta = $amount;
                    $representedDelta += $delta;
                    $running = round($running + $delta, 2);
                    $entryRows[] = $this->analyticalDeltaRow(
                        $entryDate,
                        (string) $deductionRow['type'],
                        $documentNo,
                        $voucherNo,
                        (string) $deductionRow['particulars'],
                        0.0,
                        isset($deductionRow['tds']) ? $amount : 0.0,
                        isset($deductionRow['tds']) ? 0.0 : $amount,
                        $delta,
                        $running,
                    );
                }

                if ($advanceRecovery > 0) {
                    $delta = round(-1 * $advanceRecovery, 2);
                    $representedDelta += $delta;
                    $running = round($running + $delta, 2);
                    $entryRows[] = $this->analyticalDeltaRow(
                        $entryDate,
                        'Advance',
                        $documentNo,
                        $voucherNo,
                        'Advance Recovery',
                        0.0,
                        0.0,
                        $advanceRecovery,
                        $delta,
                        $running,
                    );
                }
            } else {
                $allocations = $allocationsByVoucherLineId->get((int) $entry->id, collect());

                foreach ($allocations as $allocation) {
                    $amount = round((float) ($allocation->amount ?? 0), 2);
                    if ($amount <= 0) {
                        continue;
                    }

                    $delta = $entryDelta >= 0 ? $amount : (-1 * $amount);
                    $representedDelta += $delta;
                    $running = round($running + $delta, 2);

                    $documentNo = '';
                    $particulars = $entryDelta >= 0 ? 'Payment Adjustment' : 'Receipt Adjustment';
                    $entryType = $entryDelta >= 0 ? 'Payment' : 'Receipt';

                    if ($allocation->mode === 'against' && $allocation->bill_type === PurchaseBill::class) {
                        $refBill = $purchaseBillRefs->get((int) $allocation->bill_id);
                        $documentNo = (string) ($refBill?->reference_no ?: $refBill?->bill_number ?: ('Bill #' . $allocation->bill_id));
                        $particulars = 'Payment Against Bill';
                        $entryType = 'Payment';
                    } elseif ($allocation->mode === 'against' && $clientBillModelClass && $allocation->bill_type === $clientBillModelClass) {
                        $refBill = $clientBillRefs->get((int) $allocation->bill_id);
                        $documentNo = (string) ($refBill->invoice_number ?? $refBill->ra_number ?? ('Bill #' . $allocation->bill_id));
                        $particulars = 'Receipt Against Bill';
                        $entryType = 'Receipt';
                    } elseif ($allocation->mode === 'against' && $allocation->bill_type === SubcontractorRaBill::class) {
                        $refBill = $subcontractorBillRefs->get((int) $allocation->bill_id);
                        $documentNo = (string) ($refBill?->bill_number ?: $refBill?->ra_number ?: ('RA #' . $allocation->bill_id));
                        $particulars = 'Payment Against RA Bill';
                        $entryType = 'Payment';
                    } elseif ($allocation->mode === 'advance' && $allocation->bill_type === PurchaseOrder::class) {
                        $purchaseOrder = $purchaseOrderRefs->get((int) $allocation->bill_id);
                        $documentNo = (string) ($purchaseOrder->code ?? ('PO #' . $allocation->bill_id));
                        $particulars = 'Advance Against PO';
                        $entryType = 'Advance';
                    } elseif ($allocation->mode === 'advance' && $allocation->bill_type === SubcontractorWorkOrder::class) {
                        $workOrder = $workOrderRefs->get((int) $allocation->bill_id);
                        $documentNo = (string) ($workOrder->work_order_number ?? ('WO #' . $allocation->bill_id));
                        $particulars = 'Advance Against Work Order';
                        $entryType = 'Advance';
                    } elseif ($allocation->mode === 'on_account') {
                        $particulars = $entryDelta >= 0 ? 'On Account Payment' : 'On Account Receipt';
                        $entryType = 'On Account';
                    } else {
                        $documentNo = class_basename((string) $allocation->bill_type) . ' #' . (int) $allocation->bill_id;
                        $particulars = $entryDelta >= 0 ? 'Payment Adjustment' : 'Receipt Adjustment';
                    }

                    $entryRows[] = $this->analyticalDeltaRow(
                        $entryDate,
                        $entryType,
                        $documentNo,
                        $voucherNo,
                        $particulars,
                        0.0,
                        0.0,
                        $amount,
                        $delta,
                        $running,
                    );
                }
            }

            if ($sourceVoucherKey && ! empty($entryRows)) {
                $processedSourceVouchers[$sourceVoucherKey] = true;
            }

            $residualDelta = round($effectiveEntryDelta - $representedDelta, 2);
            if (abs($residualDelta) > 0.01 || empty($entryRows)) {
                $running = round($running + $residualDelta, 2);
                $entryRows[] = $this->analyticalDeltaRow(
                    $entryDate,
                    'Other',
                    (string) ($entry->voucher?->reference ?? ''),
                    $voucherNo,
                    (string) ($entry->description ?: ($entry->voucher?->narration ?: 'Ledger Entry')),
                    $residualDelta < 0 ? abs($residualDelta) : 0.0,
                    0.0,
                    $residualDelta > 0 ? abs($residualDelta) : 0.0,
                    $residualDelta,
                    $running,
                );
            }

            foreach ($entryRows as $row) {
                $rows->push($row + ['search_text' => $this->analyticalSearchText($row)]);
            }
        }

        return $rows;
    }

    protected function buildStandardExportRows($ledgerEntries, float $openingBalance, float $closingBalance, bool $includeBreakdown, Collection $voucherLinesByVoucher): Collection
    {
        $rows = collect([
            [
                'date' => '',
                'particulars' => 'OPENING BALANCE',
                'voucher_type' => '',
                'voucher_no' => '',
                'debit' => $openingBalance >= 0 ? number_format(abs($openingBalance), 2, '.', '') : '',
                'credit' => $openingBalance < 0 ? number_format(abs($openingBalance), 2, '.', '') : '',
            ],
        ]);

        foreach ($ledgerEntries as $e) {
            $date = $e->voucher?->voucher_date ? optional($e->voucher->voucher_date)->format('d-m-Y') : '';
            $particulars = trim(implode(' | ', array_filter([
                $e->description ?: ($e->voucher?->narration ?: ''),
                $e->voucher?->reference ? ('Ref: ' . $e->voucher->reference) : null,
                $e->costCenter?->name ? ('Cost Center: ' . $e->costCenter->name) : null,
            ])));

            $rows->push([
                'date' => $date,
                'particulars' => $particulars,
                'voucher_type' => strtoupper((string) ($e->voucher?->voucher_type ?? '')),
                'voucher_no' => (string) ($e->voucher?->voucher_no ?? ''),
                'debit' => number_format((float) $e->debit, 2, '.', ''),
                'credit' => number_format((float) $e->credit, 2, '.', ''),
            ]);

            if (! $includeBreakdown) {
                continue;
            }

            $lines = $voucherLinesByVoucher->get((int) $e->voucher_id, collect());
            foreach ($lines as $vl) {
                if ((int) $vl->id === (int) $e->id) {
                    continue;
                }

                $accCode = $vl->account?->code;
                $accName = $vl->account?->name;
                $accLabel = trim(($accCode ? ($accCode . ' - ') : '') . ($accName ?: ''));

                $rows->push([
                    'date' => $date,
                    'particulars' => trim('DETAIL: ' . ($accLabel ?: 'Voucher Line') . ' | ' . ($vl->description ?? '')),
                    'voucher_type' => strtoupper((string) ($e->voucher?->voucher_type ?? '')),
                    'voucher_no' => (string) ($e->voucher?->voucher_no ?? ''),
                    'debit' => number_format((float) $vl->debit, 2, '.', ''),
                    'credit' => number_format((float) $vl->credit, 2, '.', ''),
                ]);
            }
        }

        $rows->push([
            'date' => '',
            'particulars' => 'CLOSING BALANCE',
            'voucher_type' => '',
            'voucher_no' => '',
            'debit' => $closingBalance >= 0 ? number_format(abs($closingBalance), 2, '.', '') : '',
            'credit' => $closingBalance < 0 ? number_format(abs($closingBalance), 2, '.', '') : '',
        ]);

        return $rows;
    }

    protected function buildAnalyticalExportRows(Collection $analyticalRows, float $openingBalance, float $closingBalance): Collection
    {
        $rows = collect([
            [
                'date' => '',
                'particulars' => 'OPENING BALANCE',
                'voucher_type' => '',
                'voucher_no' => '',
                'debit' => $openingBalance >= 0 ? number_format(abs($openingBalance), 2, '.', '') : '',
                'credit' => $openingBalance < 0 ? number_format(abs($openingBalance), 2, '.', '') : '',
            ],
        ]);

        foreach ($analyticalRows as $row) {
            $particulars = match ($row['entry_type'] ?? '') {
                'TDS' => trim((string) ($row['particulars'] ?? 'TDS') . (! empty($row['document_no']) ? (' on Bill ' . $row['document_no']) : '')),
                default => trim((string) ($row['particulars'] ?? '') . (! empty($row['document_no']) ? (' - ' . $row['document_no']) : '')),
            };

            $rows->push([
                'date' => (string) ($row['date'] ?? ''),
                'particulars' => $particulars,
                'voucher_type' => strtoupper((string) ($row['entry_type'] ?? '')),
                'voucher_no' => (string) ($row['voucher_no'] ?? ''),
                'debit' => (float) ($row['debit_amount'] ?? 0) > 0 ? number_format((float) $row['debit_amount'], 2, '.', '') : '',
                'credit' => (float) ($row['credit_amount'] ?? 0) > 0 ? number_format((float) $row['credit_amount'], 2, '.', '') : '',
            ]);
        }

        $rows->push([
            'date' => '',
            'particulars' => 'CLOSING BALANCE',
            'voucher_type' => '',
            'voucher_no' => '',
            'debit' => $closingBalance >= 0 ? number_format(abs($closingBalance), 2, '.', '') : '',
            'credit' => $closingBalance < 0 ? number_format(abs($closingBalance), 2, '.', '') : '',
        ]);

        return $rows;
    }

    protected function exportCsv(
        int $companyId,
        $fromDate,
        $toDate,
        Account $account,
        $ledgerEntries,
        float $openingBalance,
        float $closingBalance,
        ?int $projectId,
        $projects,
        bool $includeBreakdown = false,
        string $viewMode = 'standard',
        ?Collection $analyticalRows = null,
    ): StreamedResponse {
        if ($viewMode === 'analytical') {
            return $this->exportAnalyticalCsv(
                companyId: $companyId,
                fromDate: $fromDate,
                toDate: $toDate,
                account: $account,
                analyticalRows: $analyticalRows ?? collect(),
                openingBalance: $openingBalance,
                closingBalance: $closingBalance,
            );
        }

        $fileName = 'ledger_' . ($account->code ?: $account->id) . '_' . $fromDate->format('Y-m-d') . '_to_' . $toDate->format('Y-m-d') . ($projectId ? ('_project_' . $projectId) : '') . '.csv';
        $company = Company::query()->find($companyId);
        $companyName = trim((string) ($company?->legal_name ?: $company?->name ?: ('Company #' . $companyId)));

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $columns = [
            'Date',
            'Particulars',
            'Vch Type',
            'Vch No.',
            'Debit (INR)',
            'Credit (INR)',
        ];

        // If requested, include full voucher lines for each voucher in the export.
        // These extra rows do NOT affect the running balance; they are informational only.
        $voucherLinesByVoucher = collect();
        if ($includeBreakdown && $ledgerEntries && count($ledgerEntries)) {
            $voucherIds = collect($ledgerEntries)->pluck('voucher_id')->unique()->values()->all();
            if (! empty($voucherIds)) {
                $voucherLinesByVoucher = VoucherLine::with(['account', 'costCenter'])
                    ->whereIn('voucher_id', $voucherIds)
                    ->orderBy('voucher_id')
                    ->orderBy('line_no')
                    ->get()
                    ->groupBy('voucher_id');
            }
        }

        $rows = $this->buildStandardExportRows($ledgerEntries, $openingBalance, $closingBalance, $includeBreakdown, $voucherLinesByVoucher);

        $callback = function () use ($columns, $companyName, $account, $fromDate, $toDate, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Ledger Statement']);
            fputcsv($handle, ['Company', $companyName]);
            fputcsv($handle, ['Account', trim(($account->code ? ($account->code . ' - ') : '') . $account->name)]);
            fputcsv($handle, ['Period', $fromDate->format('d-m-Y') . ' to ' . $toDate->format('d-m-Y')]);
            fputcsv($handle, []);
            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['date'],
                    $row['particulars'],
                    $row['voucher_type'],
                    $row['voucher_no'],
                    $row['debit'],
                    $row['credit'],
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function exportPdf(
        int $companyId,
        $fromDate,
        $toDate,
        Account $account,
        $ledgerEntries,
        float $openingBalance,
        float $closingBalance,
        bool $includeBreakdown = false,
        string $viewMode = 'standard',
        ?Collection $analyticalRows = null,
    ) {
        $company = Company::query()->find($companyId);
        $companyName = trim((string) ($company?->legal_name ?: $company?->name ?: ('Company #' . $companyId)));

        $logoPath = public_path('images/ems-logo.png');
        if (! (is_string($logoPath) && file_exists($logoPath))) {
            $logoPath = public_path('images/quotation_logo.jpeg');
        }
        $logoSrc = (is_string($logoPath) && file_exists($logoPath)) ? $logoPath : null;

        $voucherLinesByVoucher = collect();
        if ($viewMode === 'standard' && $includeBreakdown && $ledgerEntries && count($ledgerEntries)) {
            $voucherIds = collect($ledgerEntries)->pluck('voucher_id')->unique()->values()->all();
            if (! empty($voucherIds)) {
                $voucherLinesByVoucher = VoucherLine::with(['account', 'costCenter'])
                    ->whereIn('voucher_id', $voucherIds)
                    ->orderBy('voucher_id')
                    ->orderBy('line_no')
                    ->get()
                    ->groupBy('voucher_id');
            }
        }

        $rows = $viewMode === 'analytical'
            ? $this->buildAnalyticalExportRows($analyticalRows ?? collect(), $openingBalance, $closingBalance)
            : $this->buildStandardExportRows($ledgerEntries, $openingBalance, $closingBalance, $includeBreakdown, $voucherLinesByVoucher);

        $pdf = Pdf::loadView('accounting.reports.ledger_pdf', [
            'companyName' => $companyName,
            'company' => $company,
            'logoSrc' => $logoSrc,
            'account' => $account,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'rows' => $rows,
        ])->setPaper('a4', 'portrait');

        $fileName = 'ledger_' . ($account->code ?: $account->id) . '_' . $fromDate->format('Y-m-d') . '_to_' . $toDate->format('Y-m-d') . '.pdf';

        return $pdf->download($fileName);
    }

    protected function exportAnalyticalCsv(
        int $companyId,
        $fromDate,
        $toDate,
        Account $account,
        Collection $analyticalRows,
        float $openingBalance,
        float $closingBalance,
    ): StreamedResponse {
        $fileName = 'analytical_ledger_' . ($account->code ?: $account->id) . '_' . $fromDate->format('Y-m-d') . '_to_' . $toDate->format('Y-m-d') . '.csv';
        $company = Company::query()->find($companyId);
        $companyName = trim((string) ($company?->legal_name ?: $company?->name ?: ('Company #' . $companyId)));

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $columns = [
            'Date',
            'Particulars',
            'Vch Type',
            'Vch No.',
            'Debit (INR)',
            'Credit (INR)',
        ];

        $rows = $this->buildAnalyticalExportRows($analyticalRows, $openingBalance, $closingBalance);

        $callback = function () use ($columns, $companyName, $account, $fromDate, $toDate, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Ledger Statement']);
            fputcsv($handle, ['Company', $companyName]);
            fputcsv($handle, ['Account', trim(($account->code ? ($account->code . ' - ') : '') . $account->name)]);
            fputcsv($handle, ['Period', $fromDate->format('d-m-Y') . ' to ' . $toDate->format('d-m-Y')]);
            fputcsv($handle, []);
            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['date'],
                    $row['particulars'],
                    $row['voucher_type'],
                    $row['voucher_no'],
                    $row['debit'],
                    $row['credit'],
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
