<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class FundFlowReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.reports.view')->only('index');
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    /**
     * Simple fund-flow style report based on movements in Balance Sheet groups.
     *
     * For each account_group with nature in [asset, liability, equity], we compute:
     *  - Opening balance as on (from_date - 1)
     *  - Closing balance as on to_date
     *  - Delta = closing - opening
     *
     * Interpretation:
     *  - Assets:   increase = Application, decrease = Source
     *  - Liab/Eq:  increase = Source,     decrease = Application
     *
     * This is not a full statutory fund-flow, but a practical "sources & uses"
     * view for management, leveraging your existing account groups.
     */
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $toDate    = $request->date('to_date') ?: now();
        $fromDate  = $request->date('from_date') ?: $toDate->copy()->startOfYear();

        // Load all active accounts with their groups
        $accounts = Account::with('group')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($accounts->isEmpty()) {
            return view('accounting.reports.fund_flow', [
                'companyId'   => $companyId,
                'fromDate'    => $fromDate,
                'toDate'      => $toDate,
                'sources'     => [],
                'applications'=> [],
                'totalSources'=> 0.0,
                'totalApps'   => 0.0,
                'difference'  => 0.0,
            ]);
        }

        // Movements before fromDate (for opening) and up to toDate (for closing)
        $beforeFrom = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereDate('v.voucher_date', '<', $fromDate->toDateString())
            ->selectRaw('vl.account_id, COALESCE(SUM(vl.debit),0) as total_debit, COALESCE(SUM(vl.credit),0) as total_credit')
            ->groupBy('vl.account_id')
            ->get()
            ->keyBy('account_id');

        $uptoTo = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereDate('v.voucher_date', '<=', $toDate->toDateString())
            ->selectRaw('vl.account_id, COALESCE(SUM(vl.debit),0) as total_debit, COALESCE(SUM(vl.credit),0) as total_credit')
            ->groupBy('vl.account_id')
            ->get()
            ->keyBy('account_id');

        // Aggregate opening/closing balances at group level
        $groupData = [];

        foreach ($accounts as $accountId => $account) {
            $group = $account->group;
            if (! $group) {
                continue;
            }

            $nature = strtolower($group->nature ?? '');

            // Consider only Balance Sheet groups
            if (! in_array($nature, ['asset', 'liability', 'equity'], true)) {
                continue;
            }

            $groupId = $group->id;

            if (! isset($groupData[$groupId])) {
                $groupData[$groupId] = [
                    'group'    => $group,
                    'nature'   => $nature,
                    'opening'  => 0.0,
                    'closing'  => 0.0,
                ];
            }

            // Opening as on (fromDate - 1)
            $opening = (float) ($account->opening_balance ?? 0.0);
            if ($opening != 0.0) {
                $opening *= ($account->opening_balance_type === 'cr') ? -1 : 1;
            }

            $aggBefore = $beforeFrom->get($accountId);
            $movementBefore = $aggBefore ? ((float) $aggBefore->total_debit - (float) $aggBefore->total_credit) : 0.0;

            $openingBalance = $opening + $movementBefore;

            // Closing as on toDate
            $aggTo = $uptoTo->get($accountId);
            $movementTo = $aggTo ? ((float) $aggTo->total_debit - (float) $aggTo->total_credit) : 0.0;

            $closingBalance = $opening + $movementTo;

            $groupData[$groupId]['opening'] += $openingBalance;
            $groupData[$groupId]['closing'] += $closingBalance;
        }

        $sources      = [];
        $applications = [];
        $totalSources = 0.0;
        $totalApps    = 0.0;

        foreach ($groupData as $row) {
            $group   = $row['group'];
            $nature  = $row['nature'];
            $opening = $row['opening'];
            $closing = $row['closing'];

            $delta = $closing - $opening;

            if (abs($delta) < 0.005) {
                continue;
            }

            $amount = abs($delta);
            $label  = $group->name ?? 'Group';

            if ($nature === 'asset') {
                if ($delta > 0) {
                    // Asset increased -> Application
                    $applications[] = [
                        'group'  => $group,
                        'label'  => $label,
                        'amount' => $amount,
                        'note'   => 'Increase in asset',
                    ];
                    $totalApps += $amount;
                } else {
                    // Asset decreased -> Source
                    $sources[] = [
                        'group'  => $group,
                        'label'  => $label,
                        'amount' => $amount,
                        'note'   => 'Decrease in asset',
                    ];
                    $totalSources += $amount;
                }
            } elseif (in_array($nature, ['liability', 'equity'], true)) {
                if ($delta > 0) {
                    // Liab/Equity increased -> Source
                    $sources[] = [
                        'group'  => $group,
                        'label'  => $label,
                        'amount' => $amount,
                        'note'   => 'Increase in liability/equity',
                    ];
                    $totalSources += $amount;
                } else {
                    // Liab/Equity decreased -> Application
                    $applications[] = [
                        'group'  => $group,
                        'label'  => $label,
                        'amount' => $amount,
                        'note'   => 'Decrease in liability/equity',
                    ];
                    $totalApps += $amount;
                }
            }
        }

        // Sort by label
        usort($sources, function (array $a, array $b) {
            return strcmp($a['label'], $b['label']);
        });

        usort($applications, function (array $a, array $b) {
            return strcmp($a['label'], $b['label']);
        });

        $difference = $totalSources - $totalApps;

        return view('accounting.reports.fund_flow', [
            'companyId'    => $companyId,
            'fromDate'     => $fromDate,
            'toDate'       => $toDate,
            'sources'      => $sources,
            'applications' => $applications,
            'totalSources' => $totalSources,
            'totalApps'    => $totalApps,
            'difference'   => $difference,
        ]);
    }
}
