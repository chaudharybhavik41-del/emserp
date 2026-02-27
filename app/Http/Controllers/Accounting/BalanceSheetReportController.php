<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceSheetReportController extends Controller
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
     * Balance Sheet as on a date.
     *
     * We compute balances from:
     * - Opening balances (effective on/before asOfDate)
     * - Posted vouchers up to asOfDate
     *
     * We exclude Income & Expense groups and add a Profit/Loss balancing line.
     */
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $asOfDate  = $request->date('as_of_date') ?: now();
        $export    = $request->get('export');

        $accounts = Account::with('group')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        // Aggregate posted voucher movements up to asOfDate
        $movements = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereDate('v.voucher_date', '<=', $asOfDate->toDateString())
            ->whereIn('vl.account_id', $accounts->keys())
            ->selectRaw('vl.account_id, COALESCE(SUM(vl.debit),0) as total_debit, COALESCE(SUM(vl.credit),0) as total_credit')
            ->groupBy('vl.account_id')
            ->get()
            ->keyBy('account_id');

        $assetGroups = [];
        $liabilityGroups = [];

        foreach ($accounts as $accountId => $account) {
            $group = $account->group;
            if (! $group) {
                continue;
            }

            // Skip P&L accounts from Balance Sheet
            if (in_array($group->nature, ['income', 'expense'], true)) {
                continue;
            }

            $agg    = $movements->get($accountId);
            $debit  = $agg ? (float) $agg->total_debit : 0.0;
            $credit = $agg ? (float) $agg->total_credit : 0.0;

            // Opening (company-level)
            $opening = (float) ($account->opening_balance ?? 0.0);
            if ($account->opening_balance_date && $account->opening_balance_date->gt($asOfDate)) {
                $opening = 0.0;
            }
            if ($opening != 0.0) {
                $opening *= ($account->opening_balance_type === 'cr') ? -1 : 1;
            }

            // Net as-of (Dr positive, Cr negative)
            $net = $opening + ($debit - $credit);

            if (abs($net) < 0.005) {
                continue;
            }

            if ($group->nature === 'asset') {
                // Amount on Assets side (Dr positive, Cr negative)
                $assetGroups[$group->id] ??= [
                    'group' => $group,
                    'accounts' => [],
                    'total' => 0.0,
                ];

                $assetGroups[$group->id]['accounts'][] = [
                    'account' => $account,
                    'amount' => $net,
                ];

                $assetGroups[$group->id]['total'] += $net;
            } else {
                // Liabilities/Equity side: credit balances are positive
                $amount = -1 * $net;

                $liabilityGroups[$group->id] ??= [
                    'group' => $group,
                    'accounts' => [],
                    'total' => 0.0,
                ];

                $liabilityGroups[$group->id]['accounts'][] = [
                    'account' => $account,
                    'amount' => $amount,
                ];

                $liabilityGroups[$group->id]['total'] += $amount;
            }
        }

        // Sort groups by sort_order then name
        $assetGroups = array_values($assetGroups);
        $liabilityGroups = array_values($liabilityGroups);

        usort($assetGroups, fn ($a, $b) => (($a['group']->sort_order ?? 999999) <=> ($b['group']->sort_order ?? 999999)) ?: strcmp($a['group']->name, $b['group']->name));
        usort($liabilityGroups, fn ($a, $b) => (($a['group']->sort_order ?? 999999) <=> ($b['group']->sort_order ?? 999999)) ?: strcmp($a['group']->name, $b['group']->name));

        // Sort accounts within groups by name
        foreach ($assetGroups as &$g) {
            usort($g['accounts'], fn ($x, $y) => strcmp($x['account']->name, $y['account']->name));
        }
        unset($g);

        foreach ($liabilityGroups as &$g) {
            usort($g['accounts'], fn ($x, $y) => strcmp($x['account']->name, $y['account']->name));
        }
        unset($g);

        $totalAssets = array_reduce($assetGroups, fn ($carry, $g) => $carry + (float) $g['total'], 0.0);
        $totalLiabilities = array_reduce($liabilityGroups, fn ($carry, $g) => $carry + (float) $g['total'], 0.0);

        // Profit/Loss balancing line
        $plBalance = $totalAssets - $totalLiabilities;

        if ($export === 'csv') {
            return $this->exportCsv(
                companyId: $companyId,
                asOfDate: $asOfDate,
                assetGroups: $assetGroups,
                liabilityGroups: $liabilityGroups,
                totalAssets: $totalAssets,
                totalLiabilities: $totalLiabilities,
                plBalance: $plBalance,
            );
        }

        return view('accounting.reports.balance_sheet', [
            'companyId' => $companyId,
            'asOfDate' => $asOfDate,
            'assetGroups' => $assetGroups,
            'liabilityGroups' => $liabilityGroups,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'plBalance' => $plBalance,
        ]);
    }

    protected function exportCsv(
        int $companyId,
        $asOfDate,
        array $assetGroups,
        array $liabilityGroups,
        float $totalAssets,
        float $totalLiabilities,
        float $plBalance,
    ): StreamedResponse {
        $fileName = 'balance_sheet_' . $asOfDate->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $columns = [
            'Company ID',
            'As On',
            'Side',
            'Group',
            'Account Code',
            'Account Name',
            'Amount',
            'Dr/Cr',
        ];

        $callback = function () use ($columns, $companyId, $asOfDate, $assetGroups, $liabilityGroups, $totalAssets, $totalLiabilities, $plBalance) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            $emitGroup = function (string $side, array $group, callable $drCrResolver) use ($handle, $companyId, $asOfDate) {
                $groupName = $group['group']?->name ?? '';

                foreach ($group['accounts'] as $row) {
                    $acc = $row['account'];
                    $amount = (float) $row['amount'];
                    $drcr = $drCrResolver($amount);

                    fputcsv($handle, [
                        $companyId,
                        $asOfDate->toDateString(),
                        $side,
                        $groupName,
                        $acc->code,
                        $acc->name,
                        number_format(abs($amount), 2, '.', ''),
                        $drcr,
                    ]);
                }

                // Group total row
                $total = (float) $group['total'];
                fputcsv($handle, [
                    $companyId,
                    $asOfDate->toDateString(),
                    $side,
                    $groupName,
                    '',
                    'TOTAL ' . $groupName,
                    number_format(abs($total), 2, '.', ''),
                    $drCrResolver($total),
                ]);

                fputcsv($handle, []);
            };

            // Assets: Dr positive, Cr negative
            foreach ($assetGroups as $g) {
                $emitGroup('ASSETS', $g, fn (float $amt) => $amt >= 0 ? 'Dr' : 'Cr');
            }

            // Liabilities/Equity: Cr positive, Dr negative
            foreach ($liabilityGroups as $g) {
                $emitGroup('LIABILITIES', $g, fn (float $amt) => $amt >= 0 ? 'Cr' : 'Dr');
            }

            // Profit/Loss balancing
            if (abs($plBalance) >= 0.005) {
                if ($plBalance > 0) {
                    // Profit (credit) on liabilities side
                    fputcsv($handle, [$companyId, $asOfDate->toDateString(), 'LIABILITIES', 'Profit/Loss', '', 'Profit (Balancing)', number_format(abs($plBalance), 2, '.', ''), 'Cr']);
                } else {
                    // Loss (debit) on assets side
                    fputcsv($handle, [$companyId, $asOfDate->toDateString(), 'ASSETS', 'Profit/Loss', '', 'Loss (Balancing)', number_format(abs($plBalance), 2, '.', ''), 'Dr']);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, [$companyId, $asOfDate->toDateString(), 'TOTALS', '', '', 'Total Assets', number_format(abs($totalAssets), 2, '.', ''), $totalAssets >= 0 ? 'Dr' : 'Cr']);
            fputcsv($handle, [$companyId, $asOfDate->toDateString(), 'TOTALS', '', '', 'Total Liabilities', number_format(abs($totalLiabilities), 2, '.', ''), $totalLiabilities >= 0 ? 'Cr' : 'Dr']);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
