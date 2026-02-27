<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\PurchaseDebitNote;
use App\Models\Party;
use App\Services\Accounting\BillAllocationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class SupplierAgeingReportController extends Controller
{
    public function __construct(
        protected BillAllocationService $billAllocationService
    ) {
        $this->middleware('permission:accounting.reports.view')
            ->only(['index', 'bills']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    public function index(Request $request)
    {
        $companyId  = $this->defaultCompanyId();
        $supplierId = $request->integer('supplier_id') ?: null;

        $asOfDate = $request->date('as_of_date') ?: now();
        $asOfDate = Carbon::parse($asOfDate)->startOfDay();

        $suppliers = Party::where('is_supplier', true)
            ->orderBy('name')
            ->get();

        $accounts = Account::with('relatedModel')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', 'creditor')
            ->whereNotNull('related_model_type')
            ->where('related_model_type', Party::class)
            ->orderBy('name')
            ->get();

        $dnEnabled = Schema::hasTable('purchase_debit_notes');

        // Debit notes per supplier as-of date
        $dnTotalsBySupplierId = collect();
        if ($dnEnabled) {
            $dnQuery = PurchaseDebitNote::query()
                ->where('company_id', $companyId)
                ->where('status', 'posted')
                ->whereDate('note_date', '<=', $asOfDate->toDateString());

            if ($supplierId) {
                $dnQuery->where('supplier_id', $supplierId);
            }

            $dnTotalsBySupplierId = $dnQuery
                ->selectRaw('supplier_id, COALESCE(SUM(total_amount),0) as total')
                ->groupBy('supplier_id')
                ->pluck('total', 'supplier_id');
        }

        $summaryRows = [];

        foreach ($accounts as $account) {
            /** @var Party|null $party */
            $party = $account->relatedModel;
            if (! $party || ! $party->is_supplier) {
                continue;
            }

            if ($supplierId && (int) $party->id !== $supplierId) {
                continue;
            }

            $openBills = $this->billAllocationService->getOpenPurchaseBillsForAccount($account, $asOfDate);

            if ($openBills->isEmpty() && (! $dnEnabled || (float)($dnTotalsBySupplierId[$party->id] ?? 0) <= 0.009)) {
                continue;
            }

            $buckets = [
                'not_due'  => 0.0,
                '0_30'     => 0.0,
                '31_60'    => 0.0,
                '61_90'    => 0.0,
                '91_180'   => 0.0,
                'over_180' => 0.0,
            ];

            $totalOutstanding = 0.0;

            foreach ($openBills as $row) {
                $bill        = $row['bill'];
                $outstanding = (float) ($row['outstanding'] ?? 0);

                if ($outstanding <= 0.0) {
                    continue;
                }

                $baseDate = $bill->due_date ?? $bill->bill_date;
                $days     = $this->daysOverdue($baseDate, $asOfDate);

                $bucketKey = $this->bucketKey($days);
                $buckets[$bucketKey] += $outstanding;
                $totalOutstanding    += $outstanding;
            }

            $dnTotal = $dnEnabled ? (float) ($dnTotalsBySupplierId[$party->id] ?? 0.0) : 0.0;
            $net = $totalOutstanding - $dnTotal;

            $summaryRows[] = [
                'party'             => $party,
                'account'           => $account,
                'buckets'           => $buckets,
                'total_outstanding' => $totalOutstanding,
                'debit_notes'       => $dnTotal,
                'net_outstanding'   => $net,
            ];
        }

        usort($summaryRows, fn($a, $b) => strcmp($a['party']->name, $b['party']->name));

        $grand = [
            'not_due'  => 0.0,
            '0_30'     => 0.0,
            '31_60'    => 0.0,
            '61_90'    => 0.0,
            '91_180'   => 0.0,
            'over_180' => 0.0,
            'total'    => 0.0,
            'debit_notes' => 0.0,
            'net_total' => 0.0,
        ];

        foreach ($summaryRows as $row) {
            foreach ($row['buckets'] as $key => $value) {
                $grand[$key] += $value;
            }
            $grand['total'] += $row['total_outstanding'];
            $grand['debit_notes'] += $row['debit_notes'];
            $grand['net_total'] += $row['net_outstanding'];
        }

        return view('accounting.reports.supplier_ageing', [
            'companyId'          => $companyId,
            'suppliers'          => $suppliers,
            'summaryRows'        => $summaryRows,
            'asOfDate'           => $asOfDate,
            'grand'              => $grand,
            'selectedSupplierId' => $supplierId,
            'dnEnabled'          => $dnEnabled,
        ]);
    }

    public function bills(Request $request, int $accountId)
    {
        $companyId = $this->defaultCompanyId();
        $asOfDate  = $request->date('as_of_date') ?: now();
        $asOfDate  = Carbon::parse($asOfDate)->startOfDay();

        $account = Account::with('relatedModel')
            ->where('company_id', $companyId)
            ->where('id', $accountId)
            ->where('is_active', true)
            ->where('type', 'creditor')
            ->firstOrFail();

        /** @var Party|null $party */
        $party = $account->relatedModel;
        if (! $party || ! $party->is_supplier) {
            abort(404, 'Supplier ledger not found.');
        }

        $openBills = $this->billAllocationService->getOpenPurchaseBillsForAccount($account, $asOfDate);

        $rows = [];
        $grandTotals = [
            'bill_amount' => 0.0,
            'allocated'   => 0.0,
            'outstanding' => 0.0,
        ];

        foreach ($openBills as $row) {
            $bill        = $row['bill'];
            $allocated   = (float) ($row['allocated'] ?? 0);
            $outstanding = (float) ($row['outstanding'] ?? 0);

            if ($outstanding <= 0.0) {
                continue;
            }

            $billAmount = (float) ($row['bill_amount'] ?? $bill->total_amount);
            $baseDate   = $bill->due_date ?? $bill->bill_date;
            $days       = $this->daysOverdue($baseDate, $asOfDate);
            $bucketKey  = $this->bucketKey($days);

            $rows[] = [
                'bill'        => $bill,
                'bill_number' => $bill->bill_number,
                'bill_date'   => $bill->bill_date,
                'due_date'    => $bill->due_date,
                'bill_amount' => $billAmount,
                'allocated'   => $allocated,
                'outstanding' => $outstanding,
                'days'        => $days,
                'bucket'      => $bucketKey,
            ];

            $grandTotals['bill_amount'] += $billAmount;
            $grandTotals['allocated']   += $allocated;
            $grandTotals['outstanding'] += $outstanding;
        }

        $dnEnabled = Schema::hasTable('purchase_debit_notes');
        $debitNotes = collect();
        $dnTotal = 0.0;

        if ($dnEnabled) {
            $debitNotes = PurchaseDebitNote::query()
                ->with('voucher')
                ->where('company_id', $companyId)
                ->where('supplier_id', $party->id)
                ->where('status', 'posted')
                ->whereDate('note_date', '<=', $asOfDate->toDateString())
                ->orderBy('note_date')
                ->orderBy('id')
                ->get();

            $dnTotal = (float) $debitNotes->sum('total_amount');
        }

        $bucketLabels = [
            'not_due'  => 'Not due',
            '0_30'     => '0 - 30 days',
            '31_60'    => '31 - 60 days',
            '61_90'    => '61 - 90 days',
            '91_180'   => '91 - 180 days',
            'over_180' => '> 180 days',
        ];

        $netOutstanding = $grandTotals['outstanding'] - $dnTotal;

        return view('accounting.reports.supplier_ageing_detail', [
            'companyId'      => $companyId,
            'party'          => $party,
            'account'        => $account,
            'rows'           => $rows,
            'asOfDate'       => $asOfDate,
            'grandTotals'    => $grandTotals,
            'bucketLabels'   => $bucketLabels,
            'debitNotes'     => $debitNotes,
            'dnTotal'        => $dnTotal,
            'netOutstanding' => $netOutstanding,
            'dnEnabled'      => $dnEnabled,
        ]);
    }

    protected function daysOverdue($baseDate, Carbon $asOfDate): int
    {
        if (! $baseDate) {
            return 0;
        }

        return $baseDate->diffInDays($asOfDate, false);
    }

    protected function bucketKey(int $days): string
    {
        if ($days <= 0) return 'not_due';
        if ($days <= 30) return '0_30';
        if ($days <= 60) return '31_60';
        if ($days <= 90) return '61_90';
        if ($days <= 180) return '91_180';
        return 'over_180';
    }
}