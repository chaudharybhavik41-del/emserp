<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\Accounting\TdsSection;
use App\Models\Accounting\TdsCertificate;
use App\Models\Project;
use App\Services\Accounting\BillAllocationService;
use App\Services\Accounting\VoucherNumberService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class BankCashVoucherController extends Controller
{
    public function __construct(
        protected BillAllocationService $billAllocationService,
        protected VoucherNumberService $voucherNumberService
    ) {
        $this->middleware('permission:accounting.vouchers.create')
            ->only(['createPayment', 'storePayment', 'createReceipt', 'storeReceipt']);

        $this->middleware('permission:accounting.vouchers.view')
            ->only(['openPurchaseBills', 'openClientBills']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    /**
     * Bank & Cash ledgers for current company.
     */
    protected function bankCashAccounts(): \Illuminate\Support\Collection
    {
        $companyId = $this->defaultCompanyId();

        return Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('type', ['bank', 'cash'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Counterparty ledgers (all non-bank/cash accounts).
     */
    protected function counterpartyAccounts(): \Illuminate\Support\Collection
    {
        $companyId = $this->defaultCompanyId();

        return Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotIn('type', ['bank', 'cash'])
            ->orderBy('name')
            ->get();
    }

    // ---------------------------------------------------------------------
    // Payment
    // ---------------------------------------------------------------------

    public function createPayment()
    {
        $companyId            = $this->defaultCompanyId();
        $bankCashAccounts     = $this->bankCashAccounts();
        $counterpartyAccounts = $this->counterpartyAccounts();
        $projects             = Project::orderBy('name')->get();
        $costCenters          = CostCenter::orderBy('name')->get();
        $tdsSections          = TdsSection::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('accounting.vouchers.payment', compact(
            'companyId',
            'bankCashAccounts',
            'counterpartyAccounts',
            'projects',
            'costCenters',
            'tdsSections'
        ));
    }

    public function storePayment(Request $request)
    {
        $companyId = (int) $request->input('company_id', $this->defaultCompanyId());

        $rules = [
            'company_id'       => ['required', 'integer'],
            'voucher_date'     => ['required', 'date'],
            'bank_account_id'  => ['required', 'integer', 'exists:accounts,id'],
            'party_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:bank_account_id'],
            'amount'           => ['required', 'numeric', 'gt:0'],
            'narration'        => ['nullable', 'string', 'max:500'],
            'reference'        => ['nullable', 'string', 'max:100'],
            'project_id'       => ['nullable', 'integer', 'exists:projects,id'],
            'cost_center_id'   => ['nullable', 'integer', 'exists:cost_centers,id'],

            'purchase_allocations'               => ['nullable', 'array'],
            'purchase_allocations.*.bill_id'     => ['nullable', 'integer', 'exists:purchase_bills,id'],
            'purchase_allocations.*.amount'      => ['nullable', 'numeric', 'min:0'],
        ];

        $data = $request->validate($rules);

        $bankAccount = Account::where('id', $data['bank_account_id'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('type', ['bank', 'cash'])
            ->firstOrFail();

        $partyAccount = Account::where('id', $data['party_account_id'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->firstOrFail();

        try {
            $allocRows = $this->billAllocationService->validatePurchasePaymentAllocations(
                $partyAccount,
                (float) $data['amount'],
                $data['purchase_allocations'] ?? []
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        DB::transaction(function () use ($companyId, $data, $bankAccount, $partyAccount, $allocRows) {
            $voucher               = new Voucher();
            $voucher->company_id   = $companyId;
            // Centralised voucher numbering (Phase 5a)
            $voucher->voucher_no   = $this->voucherNumberService->next('payment', $companyId, $data['voucher_date']);
            $voucher->voucher_type = 'payment';
            $voucher->voucher_date = $data['voucher_date'];
            $voucher->reference    = $data['reference'] ?? null;
            $voucher->narration    = $data['narration'] ?? ('Payment - ' . $partyAccount->name);
            $voucher->project_id   = $data['project_id'] ?? null;
            $voucher->cost_center_id = $data['cost_center_id'] ?? null;
            $voucher->currency_id  = null;
            $voucher->exchange_rate = 1;
            $voucher->amount_base  = $data['amount'];
            // IMPORTANT (Phase 5b): create voucher as DRAFT, insert lines, then POST.
            // This allows the Voucher model guardrail to validate Dr == Cr at posting time.
            $voucher->status       = 'draft';
            $voucher->created_by   = auth()->id();
            $voucher->save();

            $lineNo = 1;

            // Dr Party / Expense
            $partyLine = VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => $lineNo++,
                'account_id'     => $partyAccount->id,
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'description'    => $data['narration'] ?? ('Payment - ' . $partyAccount->name),
                'debit'          => $data['amount'],
                'credit'         => 0,
                'reference_type' => null,
                'reference_id'   => null,
            ]);

            // Cr Bank / Cash
            VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => $lineNo++,
                'account_id'     => $bankAccount->id,
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'description'    => $data['narration'] ?? ('Payment - ' . $bankAccount->name),
                'debit'          => 0,
                'credit'         => $data['amount'],
                'reference_type' => null,
                'reference_id'   => null,
            ]);

            if (! empty($allocRows)) {
                $this->billAllocationService->storePurchaseAllocationsForPayment($partyLine, $allocRows);
            }

            // Finalize posting (validates balance + normalizes amount_base)
            $voucher->posted_by = auth()->id();
            $voucher->posted_at = now();
            $voucher->status    = 'posted';
            $voucher->save();
        });

        return redirect()
            ->route('accounting.vouchers.index')
            ->with('success', 'Payment voucher created successfully.');
    }

    // ---------------------------------------------------------------------
    // Receipt
    // ---------------------------------------------------------------------

    public function createReceipt()
    {
        $companyId            = $this->defaultCompanyId();
        $bankCashAccounts     = $this->bankCashAccounts();
        $counterpartyAccounts = $this->counterpartyAccounts();
        $projects             = Project::orderBy('name')->get();
        $costCenters          = CostCenter::orderBy('name')->get();
        $tdsSections          = TdsSection::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('accounting.vouchers.receipt', compact(
            'companyId',
            'bankCashAccounts',
            'counterpartyAccounts',
            'projects',
            'costCenters',
            'tdsSections'
        ));
    }

    public function storeReceipt(Request $request)
    {
        $companyId = (int) $request->input('company_id', $this->defaultCompanyId());

        $rules = [
            'company_id'       => ['required', 'integer'],
            'voucher_date'     => ['required', 'date'],
            'bank_account_id'  => ['required', 'integer', 'exists:accounts,id'],
            'party_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:bank_account_id'],
            'amount'           => ['required', 'numeric', 'gt:0'],
            'narration'        => ['nullable', 'string', 'max:500'],
            'reference'        => ['nullable', 'string', 'max:100'],
            'project_id'       => ['nullable', 'integer', 'exists:projects,id'],
            'cost_center_id'   => ['nullable', 'integer', 'exists:cost_centers,id'],

            'tds_section'      => ['nullable', 'string', 'max:20', Rule::exists('tds_sections', 'code')->where(fn ($q) => $q->where('company_id', $companyId)->where('is_active', true))],
            'tds_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tds_certificate_no'   => ['nullable', 'string', 'max:100'],
            'tds_certificate_date' => ['nullable', 'date'],

            'receipt_allocations'               => ['nullable', 'array'],
            'receipt_allocations.*.bill_id'     => ['nullable', 'integer'],
            'receipt_allocations.*.amount'      => ['nullable', 'numeric', 'min:0'],
        ];

        $data = $request->validate($rules);

        $bankAccount = Account::where('id', $data['bank_account_id'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('type', ['bank', 'cash'])
            ->firstOrFail();

        $partyAccount = Account::where('id', $data['party_account_id'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->firstOrFail();

        $allocRows = $this->billAllocationService->validateClientReceiptAllocations(
            $partyAccount,
            (float) $data['amount'],
            $data['receipt_allocations'] ?? [],
            Carbon::parse((string) $data['voucher_date'])->startOfDay(),
            'posted'
        );

        DB::transaction(function () use ($companyId, $data, $bankAccount, $partyAccount, $allocRows) {
            $voucher               = new Voucher();
            $voucher->company_id   = $companyId;
            // Centralised voucher numbering (Phase 5a)
            $voucher->voucher_no   = $this->voucherNumberService->next('receipt', $companyId, $data['voucher_date']);
            $voucher->voucher_type = 'receipt';
            $voucher->voucher_date = $data['voucher_date'];
            $voucher->reference    = $data['reference'] ?? null;
            $voucher->narration    = $data['narration'] ?? ('Receipt - ' . $partyAccount->name);
            $voucher->project_id   = $data['project_id'] ?? null;
            $voucher->cost_center_id = $data['cost_center_id'] ?? null;
            $voucher->currency_id  = null;
            $voucher->exchange_rate = 1;
            $voucher->amount_base  = $data['amount'];
            // IMPORTANT (Phase 5b): create voucher as DRAFT, insert lines, then POST.
            // This allows the Voucher model guardrail to validate Dr == Cr at posting time.
            $voucher->status       = 'draft';
            $voucher->created_by   = auth()->id();
            $voucher->save();

            $lineNo = 1;

            // Dr Bank / Cash
            VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => $lineNo++,
                'account_id'     => $bankAccount->id,
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'description'    => $data['narration'] ?? ('Receipt - ' . $bankAccount->name),
                'debit'          => $data['amount'],
                'credit'         => 0,
                'reference_type' => null,
                'reference_id'   => null,
            ]);

            // Cr Party / Debtor
            $clientLine = VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => $lineNo++,
                'account_id'     => $partyAccount->id,
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'description'    => $data['narration'] ?? ('Receipt - ' . $partyAccount->name),
                'debit'          => 0,
                'credit'         => $data['amount'],
                'reference_type' => null,
                'reference_id'   => null,
            ]);

            if (! empty($allocRows)) {
                $this->billAllocationService->storeClientAllocationsForReceipt($clientLine, $allocRows);
            }

            // Phase 7: Store any leftover receipt amount as On-Account (unallocated)
            $allocatedTotal = 0.0;
            foreach ($allocRows as $r) {
                $allocatedTotal += (float) ($r['amount'] ?? 0.0);
            }
            $leftover = (float) $data['amount'] - $allocatedTotal;
            if ($leftover > 0.009) {
                $this->billAllocationService->storeOnAccountForReceipt($clientLine, $leftover);
            }

            // Finalize posting (validates balance + normalizes amount_base)
            $voucher->posted_by = auth()->id();
            $voucher->posted_at = now();
            $voucher->status    = 'posted';
            $voucher->save();

            // Phase 1.6: TDS (Receivable) Certificate tracking for Client receipts
            // NOTE: Accounting for TDS receivable is posted at Client RA Bill posting (SalesPostingService).
            // Here we only track the expected TDS amount proportionate to allocated bills and store certificate details.
            $tdsInfo = $this->computeTdsReceivableFromReceiptAllocations($allocRows);
            $expectedTds = (float) ($tdsInfo['tds_amount'] ?? 0.0);

            if ($expectedTds > 0.009) {
                $section = $data['tds_section'] ?? ($tdsInfo['tds_section'] ?? null);
                $rate    = $data['tds_rate'] ?? ($tdsInfo['tds_rate'] ?? null);

                if ((! $rate || (float) $rate <= 0) && $section) {
                    $secRow = TdsSection::where('company_id', $companyId)->where('code', $section)->first();
                    if ($secRow) {
                        $rate = (float) $secRow->default_rate;
                    }
                }

                TdsCertificate::create([
                    'company_id'       => $companyId,
                    'direction'        => 'receivable',
                    'voucher_id'       => $voucher->id,
                    'party_account_id' => $partyAccount->id,
                    'tds_section'      => $section,
                    'tds_rate'         => $rate,
                    'tds_amount'       => round($expectedTds, 2),
                    'certificate_no'   => $data['tds_certificate_no'] ?? null,
                    'certificate_date' => $data['tds_certificate_date'] ?? null,
                    'remarks'          => null,
                    'created_by'       => auth()->id(),
                    'updated_by'       => auth()->id(),
                ]);
            }
        });

        return redirect()
            ->route('accounting.vouchers.index')
            ->with('success', 'Receipt voucher created successfully.');
    }


    /**
     * Compute proportional TDS amount for a receipt based on allocated AR bills.
     *
     * This is used ONLY for TDS certificate tracking. Accounting for TDS receivable
     * is posted at Client RA Bill posting (SalesPostingService).
     *
     * @param array<int, array<string, mixed>> $allocations Normalized allocations from BillAllocationService
     * @return array{tds_amount: float, tds_section: (string|null), tds_rate: (float|null)}
     */
    protected function computeTdsReceivableFromReceiptAllocations(array $allocations): array
    {
        $modelClass = Config::get('accounting.ar_bill_model');

        if (! is_string($modelClass) || $modelClass === '' || ! class_exists($modelClass)) {
            return ['tds_amount' => 0.0, 'tds_section' => null, 'tds_rate' => null];
        }

        $billIds = [];
        foreach ($allocations as $row) {
            $billId = (int) ($row['bill_id'] ?? 0);
            if ($billId > 0) {
                $billIds[$billId] = true;
            }
        }

        if (empty($billIds)) {
            return ['tds_amount' => 0.0, 'tds_section' => null, 'tds_rate' => null];
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $bills */
        $bills = $modelClass::query()
            ->whereIn('id', array_keys($billIds))
            ->get()
            ->keyBy('id');

        $tdsTotal  = 0.0;
        $sections  = [];
        $ratesSeen = [];

        foreach ($allocations as $row) {
            $billId = (int) ($row['bill_id'] ?? 0);
            $allocAmount = (float) ($row['amount'] ?? 0);

            if ($billId <= 0 || $allocAmount <= 0) {
                continue;
            }

            $bill = $bills->get($billId);
            if (! $bill) {
                continue;
            }

            $billTds = (float) ($bill->tds_amount ?? 0);
            if ($billTds <= 0) {
                continue;
            }

            $billReceivable = (float) ($bill->receivable_amount ?? 0);

            // Fallbacks (if the AR model doesn't have receivable_amount)
            if ($billReceivable <= 0) {
                $billReceivable = (float) ($row['bill_amount'] ?? ($bill->total_amount ?? 0));
            }

            if ($billReceivable <= 0) {
                continue;
            }

            $ratio = $allocAmount / $billReceivable;
            if ($ratio > 1) {
                $ratio = 1;
            } elseif ($ratio < 0) {
                $ratio = 0;
            }

            $tdsTotal += ($billTds * $ratio);

            $sec = trim((string) ($bill->tds_section ?? ''));
            if ($sec !== '') {
                $sections[$sec] = true;
            }

            $rate = (float) ($bill->tds_rate ?? 0);
            if ($rate > 0) {
                $ratesSeen[(string) $rate] = true;
            }
        }

        $tdsSection = count($sections) === 1 ? array_key_first($sections) : null;
        $tdsRate    = null;

        if (count($ratesSeen) === 1) {
            $tdsRate = (float) array_key_first($ratesSeen);
        }

        return [
            'tds_amount'  => round($tdsTotal, 2),
            'tds_section' => $tdsSection,
            'tds_rate'    => $tdsRate,
        ];
    }

    // ---------------------------------------------------------------------
    // APIs: Open bills
    // ---------------------------------------------------------------------

    /**
     * JSON API: Open purchase bills for a selected supplier ledger (AP side).
     */
    public function openPurchaseBills(Request $request): JsonResponse
    {
        $accountId = (int) $request->input('party_account_id');

        if (! $accountId) {
            return response()->json(['data' => []]);
        }

        $account = Account::with('relatedModel')->findOrFail($accountId);

        $rows = $this->billAllocationService->getOpenPurchaseBillsForAccount($account);
		$rows = $rows->filter(function ($row) {
   		 $bill = $row['bill'] ?? null;
   		 return $bill && ($bill->status ?? null) === 'posted';
		})->values();

        $data = collect($rows)->map(function (array $row) {
            /** @var \App\Models\PurchaseBill $bill */
            $bill = $row['bill'];

            return [
                'id'                 => $bill->id,
                'bill_number'        => $bill->bill_number,
                'bill_date'          => $bill->bill_date ? ( ($bill->bill_date instanceof \Carbon\Carbon) ? $bill->bill_date->toDateString() : \Carbon\Carbon::parse($bill->bill_date)->toDateString() ) : null,
                'total_amount'       => (float) ($row['bill_amount'] ?? $bill->total_amount),
                'invoice_total'      => (float) ($bill->total_amount ?? 0),
                'tcs_amount'         => (float) ($bill->tcs_amount ?? 0),
                'tds_amount'         => (float) ($bill->tds_amount ?? 0),
                'allocated_amount'   => (float) $row['allocated'],
                'outstanding_amount' => (float) $row['outstanding'],
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * JSON API: Open client bills for a selected debtor ledger (AR side).
     * For now, this will return an empty list until accounting.ar_bill_model
     * is configured and the AR bill model/table exists.
     */
    public function openClientBills(Request $request): JsonResponse
    {
        $accountId = (int) $request->input('party_account_id');

        if (! $accountId) {
            return response()->json(['data' => []]);
        }

        $account = Account::with('relatedModel')->findOrFail($accountId);

        $asOfDate = $request->input('as_of_date');
        $status   = (string) $request->input('status', 'posted');

        $rows = $this->billAllocationService->getOpenClientBillsForAccount($account, $asOfDate, $status);

        $data = collect($rows)->map(function (array $row) {
            /** @var \Illuminate\Database\Eloquent\Model $bill */
            $bill = $row['bill'];

            $number = $bill->bill_number ?? $bill->invoice_number ?? ('#' . $bill->id);
            $billAmount = (float) ($row['bill_amount'] ?? ($bill->receivable_amount ?? ($bill->total_amount ?? 0.0)));

            return [
                'id'                 => $bill->id,
                'bill_number'        => $number,
                'bill_date'          => $bill->bill_date ? ( ($bill->bill_date instanceof \Carbon\Carbon) ? $bill->bill_date->toDateString() : \Carbon\Carbon::parse($bill->bill_date)->toDateString() ) : null,
                'total_amount'       => $billAmount,
                'allocated_amount'   => (float) $row['allocated'],
                'outstanding_amount' => (float) $row['outstanding'],
            ];
        })->values();

        return response()->json(['data' => $data]);
    }
}
