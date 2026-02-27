<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\SalesCreditNote;
use App\Models\Party;
use App\Services\Accounting\BillAllocationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class ClientAgeingReportController extends Controller
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

    protected function bucketLabels(): array
    {
        return [
            'not_due'  => 'Not due',
            '0_30'     => '0-30 days',
            '31_60'    => '31-60 days',
            '61_90'    => '61-90 days',
            '91_180'   => '91-180 days',
            'over_180' => '> 180 days',
        ];
    }

    protected function emptyBuckets(): array
    {
        return [
            'not_due'  => 0.0,
            '0_30'     => 0.0,
            '31_60'    => 0.0,
            '61_90'    => 0.0,
            '91_180'   => 0.0,
            'over_180' => 0.0,
        ];
    }

    protected function classifyBucket(Carbon $asOfDate, ?Carbon $dueOrBillDate): array
    {
        if (! $dueOrBillDate) return ['bucket' => 'not_due', 'days' => 0];

        $days = (int) $dueOrBillDate->startOfDay()->diffInDays($asOfDate->copy()->startOfDay(), false);

        if ($days <= 0) return ['bucket' => 'not_due', 'days' => 0];
        if ($days <= 30) return ['bucket' => '0_30', 'days' => $days];
        if ($days <= 60) return ['bucket' => '31_60', 'days' => $days];
        if ($days <= 90) return ['bucket' => '61_90', 'days' => $days];
        if ($days <= 180) return ['bucket' => '91_180', 'days' => $days];
        return ['bucket' => 'over_180', 'days' => $days];
    }

    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $selectedClientId = $request->integer('client_id') ?: null;

        $asOfInput = (string) $request->input('as_of_date', '');
        $asOfDate  = $asOfInput !== '' ? Carbon::parse($asOfInput)->startOfDay() : now()->startOfDay();

        $status = (string) $request->input('status', 'posted');
        if ($status === '') $status = 'posted';

        $arModelClass = Config::get('accounting.ar_bill_model');
        $arEnabled    = is_string($arModelClass) && $arModelClass !== '' && class_exists($arModelClass);

        $clients = Party::where('is_client', true)->orderBy('name')->get();

        $accounts = Account::with('relatedModel')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', 'debtor')
            ->where('related_model_type', Party::class)
            ->orderBy('name')
            ->get();

        $cnEnabled = Schema::hasTable('sales_credit_notes');

        $cnTotalsByClientId = collect();
        if ($cnEnabled) {
            $cnTotalsByClientId = SalesCreditNote::query()
                ->where('company_id', $companyId)
                ->where('status', 'posted')
                ->whereDate('note_date', '<=', $asOfDate->toDateString())
                ->selectRaw('client_id, COALESCE(SUM(total_amount),0) as total')
                ->groupBy('client_id')
                ->pluck('total', 'client_id');
        }

        $summaryRows = [];
        $grand = $this->emptyBuckets();
        $grand['bill_total']  = 0.0;
        $grand['on_account']  = 0.0;
        $grand['credit_notes']= 0.0;
        $grand['net_total']   = 0.0;

        if ($arEnabled) {
            foreach ($accounts as $account) {
                /** @var Party|null $party */
                $party = $account->relatedModel;
                if (! $party || ! $party->is_client) continue;

                if ($selectedClientId && (int) $party->id !== $selectedClientId) continue;

                $openBills = $this->billAllocationService->getOpenClientBillsForAccount($account, $asOfDate, $status);
                $onAccount = (float) $this->billAllocationService->getOnAccountReceiptsAsOf($account, $asOfDate);
                $creditNotes = $cnEnabled ? (float) ($cnTotalsByClientId[$party->id] ?? 0.0) : 0.0;

                $buckets = $this->emptyBuckets();
                $totalOutstanding = 0.0;

                foreach ($openBills as $row) {
                    $bill = $row['bill'];

                    $due = $bill->due_date ?? null;
                    $billDate = $bill->bill_date ?? null;

                    $dueOrBill = null;
                    if ($due) {
                        $dueOrBill = $due instanceof Carbon ? $due : Carbon::parse($due);
                    } elseif ($billDate) {
                        $dueOrBill = $billDate instanceof Carbon ? $billDate : Carbon::parse($billDate);
                    }

                    $class = $this->classifyBucket($asOfDate, $dueOrBill);
                    $bucket = $class['bucket'];

                    $outstanding = (float) ($row['outstanding'] ?? 0.0);
                    $buckets[$bucket] += $outstanding;
                    $totalOutstanding += $outstanding;
                }

                if ($openBills->isEmpty() && abs($onAccount) < 0.01 && abs($creditNotes) < 0.01) {
                    continue;
                }

                $net = $totalOutstanding - $onAccount - $creditNotes;

                $summaryRows[] = [
                    'party'            => $party,
                    'account'          => $account,
                    'buckets'          => $buckets,
                    'total_outstanding'=> $totalOutstanding,
                    'on_account'       => $onAccount,
                    'credit_notes'     => $creditNotes,
                    'net_outstanding'  => $net,
                ];

                foreach ($this->emptyBuckets() as $k => $_) {
                    $grand[$k] += $buckets[$k];
                }
                $grand['bill_total'] += $totalOutstanding;
                $grand['on_account'] += $onAccount;
                $grand['credit_notes'] += $creditNotes;
                $grand['net_total']  += $net;
            }

            usort($summaryRows, fn($a, $b) => strcmp($a['party']->name, $b['party']->name));
        }

        return view('accounting.reports.client_ageing', [
            'companyId'        => $companyId,
            'clients'          => $clients,
            'selectedClientId' => $selectedClientId,
            'asOfDate'         => $asOfDate,
            'status'           => $status,
            'summaryRows'      => $summaryRows,
            'grand'            => $grand,
            'arEnabled'        => $arEnabled,
            'cnEnabled'        => $cnEnabled,
        ]);
    }

    public function bills(Request $request, Account $account)
    {
        $companyId = $this->defaultCompanyId();

        if ((int) $account->company_id !== $companyId) abort(404);

        $asOfInput = (string) $request->input('as_of_date', '');
        $asOfDate  = $asOfInput !== '' ? Carbon::parse($asOfInput)->startOfDay() : now()->startOfDay();

        $status = (string) $request->input('status', 'posted');
        if ($status === '') $status = 'posted';

        /** @var Party|null $party */
        $party = $account->relatedModel;
        if (! $party || ! $party->is_client) abort(404);

        $rows = [];
        $bucketLabels = $this->bucketLabels();
        $grandTotals = [
            'bill_amount'  => 0.0,
            'allocated'    => 0.0,
            'outstanding'  => 0.0,
        ];

        $openBills = $this->billAllocationService->getOpenClientBillsForAccount($account, $asOfDate, $status);

        foreach ($openBills as $row) {
            $bill = $row['bill'];

            $due = $bill->due_date ?? null;
            $billDate = $bill->bill_date ?? null;

            $dueOrBill = null;
            if ($due) {
                $dueOrBill = $due instanceof Carbon ? $due : Carbon::parse($due);
            } elseif ($billDate) {
                $dueOrBill = $billDate instanceof Carbon ? $billDate : Carbon::parse($billDate);
            }

            $class = $this->classifyBucket($asOfDate, $dueOrBill);

            $billAmount  = (float) ($row['bill_amount'] ?? $this->billAllocationService->getClientBillAmount($bill));
            $allocated   = (float) ($row['allocated'] ?? 0.0);
            $outstanding = (float) ($row['outstanding'] ?? 0.0);

            $number = $bill->bill_number ?? $bill->invoice_number ?? ('#' . $bill->id);

            $billDateVal = $bill->bill_date ? ( $bill->bill_date instanceof Carbon ? $bill->bill_date : Carbon::parse($bill->bill_date) ) : null;
            $dueDateVal  = $bill->due_date ? ( $bill->due_date instanceof Carbon ? $bill->due_date : Carbon::parse($bill->due_date) ) : null;

            $rows[] = [
                'bill'        => $bill,
                'bill_number' => $number,
                'bill_date'   => $billDateVal,
                'due_date'    => $dueDateVal,
                'bill_amount' => $billAmount,
                'allocated'   => $allocated,
                'outstanding' => $outstanding,
                'days'        => (int) $class['days'],
                'bucket'      => $class['bucket'],
            ];

            $grandTotals['bill_amount'] += $billAmount;
            $grandTotals['allocated']   += $allocated;
            $grandTotals['outstanding'] += $outstanding;
        }

        $onAccount = (float) $this->billAllocationService->getOnAccountReceiptsAsOf($account, $asOfDate);

        $cnEnabled = Schema::hasTable('sales_credit_notes');
        $creditNotes = collect();
        $cnTotal = 0.0;

        if ($cnEnabled) {
            $creditNotes = SalesCreditNote::query()
                ->with('voucher')
                ->where('company_id', $companyId)
                ->where('client_id', $party->id)
                ->where('status', 'posted')
                ->whereDate('note_date', '<=', $asOfDate->toDateString())
                ->orderBy('note_date')
                ->orderBy('id')
                ->get();

            $cnTotal = (float) $creditNotes->sum('total_amount');
        }

        $net = $grandTotals['outstanding'] - $onAccount - $cnTotal;

        return view('accounting.reports.client_ageing_detail', [
            'companyId'     => $companyId,
            'party'         => $party,
            'account'       => $account,
            'asOfDate'      => $asOfDate,
            'status'        => $status,
            'rows'          => $rows,
            'bucketLabels'  => $bucketLabels,
            'grandTotals'   => $grandTotals,
            'onAccount'     => $onAccount,
            'creditNotes'   => $creditNotes,
            'cnTotal'       => $cnTotal,
            'netOutstanding'=> $net,
            'cnEnabled'     => $cnEnabled,
        ]);
    }
}
