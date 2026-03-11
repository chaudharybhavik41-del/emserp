<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountBillAllocation;
use App\Models\Accounting\VoucherLine;
use App\Models\Company;
use App\Models\Party;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\Project;
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
        $supportsAnalyticalView = $this->supportsAnalyticalSupplierLedger($account);
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
            $analyticalRows = $this->buildAnalyticalSupplierRows($account, $ledgerEntries, $openingBalance);
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
            'viewMode' => $viewMode,
            'analyticalRows' => $analyticalRows,
            'openingBalance' => $openingBalance,
            'closingBalance' => $closingBalance,
        ]);
    }

    protected function supportsAnalyticalSupplierLedger(?Account $account): bool
    {
        if (! $account || $account->related_model_type !== Party::class || empty($account->related_model_id)) {
            return false;
        }

        $party = Party::query()->find((int) $account->related_model_id);

        return (bool) ($party?->is_supplier);
    }

    protected function buildAnalyticalSupplierRows(Account $account, Collection $ledgerEntries, float $openingBalance): Collection
    {
        if ($ledgerEntries->isEmpty()) {
            return collect();
        }

        $party = Party::query()->find((int) $account->related_model_id);
        if (! $party || ! $party->is_supplier) {
            return collect();
        }

        $voucherIds = $ledgerEntries->pluck('voucher_id')->filter()->unique()->values()->all();
        $voucherLineIds = $ledgerEntries->pluck('id')->filter()->unique()->values()->all();

        $purchaseBillsByVoucherId = PurchaseBill::query()
            ->where('company_id', (int) $account->company_id)
            ->where('supplier_id', (int) $party->id)
            ->whereIn('voucher_id', $voucherIds)
            ->get()
            ->keyBy('voucher_id');

        $allocationsByVoucherLineId = AccountBillAllocation::query()
            ->where('company_id', (int) $account->company_id)
            ->where('account_id', (int) $account->id)
            ->whereIn('voucher_line_id', $voucherLineIds)
            ->whereHas('voucher', function ($query) {
                $query->where('status', 'posted');
            })
            ->orderBy('voucher_line_id')
            ->orderBy('id')
            ->get()
            ->groupBy('voucher_line_id');

        $purchaseBillRefs = PurchaseBill::query()
            ->whereIn('id', $allocationsByVoucherLineId->flatten(1)->where('bill_type', PurchaseBill::class)->pluck('bill_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $purchaseOrderRefs = PurchaseOrder::query()
            ->whereIn('id', $allocationsByVoucherLineId->flatten(1)->where('bill_type', PurchaseOrder::class)->pluck('bill_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $rows = collect();
        $running = round($openingBalance, 2);

        foreach ($ledgerEntries as $entry) {
            $entryDate = optional($entry->voucher?->voucher_date)->toDateString();
            $entryDelta = round((float) $entry->debit - (float) $entry->credit, 2);
            $representedDelta = 0.0;
            $entryRows = [];

            /** @var PurchaseBill|null $purchaseBill */
            $purchaseBill = $purchaseBillsByVoucherId->get((int) $entry->voucher_id);
            if ($purchaseBill) {
                $grossBillAmount = round(
                    (float) ($purchaseBill->total_amount ?? 0) + (float) ($purchaseBill->tcs_amount ?? 0),
                    2
                );
                $tdsAmount = round((float) ($purchaseBill->tds_amount ?? 0), 2);

                if ($grossBillAmount > 0) {
                    $delta = round(-1 * $grossBillAmount, 2);
                    $representedDelta += $delta;
                    $running = round($running + $delta, 2);
                    $entryRows[] = [
                        'date' => $entryDate,
                        'entry_type' => 'Bill',
                        'document_no' => (string) ($purchaseBill->reference_no ?: $purchaseBill->bill_number ?: ''),
                        'voucher_no' => (string) ($entry->voucher?->voucher_no ?? ''),
                        'particulars' => 'Purchase Bill',
                        'bill_amount' => $grossBillAmount,
                        'tds_amount' => 0.0,
                        'payment_amount' => 0.0,
                        'balance' => $running,
                        'balance_type' => $running >= 0 ? 'Dr' : 'Cr',
                    ];
                }

                if ($tdsAmount > 0) {
                    $delta = $tdsAmount;
                    $representedDelta += $delta;
                    $running = round($running + $delta, 2);
                    $entryRows[] = [
                        'date' => $entryDate,
                        'entry_type' => 'TDS',
                        'document_no' => (string) ($purchaseBill->reference_no ?: $purchaseBill->bill_number ?: ''),
                        'voucher_no' => (string) ($entry->voucher?->voucher_no ?? ''),
                        'particulars' => trim('TDS ' . (string) ($purchaseBill->tds_section ?? '')),
                        'bill_amount' => 0.0,
                        'tds_amount' => $tdsAmount,
                        'payment_amount' => 0.0,
                        'balance' => $running,
                        'balance_type' => $running >= 0 ? 'Dr' : 'Cr',
                    ];
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
                    $particulars = 'Settlement';

                    if ($allocation->mode === 'against' && $allocation->bill_type === PurchaseBill::class) {
                        $refBill = $purchaseBillRefs->get((int) $allocation->bill_id);
                        $documentNo = (string) ($refBill?->reference_no ?: $refBill?->bill_number ?: ('Bill #' . $allocation->bill_id));
                        $particulars = 'Payment Against Bill';
                    } elseif ($allocation->mode === 'advance' && $allocation->bill_type === PurchaseOrder::class) {
                        $purchaseOrder = $purchaseOrderRefs->get((int) $allocation->bill_id);
                        $documentNo = (string) ($purchaseOrder->code ?? ('PO #' . $allocation->bill_id));
                        $particulars = 'Advance Against PO';
                    } elseif ($allocation->mode === 'on_account') {
                        $particulars = 'On Account Payment';
                    } else {
                        $documentNo = class_basename((string) $allocation->bill_type) . ' #' . (int) $allocation->bill_id;
                        $particulars = 'Payment Adjustment';
                    }

                    $entryRows[] = [
                        'date' => $entryDate,
                        'entry_type' => 'Payment',
                        'document_no' => $documentNo,
                        'voucher_no' => (string) ($entry->voucher?->voucher_no ?? ''),
                        'particulars' => $particulars,
                        'bill_amount' => 0.0,
                        'tds_amount' => 0.0,
                        'payment_amount' => $amount,
                        'balance' => $running,
                        'balance_type' => $running >= 0 ? 'Dr' : 'Cr',
                    ];
                }
            }

            $residualDelta = round($entryDelta - $representedDelta, 2);
            if (abs($residualDelta) > 0.01 || empty($entryRows)) {
                $running = round($running + $residualDelta, 2);
                $entryRows[] = [
                    'date' => $entryDate,
                    'entry_type' => 'Other',
                    'document_no' => (string) ($entry->voucher?->reference ?? ''),
                    'voucher_no' => (string) ($entry->voucher?->voucher_no ?? ''),
                    'particulars' => (string) ($entry->description ?: ($entry->voucher?->narration ?: 'Ledger Entry')),
                    'bill_amount' => $residualDelta < 0 ? abs($residualDelta) : 0.0,
                    'tds_amount' => 0.0,
                    'payment_amount' => $residualDelta > 0 ? abs($residualDelta) : 0.0,
                    'balance' => $running,
                    'balance_type' => $running >= 0 ? 'Dr' : 'Cr',
                ];
            }

            foreach ($entryRows as $row) {
                $searchable = strtolower(trim(implode(' ', [
                    $row['date'],
                    $row['entry_type'],
                    $row['document_no'],
                    $row['voucher_no'],
                    $row['particulars'],
                ])));

                $rows->push($row + ['search_text' => $searchable]);
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
            $date = $e->voucher?->voucher_date ? optional($e->voucher->voucher_date)->toDateString() : '';
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
                'Bill' => trim('Purchase Bill' . (! empty($row['document_no']) ? (' - ' . $row['document_no']) : '')),
                'TDS' => trim(($row['particulars'] ?? 'TDS') . (! empty($row['document_no']) ? (' on Bill ' . $row['document_no']) : '')),
                'Payment' => trim(($row['particulars'] ?? 'Payment') . (! empty($row['document_no']) ? (' - ' . $row['document_no']) : '')),
                default => (string) ($row['particulars'] ?? ''),
            };

            $debit = '';
            $credit = '';

            if (($row['entry_type'] ?? '') === 'Bill') {
                $credit = number_format((float) ($row['bill_amount'] ?? 0), 2, '.', '');
            } elseif (($row['entry_type'] ?? '') === 'TDS') {
                $debit = number_format((float) ($row['tds_amount'] ?? 0), 2, '.', '');
            } elseif (($row['entry_type'] ?? '') === 'Payment') {
                $debit = number_format((float) ($row['payment_amount'] ?? 0), 2, '.', '');
            } else {
                $debit = (float) ($row['payment_amount'] ?? 0) > 0 ? number_format((float) $row['payment_amount'], 2, '.', '') : '';
                $credit = (float) ($row['bill_amount'] ?? 0) > 0 ? number_format((float) $row['bill_amount'], 2, '.', '') : '';
            }

            $rows->push([
                'date' => (string) ($row['date'] ?? ''),
                'particulars' => $particulars,
                'voucher_type' => strtoupper((string) ($row['entry_type'] ?? '')),
                'voucher_no' => (string) ($row['voucher_no'] ?? ''),
                'debit' => $debit,
                'credit' => $credit,
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
            fputcsv($handle, ['Period', $fromDate->toDateString() . ' to ' . $toDate->toDateString()]);
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
            fputcsv($handle, ['Period', $fromDate->toDateString() . ' to ' . $toDate->toDateString()]);
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
