<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriodLock;
use App\Models\Accounting\Voucher;
use App\Models\ClientRaBill;
use App\Models\Party;
use App\Models\PurchaseBill;
use App\Models\SubcontractorRaBill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingYearEndCloseService
{
    public function __construct(
        protected AccountingFinancialYearService $financialYearService
    ) {
    }

    public function build(int $companyId): array
    {
        $currentFy = $this->financialYearService->currentRange();
        $previousFy = $this->financialYearService->previousRange();
        $nextFyStart = $currentFy['end']->copy()->addDay()->startOfDay();
        $trialBalance = $this->trialBalanceSummary($companyId, $previousFy['end']);

        $checklistRows = $this->buildChecklistRows($companyId, $previousFy, $trialBalance);
        $diagnosticRows = $this->buildDiagnosticRows($companyId, $currentFy);
        $issueLists = $this->buildIssueLists($companyId, $currentFy);
        $carryForwardPreview = [
            'non_party_rows' => $this->buildCarryForwardRows($companyId, false, false)->count(),
            'party_rows' => $this->buildCarryForwardRows($companyId, true, false)
                ->filter(fn (array $row) => ($row['is_party_ledger'] ?? false) === true)
                ->count(),
        ];

        $summary = [
            'critical' => collect([$checklistRows, $diagnosticRows])->flatten(1)->where('status', 'critical')->count(),
            'warning' => collect([$checklistRows, $diagnosticRows])->flatten(1)->where('status', 'warning')->count(),
            'ok' => collect([$checklistRows, $diagnosticRows])->flatten(1)->where('status', 'ok')->count(),
        ];

        return [
            'companyId' => $companyId,
            'currentFy' => $currentFy,
            'previousFy' => $previousFy,
            'nextFyStart' => $nextFyStart,
            'trialBalance' => $trialBalance,
            'sections' => [
                [
                    'title' => 'Close Checklist',
                    'description' => 'Core controls that should be green before locking the previous year.',
                    'rows' => $checklistRows,
                ],
                [
                    'title' => 'Carry Forward Diagnostics',
                    'description' => 'Opening-balance and carry-forward signals that usually need review before year close.',
                    'rows' => $diagnosticRows,
                ],
            ],
            'issueLists' => $issueLists,
            'carryForwardPreview' => $carryForwardPreview,
            'summary' => $summary,
        ];
    }

    public function buildCarryForwardRows(int $companyId, bool $includePartyLedgers = false, bool $includeZeroBalances = false): Collection
    {
        $previousFy = $this->financialYearService->previousRange();
        $nextFyStart = $previousFy['end']->copy()->addDay()->startOfDay();

        $accounts = Account::query()
            ->with('group')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        if (! $includePartyLedgers) {
            $accounts = $accounts->reject(fn (Account $account) => $account->related_model_type === Party::class)->values();
        }

        $movements = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereDate('v.voucher_date', '<=', $previousFy['end']->toDateString())
            ->whereIn('vl.account_id', $accounts->pluck('id'))
            ->selectRaw('vl.account_id, COALESCE(SUM(vl.debit),0) as total_debit, COALESCE(SUM(vl.credit),0) as total_credit')
            ->groupBy('vl.account_id')
            ->get()
            ->keyBy('account_id');

        return $accounts
            ->map(function (Account $account) use ($movements, $previousFy, $nextFyStart, $includeZeroBalances) {
                $groupNature = (string) ($account->group?->nature ?? '');
                if (in_array($groupNature, ['income', 'expense'], true)) {
                    return null;
                }

                $agg = $movements->get($account->id);
                $debit = $agg ? (float) $agg->total_debit : 0.0;
                $credit = $agg ? (float) $agg->total_credit : 0.0;

                $opening = (float) ($account->opening_balance ?? 0.0);
                if ($account->opening_balance_date && $account->opening_balance_date->gt($previousFy['end'])) {
                    $opening = 0.0;
                }
                if ($opening !== 0.0 && ($account->opening_balance_type ?? 'dr') === 'cr') {
                    $opening *= -1;
                }

                $net = round($opening + ($debit - $credit), 2);
                if (! $includeZeroBalances && abs($net) < 0.005) {
                    return null;
                }

                return [
                    'account_code' => (string) $account->code,
                    'account_name' => (string) $account->name,
                    'opening_balance' => number_format(abs($net), 2, '.', ''),
                    'dr_cr' => $net >= 0 ? 'dr' : 'cr',
                    'opening_date' => $nextFyStart->toDateString(),
                    'group_nature' => $groupNature,
                    'is_party_ledger' => $account->related_model_type === Party::class,
                ];
            })
            ->filter()
            ->values();
    }

    protected function buildChecklistRows(int $companyId, array $previousFy, array $trialBalance): array
    {
        $fullyLocked = AccountingPeriodLock::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('from_date', '<=', $previousFy['start']->toDateString())
            ->whereDate('to_date', '>=', $previousFy['end']->toDateString())
            ->exists();

        $draftVouchers = Voucher::query()
            ->where('company_id', $companyId)
            ->where('status', 'draft')
            ->count();

        $draftPurchaseBills = PurchaseBill::query()
            ->where('company_id', $companyId)
            ->where('status', 'draft')
            ->count();

        $approvedClientRa = ClientRaBill::query()
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->count();

        $approvedSubcontractorRa = SubcontractorRaBill::query()
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->count();

        return [
            [
                'label' => 'Previous FY trial balance difference',
                'status' => abs((float) $trialBalance['difference']) < 0.005 ? 'ok' : 'critical',
                'detail' => 'Difference = ' . number_format((float) $trialBalance['difference'], 2),
                'message' => abs((float) $trialBalance['difference']) < 0.005
                    ? 'Books are balanced at previous FY end.'
                    : 'Previous FY is not balanced. Resolve this before close.',
            ],
            [
                'label' => 'Previous FY fully locked',
                'status' => $fullyLocked ? 'ok' : 'warning',
                'detail' => 'FY ' . $previousFy['label'] . ' = '
                    . $previousFy['start']->format('d-m-Y') . ' to '
                    . $previousFy['end']->format('d-m-Y'),
                'message' => $fullyLocked
                    ? 'An active period lock covers the full previous financial year.'
                    : 'No active period lock covers the full previous financial year.',
            ],
            $this->metricRow('Draft manual vouchers', $draftVouchers, 'Review and post or cancel draft manual vouchers before close.'),
            $this->metricRow('Draft purchase bills', $draftPurchaseBills, 'Draft purchase bills are pending before year close.'),
            $this->metricRow('Approved client RA bills awaiting posting', $approvedClientRa, 'Approved client RA bills are still pending posting.'),
            $this->metricRow('Approved subcontractor RA bills awaiting posting', $approvedSubcontractorRa, 'Approved subcontractor RA bills are still pending posting.'),
        ];
    }

    protected function buildDiagnosticRows(int $companyId, array $currentFy): array
    {
        $missingDateCount = Account::query()
            ->where('company_id', $companyId)
            ->where('opening_balance', '!=', 0)
            ->whereNull('opening_balance_date')
            ->count();

        $insideFyCount = Account::query()
            ->where('company_id', $companyId)
            ->where('opening_balance', '!=', 0)
            ->whereNotNull('opening_balance_date')
            ->whereDate('opening_balance_date', '>=', $currentFy['start']->toDateString())
            ->whereDate('opening_balance_date', '<=', $currentFy['end']->toDateString())
            ->count();

        $partyOpeningCount = Account::query()
            ->where('company_id', $companyId)
            ->where('opening_balance', '!=', 0)
            ->where('related_model_type', \App\Models\Party::class)
            ->count();

        $preOpeningActivityCount = Account::query()
            ->where('company_id', $companyId)
            ->where('opening_balance', '!=', 0)
            ->whereNotNull('opening_balance_date')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('voucher_lines as vl')
                    ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
                    ->whereColumn('vl.account_id', 'accounts.id')
                    ->where('v.status', 'posted')
                    ->whereDate('v.voucher_date', '<', DB::raw('accounts.opening_balance_date'));
            })
            ->count();

        return [
            $this->metricRow('Opening balances without effective date', $missingDateCount, 'Set opening_balance_date so reports know when the opening becomes effective.'),
            $this->metricRow('Opening balances dated inside current FY', $insideFyCount, 'Review whether these are true carry-forward balances or mid-year adjustments.'),
            $this->metricRow('Party ledgers carrying opening balances', $partyOpeningCount, 'Review these carefully if bill-wise outstanding migration is also in use.'),
            $this->metricRow('Ledgers with voucher activity before opening date', $preOpeningActivityCount, 'This may indicate carry-forward dating issues or double counting.'),
        ];
    }

    protected function buildIssueLists(int $companyId, array $currentFy): array
    {
        return [
            'opening_without_date' => Account::query()
                ->where('company_id', $companyId)
                ->where('opening_balance', '!=', 0)
                ->whereNull('opening_balance_date')
                ->orderBy('name')
                ->limit(25)
                ->get(['id', 'code', 'name', 'opening_balance', 'opening_balance_type']),
            'opening_inside_current_fy' => Account::query()
                ->where('company_id', $companyId)
                ->where('opening_balance', '!=', 0)
                ->whereNotNull('opening_balance_date')
                ->whereDate('opening_balance_date', '>=', $currentFy['start']->toDateString())
                ->whereDate('opening_balance_date', '<=', $currentFy['end']->toDateString())
                ->orderBy('opening_balance_date')
                ->orderBy('name')
                ->limit(25)
                ->get(['id', 'code', 'name', 'opening_balance', 'opening_balance_type', 'opening_balance_date']),
            'party_opening_balances' => Account::query()
                ->where('company_id', $companyId)
                ->where('opening_balance', '!=', 0)
                ->where('related_model_type', \App\Models\Party::class)
                ->orderBy('name')
                ->limit(25)
                ->get(['id', 'code', 'name', 'opening_balance', 'opening_balance_type', 'opening_balance_date']),
            'pre_opening_activity' => Account::query()
                ->where('company_id', $companyId)
                ->where('opening_balance', '!=', 0)
                ->whereNotNull('opening_balance_date')
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('voucher_lines as vl')
                        ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
                        ->whereColumn('vl.account_id', 'accounts.id')
                        ->where('v.status', 'posted')
                        ->whereDate('v.voucher_date', '<', DB::raw('accounts.opening_balance_date'));
                })
                ->orderBy('name')
                ->limit(25)
                ->get(['id', 'code', 'name', 'opening_balance', 'opening_balance_type', 'opening_balance_date']),
        ];
    }

    protected function trialBalanceSummary(int $companyId, \Carbon\Carbon $asOfDate): array
    {
        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->get(['id', 'opening_balance', 'opening_balance_type', 'opening_balance_date'])
            ->keyBy('id');

        if ($accounts->isEmpty()) {
            return [
                'grand_debit' => 0.0,
                'grand_credit' => 0.0,
                'difference' => 0.0,
            ];
        }

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

        $grandDebit = 0.0;
        $grandCredit = 0.0;

        foreach ($accounts as $accountId => $account) {
            $agg = $movements->get($accountId);
            $debit = $agg ? (float) $agg->total_debit : 0.0;
            $credit = $agg ? (float) $agg->total_credit : 0.0;

            $opening = (float) ($account->opening_balance ?? 0.0);
            if ($account->opening_balance_date && $account->opening_balance_date > $asOfDate->toDateString()) {
                $opening = 0.0;
            }
            if ($opening !== 0.0 && ($account->opening_balance_type ?? 'dr') === 'cr') {
                $opening *= -1;
            }

            $net = $opening + ($debit - $credit);
            if (abs($net) < 0.005) {
                continue;
            }

            if ($net >= 0) {
                $grandDebit += $net;
            } else {
                $grandCredit += abs($net);
            }
        }

        return [
            'grand_debit' => round($grandDebit, 2),
            'grand_credit' => round($grandCredit, 2),
            'difference' => round($grandDebit - $grandCredit, 2),
        ];
    }

    protected function metricRow(string $label, int $count, string $message): array
    {
        return [
            'label' => $label,
            'status' => $count > 0 ? 'warning' : 'ok',
            'detail' => 'Count = ' . $count,
            'message' => $message,
        ];
    }
}
