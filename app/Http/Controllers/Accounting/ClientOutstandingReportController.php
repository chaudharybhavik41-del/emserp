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

class ClientOutstandingReportController extends Controller
{
    public function __construct(
        protected BillAllocationService $billAllocationService
    ) {
        $this->middleware('permission:accounting.reports.view')
            ->only(['index']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $clientId  = $request->integer('client_id') ?: null;

        $asOfInput = (string) $request->input('as_of_date', '');
        $asOfDate  = $asOfInput !== '' ? Carbon::parse($asOfInput)->startOfDay() : now()->startOfDay();

        $status = (string) $request->input('status', 'posted');
        if ($status === '') $status = 'posted';

        $arModelClass = Config::get('accounting.ar_bill_model');
        $arEnabled    = is_string($arModelClass) && $arModelClass !== '' && class_exists($arModelClass);

        $clients = Party::where('is_client', true)
            ->orderBy('name')
            ->get();

        $accounts = Account::with('relatedModel')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', 'debtor')
            ->where('related_model_type', Party::class)
            ->orderBy('name')
            ->get();

        $cnEnabled = Schema::hasTable('sales_credit_notes');

        // Credit notes totals per client as-of date
        $cnTotalsByClientId = collect();
        if ($cnEnabled) {
            $cnQuery = SalesCreditNote::query()
                ->where('company_id', $companyId)
                ->where('status', 'posted')
                ->whereDate('note_date', '<=', $asOfDate->toDateString());

            if ($clientId) {
                $cnQuery->where('client_id', $clientId);
            }

            $cnTotalsByClientId = $cnQuery
                ->selectRaw('client_id, COALESCE(SUM(total_amount),0) as total')
                ->groupBy('client_id')
                ->pluck('total', 'client_id');
        }

        $summaryRows = [];

        if ($arEnabled) {
            foreach ($accounts as $account) {
                /** @var Party|null $party */
                $party = $account->relatedModel;
                if (! $party || ! $party->is_client) continue;

                if ($clientId && (int) $party->id !== $clientId) continue;

                $openBills = $this->billAllocationService->getOpenClientBillsForAccount($account, $asOfDate, $status);
                $onAccount = (float) $this->billAllocationService->getOnAccountReceiptsAsOf($account, $asOfDate);

                $totalBillAmount   = 0.0;
                $totalAllocated    = 0.0;
                $totalOutstanding  = 0.0;

                foreach ($openBills as $row) {
                    $totalBillAmount  += (float) ($row['bill_amount'] ?? 0.0);
                    $totalAllocated   += (float) ($row['allocated'] ?? 0.0);
                    $totalOutstanding += (float) ($row['outstanding'] ?? 0.0);
                }

                $creditNotes = $cnEnabled ? (float) ($cnTotalsByClientId[$party->id] ?? 0.0) : 0.0;

                // Net receivable = bills outstanding - on-account receipts - credit notes
                $netOutstanding = $totalOutstanding - (float) $onAccount - $creditNotes;

                if ($openBills->isEmpty() && abs($onAccount) < 0.01 && abs($creditNotes) < 0.01) {
                    continue;
                }

                $summaryRows[] = [
                    'party'           => $party,
                    'account'         => $account,
                    'bill_amount'     => $totalBillAmount,
                    'allocated'       => $totalAllocated,
                    'bill_outstanding'=> $totalOutstanding,
                    'on_account'      => (float) $onAccount,
                    'credit_notes'    => $creditNotes,
                    'net_outstanding' => $netOutstanding,
                ];
            }

            usort($summaryRows, fn($a, $b) => strcmp($a['party']->name, $b['party']->name));
        }

        $selectedClient  = null;
        $selectedAccount = null;
        $detailBills     = collect();
        $detailOnAccount = 0.0;
        $detailCreditNotes = collect();
        $detailCnTotal   = 0.0;
        $detailNet       = 0.0;

        if ($clientId && $arEnabled) {
            $selectedClient = $clients->firstWhere('id', $clientId);

            if ($selectedClient) {
                $selectedAccount = $accounts->firstWhere('related_model_id', $selectedClient->id);

                if ($selectedAccount) {
                    $detailBills = $this->billAllocationService->getOpenClientBillsForAccount($selectedAccount, $asOfDate, $status);
                    $detailOnAccount = (float) $this->billAllocationService->getOnAccountReceiptsAsOf($selectedAccount, $asOfDate);

                    $billOutstanding = 0.0;
                    foreach ($detailBills as $row) {
                        $billOutstanding += (float) ($row['outstanding'] ?? 0.0);
                    }

                    if ($cnEnabled) {
                        $detailCreditNotes = SalesCreditNote::query()
                            ->with('voucher')
                            ->where('company_id', $companyId)
                            ->where('client_id', $selectedClient->id)
                            ->where('status', 'posted')
                            ->whereDate('note_date', '<=', $asOfDate->toDateString())
                            ->orderBy('note_date')
                            ->orderBy('id')
                            ->get();

                        $detailCnTotal = (float) $detailCreditNotes->sum('total_amount');
                    }

                    $detailNet = $billOutstanding - $detailOnAccount - $detailCnTotal;
                }
            }
        }

        $grandTotalBill        = array_sum(array_column($summaryRows, 'bill_amount'));
        $grandTotalAlloc       = array_sum(array_column($summaryRows, 'allocated'));
        $grandTotalBillOutstd  = array_sum(array_column($summaryRows, 'bill_outstanding'));
        $grandTotalOnAccount   = array_sum(array_column($summaryRows, 'on_account'));
        $grandTotalCn          = array_sum(array_column($summaryRows, 'credit_notes'));
        $grandTotalNetOutstd   = array_sum(array_column($summaryRows, 'net_outstanding'));

        return view('accounting.reports.client_outstanding', [
            'companyId'             => $companyId,
            'clients'               => $clients,
            'summaryRows'           => $summaryRows,
            'selectedClient'        => $selectedClient,
            'selectedAccount'       => $selectedAccount,
            'detailBills'           => $detailBills,
            'detailOnAccount'       => $detailOnAccount,
            'detailCreditNotes'     => $detailCreditNotes,
            'detailCnTotal'         => $detailCnTotal,
            'detailNet'             => $detailNet,
            'asOfDate'              => $asOfDate,
            'status'                => $status,
            'grandTotalBill'        => $grandTotalBill,
            'grandTotalAlloc'       => $grandTotalAlloc,
            'grandTotalBillOutstd'  => $grandTotalBillOutstd,
            'grandTotalOnAccount'   => $grandTotalOnAccount,
            'grandTotalCn'          => $grandTotalCn,
            'grandTotalNetOutstd'   => $grandTotalNetOutstd,
            'arEnabled'             => $arEnabled,
            'cnEnabled'             => $cnEnabled,
        ]);
    }
}
