<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\PurchaseDebitNote;
use App\Models\Party;
use App\Services\Accounting\BillAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SupplierOutstandingReportController extends Controller
{
    public function __construct(
        protected BillAllocationService $billAllocationService
    ) {
        $this->middleware('permission:accounting.reports.view')->only(['index']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    public function index(Request $request)
    {
        $companyId  = $this->defaultCompanyId();
        $supplierId = $request->integer('supplier_id') ?: null;

        $asOfDateInput = $request->input('as_of_date');
        $asOfDate = $asOfDateInput ? Carbon::parse($asOfDateInput)->endOfDay() : now()->endOfDay();

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

        // Debit Notes totals per supplier (posted up to As-of)
        $dnTotalsBySupplierId = collect();
        $dnEnabled = Schema::hasTable('purchase_debit_notes');

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

            // Open purchase bills (already centrally filtered to posted in BillAllocationService)
            $openBills = $this->billAllocationService->getOpenPurchaseBillsForAccount($account, $asOfDate);

            $totalBillAmount   = 0.0;
            $totalAllocated    = 0.0;
            $totalOutstanding  = 0.0;

            foreach ($openBills as $row) {
                $totalBillAmount  += (float) ($row['bill_amount'] ?? 0.0);
                $totalAllocated   += (float) ($row['allocated'] ?? 0.0);
                $totalOutstanding += (float) ($row['outstanding'] ?? 0.0);
            }

            $debitNotes = $dnEnabled ? (float) ($dnTotalsBySupplierId[(int) $party->id] ?? 0.0) : 0.0;

            // Net payable reduces by debit notes (supplier credit)
            $netOutstanding = $totalOutstanding - $debitNotes;

            // Include row if there are open bills OR debit notes exist
            if ($openBills->isEmpty() && abs($debitNotes) < 0.01) {
                continue;
            }

            $summaryRows[] = [
                'party'          => $party,
                'account'        => $account,
                'bill_amount'    => $totalBillAmount,
                'allocated'      => $totalAllocated,
                'outstanding'    => $totalOutstanding,
                'debit_notes'    => $debitNotes,
                'net_outstanding'=> $netOutstanding,
            ];
        }

        usort($summaryRows, fn($a, $b) => strcmp($a['party']->name, $b['party']->name));

        // Detail for selected supplier
        $selectedParty   = null;
        $selectedAccount = null;
        $detailBills     = collect();
        $detailDebitNotes = collect();
        $detailDnTotal   = 0.0;

        if ($supplierId) {
            $selectedParty = $suppliers->firstWhere('id', $supplierId);

            if ($selectedParty) {
                $selectedAccount = $accounts->firstWhere('related_model_id', $selectedParty->id);

                if ($selectedAccount) {
                    $detailBills = $this->billAllocationService->getOpenPurchaseBillsForAccount($selectedAccount, $asOfDate);
                }

                if ($dnEnabled) {
                    $detailDebitNotes = PurchaseDebitNote::query()
                        ->with('voucher')
                        ->where('company_id', $companyId)
                        ->where('supplier_id', $selectedParty->id)
                        ->where('status', 'posted')
                        ->whereDate('note_date', '<=', $asOfDate->toDateString())
                        ->orderBy('note_date')
                        ->orderBy('id')
                        ->get();

                    $detailDnTotal = (float) $detailDebitNotes->sum('total_amount');
                }
            }
        }

        $grandTotalBill   = array_sum(array_column($summaryRows, 'bill_amount'));
        $grandTotalAlloc  = array_sum(array_column($summaryRows, 'allocated'));
        $grandTotalOutstd = array_sum(array_column($summaryRows, 'outstanding'));
        $grandTotalDn     = array_sum(array_column($summaryRows, 'debit_notes'));
        $grandTotalNet    = array_sum(array_column($summaryRows, 'net_outstanding'));

        return view('accounting.reports.supplier_outstanding', [
            'companyId'          => $companyId,
            'suppliers'          => $suppliers,
            'summaryRows'        => $summaryRows,
            'selectedParty'      => $selectedParty,
            'selectedAccount'    => $selectedAccount,
            'detailBills'        => $detailBills,
            'detailDebitNotes'   => $detailDebitNotes,
            'detailDnTotal'      => $detailDnTotal,
            'asOfDate'           => $asOfDate,
            'grandTotalBill'     => $grandTotalBill,
            'grandTotalAlloc'    => $grandTotalAlloc,
            'grandTotalOutstd'   => $grandTotalOutstd,
            'grandTotalDn'       => $grandTotalDn,
            'grandTotalNet'      => $grandTotalNet,
            'dnEnabled'          => $dnEnabled,
        ]);
    }
}