<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialBalanceReportController extends Controller
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
     * Trial Balance (group-wise) as on a date.
     *
     * Notes:
     * - Company-level TB uses opening balances + posted vouchers.
     * - When filtering by project, we show ONLY voucher movements tagged to that project
     *   (opening balances are company-level and are intentionally NOT mixed into project TB).
     */
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $asOfDate  = $request->date('as_of_date') ?: now();
        $projectId = $request->integer('project_id') ?: null;
        $export    = $request->get('export');

        $accounts = Account::with('group')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $projects = Project::orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        if ($accounts->isEmpty()) {
            return view('accounting.reports.trial_balance', [
                'companyId'    => $companyId,
                'asOfDate'     => $asOfDate,
                'projectId'    => $projectId,
                'projects'     => $projects,
                'rows'         => [],
                'grandDebit'   => 0.0,
                'grandCredit'  => 0.0,
                'difference'   => 0.0,
            ]);
        }

        // Aggregate posted voucher movements up to asOfDate
        $movementQuery = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereDate('v.voucher_date', '<=', $asOfDate->toDateString());

        if ($projectId) {
            $movementQuery->where('v.project_id', $projectId);
        }

        $movements = $movementQuery
            ->whereIn('vl.account_id', $accounts->keys())
            ->selectRaw('vl.account_id, COALESCE(SUM(vl.debit),0) as total_debit, COALESCE(SUM(vl.credit),0) as total_credit')
            ->groupBy('vl.account_id')
            ->get()
            ->keyBy('account_id');

        $rows        = [];
        $grandDebit  = 0.0;
        $grandCredit = 0.0;

        foreach ($accounts as $accountId => $account) {
            $agg    = $movements->get($accountId);
            $debit  = $agg ? (float) $agg->total_debit : 0.0;
            $credit = $agg ? (float) $agg->total_credit : 0.0;

            // Opening is company-level only (do not mix with project-filtered TB)
            $opening = 0.0;
            if (! $projectId) {
                $opening = (float) ($account->opening_balance ?? 0.0);

                // Only apply opening if it is effective on/before asOfDate
                if ($account->opening_balance_date && $account->opening_balance_date->gt($asOfDate)) {
                    $opening = 0.0;
                }

                if ($opening != 0.0) {
                    $opening *= ($account->opening_balance_type === 'cr') ? -1 : 1;
                }
            }

            // Net balance as of date (Dr positive, Cr negative)
            $net = $opening + ($debit - $credit);

            if (abs($net) < 0.005) {
                continue;
            }

            $rowDebit  = $net >= 0 ? $net : 0.0;
            $rowCredit = $net < 0 ? (-1 * $net) : 0.0;

            $rows[] = [
                'group'   => $account->group,
                'account' => $account,
                'debit'   => $rowDebit,
                'credit'  => $rowCredit,
                'net'     => $net,
            ];

            $grandDebit  += $rowDebit;
            $grandCredit += $rowCredit;
        }

        // Sort by group sort_order then group name then account name
        usort($rows, function (array $a, array $b) {
            $ga = $a['group'];
            $gb = $b['group'];

            $sa = $ga->sort_order ?? 999999;
            $sb = $gb->sort_order ?? 999999;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            $na = $ga->name ?? 'Ungrouped';
            $nb = $gb->name ?? 'Ungrouped';
            if ($na !== $nb) {
                return strcmp($na, $nb);
            }

            return strcmp($a['account']->name ?? '', $b['account']->name ?? '');
        });

        $difference = round($grandDebit - $grandCredit, 2);

        if ($export === 'csv') {
            return $this->exportCsv(
                rows: $rows,
                companyId: $companyId,
                asOfDate: $asOfDate,
                projectId: $projectId,
                projects: $projects,
                grandDebit: $grandDebit,
                grandCredit: $grandCredit,
                difference: $difference,
            );
        }

        return view('accounting.reports.trial_balance', [
            'companyId'    => $companyId,
            'asOfDate'     => $asOfDate,
            'projectId'    => $projectId,
            'projects'     => $projects,
            'rows'         => $rows,
            'grandDebit'   => $grandDebit,
            'grandCredit'  => $grandCredit,
            'difference'   => $difference,
        ]);
    }

    protected function exportCsv(
        array $rows,
        int $companyId,
        $asOfDate,
        ?int $projectId,
        $projects,
        float $grandDebit,
        float $grandCredit,
        float $difference,
    ): StreamedResponse {
        $projectLabel = '';
        if ($projectId) {
            $p = $projects->firstWhere('id', $projectId);
            $projectLabel = $p ? ($p->code . ' - ' . $p->name) : ('#' . $projectId);
        }

        $fileName = 'trial_balance_' . $asOfDate->format('Y-m-d') . ($projectId ? ('_project_' . $projectId) : '') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $columns = [
            'As On',
            'Company ID',
            'Project',
            'Group',
            'Account Code',
            'Account Name',
            'Debit',
            'Credit',
        ];

        $callback = function () use ($rows, $columns, $asOfDate, $companyId, $projectLabel, $grandDebit, $grandCredit, $difference) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                $groupName = $row['group']?->name ?? 'Ungrouped';
                $acc       = $row['account'];

                fputcsv($handle, [
                    $asOfDate->toDateString(),
                    $companyId,
                    $projectLabel,
                    $groupName,
                    $acc->code,
                    $acc->name,
                    number_format((float) $row['debit'], 2, '.', ''),
                    number_format((float) $row['credit'], 2, '.', ''),
                ]);
            }

            // Totals
            fputcsv($handle, []);
            fputcsv($handle, ['TOTAL', '', '', '', '', '', number_format($grandDebit, 2, '.', ''), number_format($grandCredit, 2, '.', '')]);
            fputcsv($handle, ['DIFFERENCE (Dr - Cr)', '', '', '', '', '', number_format($difference, 2, '.', ''), '']);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
