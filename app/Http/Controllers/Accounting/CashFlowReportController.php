<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class CashFlowReportController extends Controller
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
     * Direct cash-flow statement based on movements in cash / bank accounts.
     *
     * - Cash / bank accounts are identified by account.type in cashflow_cash_account_types
     *   (default: ['bank', 'cash']). You can override via config/accounting.php:
     *
     *     'cashflow_cash_account_types' => ['bank', 'cash'],
     *     'cashflow_cash_group_codes'   => ['CASH', 'BANK'],
     *
     * - Vouchers of type "contra" (and anything in cashflow_ignore_types) are
     *   ignored to avoid double-counting cash-to-cash transfers.
     */
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $toDate    = $request->date('to_date') ?: now();
        $fromDate  = $request->date('from_date') ?: $toDate->copy()->startOfMonth();

        // Detect cash / bank ledgers
        $cashAccountTypes = Config::get('accounting.cashflow_cash_account_types', ['bank', 'cash']);
        $cashGroupCodes   = Config::get('accounting.cashflow_cash_group_codes', []);

        $cashAccountsQuery = Account::with('group')
            ->where('company_id', $companyId)
            ->where('is_active', true);

        if (! empty($cashAccountTypes)) {
            $cashAccountsQuery->whereIn('type', $cashAccountTypes);
        }

        $cashAccounts = $cashAccountsQuery->get();

        // Fallback by group code if no explicit cash/bank types are found
        if ($cashAccounts->isEmpty() && ! empty($cashGroupCodes)) {
            $cashAccounts = Account::with('group')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereHas('group', function ($q) use ($cashGroupCodes) {
                    $q->whereIn('code', $cashGroupCodes);
                })
                ->get();
        }

        $cashAccountIds = $cashAccounts->pluck('id')->all();

        if (empty($cashAccountIds)) {
            // No cash/bank accounts configured; return an empty view with hint
            return view('accounting.reports.cash_flow', [
                'companyId'    => $companyId,
                'fromDate'     => $fromDate,
                'toDate'       => $toDate,
                'cashAccounts' => $cashAccounts,
                'openingCash'  => 0.0,
                'closingCash'  => 0.0,
                'totalInflow'  => 0.0,
                'totalOutflow' => 0.0,
                'netChange'    => 0.0,
                'typeRows'     => [],
                'lines'        => [],
            ]);
        }

        // Opening cash/bank balance as on (fromDate - 1)
        $before = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereIn('vl.account_id', $cashAccountIds)
            ->whereDate('v.voucher_date', '<', $fromDate->toDateString())
            ->selectRaw('vl.account_id, COALESCE(SUM(vl.debit),0) as total_debit, COALESCE(SUM(vl.credit),0) as total_credit')
            ->groupBy('vl.account_id')
            ->get()
            ->keyBy('account_id');

        $openingCash = 0.0;

        foreach ($cashAccounts as $account) {
            $opening = (float) ($account->opening_balance ?? 0.0);
            if ($opening != 0.0) {
                $opening *= ($account->opening_balance_type === 'cr') ? -1 : 1;
            }

            $agg = $before->get($account->id);
            $movementBefore = $agg ? ((float) $agg->total_debit - (float) $agg->total_credit) : 0.0;

            $openingCash += $opening + $movementBefore;
        }

        // Movements in the period for cash/bank ledgers
        $rawLines = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->join('accounts as a', 'a.id', '=', 'vl.account_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereIn('vl.account_id', $cashAccountIds)
            ->whereDate('v.voucher_date', '>=', $fromDate->toDateString())
            ->whereDate('v.voucher_date', '<=', $toDate->toDateString())
            ->select(
                'v.id as voucher_id',
                'v.voucher_no',
                'v.voucher_type',
                'v.voucher_date',
                'v.narration',
                'a.id as account_id',
                'a.code as account_code',
                'a.name as account_name',
                'vl.debit',
                'vl.credit'
            )
            ->orderBy('v.voucher_date')
            ->orderBy('v.id')
            ->orderBy('vl.line_no')
            ->get();

        $ignoreTypes = Config::get('accounting.cashflow_ignore_types', ['contra']);

        $typeRows    = [];
        $detailLines = [];
        $totalInflow = 0.0;
        $totalOutflow = 0.0;

        foreach ($rawLines as $line) {
            $type = $line->voucher_type ?: 'unknown';

            if (in_array($type, $ignoreTypes, true)) {
                // Contra / internal cash transfers – ignore in cash-flow
                continue;
            }

            $debit  = (float) $line->debit;
            $credit = (float) $line->credit;
            $delta  = $debit - $credit; // Dr = cash in, Cr = cash out (for asset accounts)

            $inflow  = $delta > 0 ? $delta : 0.0;
            $outflow = $delta < 0 ? -1 * $delta : 0.0;

            if (! isset($typeRows[$type])) {
                $typeRows[$type] = [
                    'voucher_type' => $type,
                    'inflow'       => 0.0,
                    'outflow'      => 0.0,
                ];
            }

            $typeRows[$type]['inflow']  += $inflow;
            $typeRows[$type]['outflow'] += $outflow;

            $totalInflow  += $inflow;
            $totalOutflow += $outflow;

            $detailLines[] = [
                'voucher_id'  => $line->voucher_id,
                'date'        => $line->voucher_date,
                'voucher_no'  => $line->voucher_no,
                'voucher_type'=> $type,
                'narration'   => $line->narration,
                'account_id'  => $line->account_id,
                'account_code'=> $line->account_code,
                'account_name'=> $line->account_name,
                'debit'       => $debit,
                'credit'      => $credit,
                'inflow'      => $inflow,
                'outflow'     => $outflow,
            ];
        }

        // Sort summary rows by voucher type name
        $typeRows = array_values($typeRows);
        usort($typeRows, function (array $a, array $b) {
            return strcmp($a['voucher_type'], $b['voucher_type']);
        });

        $netChange   = $totalInflow - $totalOutflow;
        $closingCash = $openingCash + $netChange;

        return view('accounting.reports.cash_flow', [
            'companyId'    => $companyId,
            'fromDate'     => $fromDate,
            'toDate'       => $toDate,
            'cashAccounts' => $cashAccounts,
            'openingCash'  => $openingCash,
            'closingCash'  => $closingCash,
            'totalInflow'  => $totalInflow,
            'totalOutflow' => $totalOutflow,
            'netChange'    => $netChange,
            'typeRows'     => $typeRows,
            'lines'        => $detailLines,
        ]);
    }
}
