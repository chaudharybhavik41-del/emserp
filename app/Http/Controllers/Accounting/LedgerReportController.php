<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\VoucherLine;
use App\Models\Project;
use Illuminate\Http\Request;
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
            'openingBalance' => $openingBalance,
            'closingBalance' => $closingBalance,
        ]);
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
    ): StreamedResponse {
        $projectLabel = '';
        if ($projectId) {
            $p = $projects->firstWhere('id', $projectId);
            $projectLabel = $p ? ($p->code . ' - ' . $p->name) : ('#' . $projectId);
        }

        $fileName = 'ledger_' . ($account->code ?: $account->id) . '_' . $fromDate->format('Y-m-d') . '_to_' . $toDate->format('Y-m-d') . ($projectId ? ('_project_' . $projectId) : '') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $columns = [
            'Company ID',
            'Project',
            'Account Code',
            'Account Name',
            'From Date',
            'To Date',
            'Entry Date',
            'Voucher No',
            'Voucher Type',
            'Reference',
            'Narration',
            'Line Description',
            'Cost Center',
            'Debit',
            'Credit',
            'Running Balance',
            'Dr/Cr',
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

        $callback = function () use ($columns, $companyId, $projectLabel, $account, $fromDate, $toDate, $ledgerEntries, $openingBalance, $includeBreakdown, $voucherLinesByVoucher) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            $running = $openingBalance;
            $openingType = $running >= 0 ? 'Dr' : 'Cr';

            // Opening row
            fputcsv($handle, [
                $companyId,
                $projectLabel,
                $account->code,
                $account->name,
                $fromDate->toDateString(),
                $toDate->toDateString(),
                '',
                '',
                '',
                '',
                'OPENING BALANCE',
                '',
                '',
                '',
                '',
                number_format(abs($running), 2, '.', ''),
                $openingType,
            ]);

            foreach ($ledgerEntries as $e) {
                $date = $e->voucher?->voucher_date ? optional($e->voucher->voucher_date)->toDateString() : '';

                $running += ((float) $e->debit - (float) $e->credit);
                $type = $running >= 0 ? 'Dr' : 'Cr';

                fputcsv($handle, [
                    $companyId,
                    $projectLabel,
                    $account->code,
                    $account->name,
                    $fromDate->toDateString(),
                    $toDate->toDateString(),
                    $date,
                    $e->voucher?->voucher_no,
                    $e->voucher?->voucher_type,
                    $e->voucher?->reference,
                    $e->voucher?->narration,
                    $e->description,
                    $e->costCenter?->name,
                    number_format((float) $e->debit, 2, '.', ''),
                    number_format((float) $e->credit, 2, '.', ''),
                    number_format(abs($running), 2, '.', ''),
                    $type,
                ]);

                // Optional voucher break-up rows (informational).
                if ($includeBreakdown) {
                    $vId = (int) $e->voucher_id;
                    $lines = $voucherLinesByVoucher->get($vId, collect());

                    foreach ($lines as $vl) {
                        // Skip the primary ledger line to avoid duplication in export
                        if ((int) $vl->id === (int) $e->id) {
                            continue;
                        }

                        $accCode = $vl->account?->code;
                        $accName = $vl->account?->name;
                        $accLabel = trim(($accCode ? ($accCode . ' - ') : '') . ($accName ?: ''));

                        fputcsv($handle, [
                            $companyId,
                            $projectLabel,
                            $account->code,
                            $account->name,
                            $fromDate->toDateString(),
                            $toDate->toDateString(),
                            $date,
                            $e->voucher?->voucher_no,
                            $e->voucher?->voucher_type,
                            $e->voucher?->reference,
                            'DETAIL: ' . ($accLabel ?: 'Voucher Line'),
                            $vl->description,
                            $vl->costCenter?->name,
                            number_format((float) $vl->debit, 2, '.', ''),
                            number_format((float) $vl->credit, 2, '.', ''),
                            '',
                            '',
                        ]);
                    }
                }
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}