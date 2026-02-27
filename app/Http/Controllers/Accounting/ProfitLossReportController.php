<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitLossReportController extends Controller
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
     * Profit & Loss: Income/Expense groups for a date range.
     */
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $toDate   = $request->date('to_date') ?: now();
        $fromDate = $request->date('from_date') ?: $toDate->copy()->startOfMonth();

        $export = $request->get('export');

        // Include inactive accounts too (if they have movements they must appear in statements)
        $accounts = Account::with('group')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        // Aggregate voucher movements in period
        $movements = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereDate('v.voucher_date', '>=', $fromDate->toDateString())
            ->whereDate('v.voucher_date', '<=', $toDate->toDateString())
            ->whereIn('vl.account_id', $accounts->keys())
            ->selectRaw('vl.account_id, COALESCE(SUM(vl.debit),0) as total_debit, COALESCE(SUM(vl.credit),0) as total_credit')
            ->groupBy('vl.account_id')
            ->get()
            ->keyBy('account_id');

        $incomeGroups = [];
        $expenseGroups = [];

        $totalIncome = 0.0;
        $totalExpense = 0.0;

        foreach ($accounts as $accountId => $account) {
            $group = $account->group;
            if (! $group) {
                continue;
            }

            $nature = $group->nature;
            if (! in_array($nature, ['income', 'expense'], true)) {
                continue;
            }

            $agg    = $movements->get($accountId);
            $debit  = $agg ? (float) $agg->total_debit : 0.0;
            $credit = $agg ? (float) $agg->total_credit : 0.0;

            // For P&L, we use period movement only:
            // - Income normally has credit balance => amount = credit - debit
            // - Expense normally has debit balance => amount = debit - credit
            $amount = 0.0;
            if ($nature === 'income') {
                $amount = $credit - $debit;
                if (abs($amount) < 0.005) {
                    continue;
                }
                $totalIncome += $amount;

                $incomeGroups[$group->id] ??= [
                    'group' => $group,
                    'accounts' => [],
                    'total' => 0.0,
                ];

                $incomeGroups[$group->id]['accounts'][] = [
                    'account' => $account,
                    'amount' => $amount,
                ];

                $incomeGroups[$group->id]['total'] += $amount;
            } else {
                $amount = $debit - $credit;
                if (abs($amount) < 0.005) {
                    continue;
                }
                $totalExpense += $amount;

                $expenseGroups[$group->id] ??= [
                    'group' => $group,
                    'accounts' => [],
                    'total' => 0.0,
                ];

                $expenseGroups[$group->id]['accounts'][] = [
                    'account' => $account,
                    'amount' => $amount,
                ];

                $expenseGroups[$group->id]['total'] += $amount;
            }
        }

        // Sort groups by sort_order then name
        $incomeGroups = array_values($incomeGroups);
        $expenseGroups = array_values($expenseGroups);

        usort($incomeGroups, fn ($a, $b) => (($a['group']->sort_order ?? 999999) <=> ($b['group']->sort_order ?? 999999)) ?: strcmp($a['group']->name, $b['group']->name));
        usort($expenseGroups, fn ($a, $b) => (($a['group']->sort_order ?? 999999) <=> ($b['group']->sort_order ?? 999999)) ?: strcmp($a['group']->name, $b['group']->name));

        // Sort accounts inside groups by name
        foreach ($incomeGroups as &$g) {
            usort($g['accounts'], fn ($x, $y) => strcmp($x['account']->name, $y['account']->name));
        }
        unset($g);

        foreach ($expenseGroups as &$g) {
            usort($g['accounts'], fn ($x, $y) => strcmp($x['account']->name, $y['account']->name));
        }
        unset($g);

        $profit = $totalIncome - $totalExpense;

        if ($export === 'csv') {
            return $this->exportCsv(
                companyId: $companyId,
                fromDate: $fromDate,
                toDate: $toDate,
                incomeGroups: $incomeGroups,
                expenseGroups: $expenseGroups,
                totalIncome: $totalIncome,
                totalExpense: $totalExpense,
                profit: $profit,
            );
        }

        return view('accounting.reports.profit_loss', [
            'companyId' => $companyId,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'incomeGroups' => $incomeGroups,
            'expenseGroups' => $expenseGroups,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'profit' => $profit,
        ]);
    }

    protected function exportCsv(
        int $companyId,
        $fromDate,
        $toDate,
        array $incomeGroups,
        array $expenseGroups,
        float $totalIncome,
        float $totalExpense,
        float $profit,
    ): StreamedResponse {
        $fileName = 'profit_loss_' . $fromDate->format('Y-m-d') . '_to_' . $toDate->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $columns = [
            'Company ID',
            'From Date',
            'To Date',
            'Section',
            'Group',
            'Account Code',
            'Account Name',
            'Amount',
        ];

        $callback = function () use ($columns, $companyId, $fromDate, $toDate, $incomeGroups, $expenseGroups, $totalIncome, $totalExpense, $profit) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            for ($pass = 1; $pass <= 2; $pass++) {
                $sectionName = $pass === 1 ? 'INCOME' : 'EXPENSE';
                $groups = $pass === 1 ? $incomeGroups : $expenseGroups;

                foreach ($groups as $g) {
                    $groupName = $g['group']?->name ?? '';

                    foreach ($g['accounts'] as $row) {
                        $acc = $row['account'];
                        $amount = (float) $row['amount'];

                        fputcsv($handle, [
                            $companyId,
                            $fromDate->toDateString(),
                            $toDate->toDateString(),
                            $sectionName,
                            $groupName,
                            $acc->code,
                            $acc->name,
                            number_format($amount, 2, '.', ''),
                        ]);
                    }

                    // group total row
                    fputcsv($handle, [
                        $companyId,
                        $fromDate->toDateString(),
                        $toDate->toDateString(),
                        $sectionName,
                        $groupName,
                        '',
                        'TOTAL ' . $groupName,
                        number_format((float) $g['total'], 2, '.', ''),
                    ]);
                }

                fputcsv($handle, []);
            }

            // Totals
            fputcsv($handle, ['TOTAL INCOME', '', '', '', '', '', '', number_format($totalIncome, 2, '.', '')]);
            fputcsv($handle, ['TOTAL EXPENSE', '', '', '', '', '', '', number_format($totalExpense, 2, '.', '')]);
            fputcsv($handle, ['PROFIT / (LOSS)', '', '', '', '', '', '', number_format($profit, 2, '.', '')]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
