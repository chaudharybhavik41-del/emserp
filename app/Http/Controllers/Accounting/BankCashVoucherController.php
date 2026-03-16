<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\Accounting\TdsSection;
use App\Models\Accounting\TdsCertificate;
use App\Models\ActivityLog;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\Project;
use App\Models\SubcontractorRaBill;
use App\Models\SubcontractorWorkOrder;
use App\Services\Accounting\BillAllocationService;
use App\Services\Accounting\AccountingDateControlService;
use App\Services\Accounting\PaymentReceiptPostingService;
use App\Services\Accounting\PartyAccountService;
use App\Services\Accounting\ProjectCostCenterResolver;
use App\Services\Accounting\VoucherNumberService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class BankCashVoucherController extends Controller
{
    public function __construct(
        protected BillAllocationService $billAllocationService,
        protected PartyAccountService $partyAccountService,
        protected VoucherNumberService $voucherNumberService,
        protected PaymentReceiptPostingService $paymentReceiptPostingService
    ) {
        $this->middleware('permission:accounting.vouchers.create')
            ->only(['createPayment', 'storePayment', 'createReceipt', 'storeReceipt', 'createCashPayment', 'storeCashPayment']);

        $this->middleware('permission:accounting.vouchers.view')
            ->only(['indexPayment', 'indexReceipt', 'indexCashPayment', 'openPurchaseBills', 'accountSummary', 'openSupplierPurchaseOrders', 'openSubcontractorWorkOrders', 'openClientBills']);
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

        $accounts = Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotIn('type', ['bank', 'cash'])
            ->orderBy('name')
            ->get();

        $partyIds = $accounts
            ->filter(fn (Account $account) => $account->related_model_type === Party::class && ! empty($account->related_model_id))
            ->pluck('related_model_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $partiesById = $partyIds->isEmpty()
            ? collect()
            : Party::query()
                ->whereIn('id', $partyIds)
                ->get(['id', 'is_supplier', 'is_contractor', 'is_client'])
                ->keyBy('id');

        return $accounts->each(function (Account $account) use ($partiesById) {
            $party = $account->related_model_type === Party::class
                ? $partiesById->get((int) ($account->related_model_id ?? 0))
                : null;

            $account->setAttribute('party_is_supplier', (bool) ($party?->is_supplier));
            $account->setAttribute('party_is_contractor', (bool) ($party?->is_contractor));
            $account->setAttribute('party_is_client', (bool) ($party?->is_client));
        });
    }

    protected function resolvePartyFromAccount(Account $account): ?Party
    {
        if ($account->related_model_type !== Party::class || empty($account->related_model_id)) {
            return null;
        }

        return Party::query()->find((int) $account->related_model_id);
    }

    protected function accountBalanceAsOf(Account $account, Carbon|string|null $asOfDate = null): float
    {
        $asOf = $asOfDate instanceof Carbon
            ? $asOfDate->copy()->startOfDay()
            : ($asOfDate ? Carbon::parse((string) $asOfDate)->startOfDay() : now()->startOfDay());

        $opening = 0.0;
        if ((float) ($account->opening_balance ?? 0) !== 0.0) {
            $opening = (float) $account->opening_balance;

            if ($account->opening_balance_date && $account->opening_balance_date->gt($asOf)) {
                $opening = 0.0;
            } elseif (($account->opening_balance_type ?? 'dr') === 'cr') {
                $opening *= -1;
            }
        }

        $query = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', (int) $account->company_id)
            ->where('v.status', 'posted')
            ->where('vl.account_id', $account->id)
            ->whereDate('v.voucher_date', '<=', $asOf->toDateString());

        if ($account->opening_balance_date) {
            $query->whereDate('v.voucher_date', '>=', $account->opening_balance_date->toDateString());
        }

        $agg = $query
            ->selectRaw('COALESCE(SUM(vl.debit),0) as debit_total, COALESCE(SUM(vl.credit),0) as credit_total')
            ->first();

        return round($opening + (float) ($agg->debit_total ?? 0) - (float) ($agg->credit_total ?? 0), 2);
    }

    protected function paymentCounterpartyAccounts(): \Illuminate\Support\Collection
    {
        $companyId = $this->defaultCompanyId();

        Party::query()
            ->where(function ($query) {
                $query->where('is_supplier', true)
                    ->orWhere('is_contractor', true);
            })
            ->orderBy('name')
            ->get()
            ->each(function (Party $party) use ($companyId) {
                $this->partyAccountService->syncAccountForParty($party, $companyId);
            });

        return $this->counterpartyAccounts();
    }

    protected function cashAccounts(): \Illuminate\Support\Collection
    {
        return $this->bankCashAccounts()
            ->where('type', 'cash')
            ->values();
    }

    protected function cashExpenseAccounts(): \Illuminate\Support\Collection
    {
        $companyId = $this->defaultCompanyId();

        return Account::query()
            ->select('accounts.*')
            ->join('account_groups', 'account_groups.id', '=', 'accounts.account_group_id')
            ->where('accounts.company_id', $companyId)
            ->where('accounts.is_active', true)
            ->where('account_groups.nature', 'expense')
            ->orderBy('accounts.name')
            ->get();
    }

    protected function receiptCounterpartyAccounts(): \Illuminate\Support\Collection
    {
        $companyId = $this->defaultCompanyId();

        Party::query()
            ->where('is_client', true)
            ->orderBy('name')
            ->get()
            ->each(function (Party $party) use ($companyId) {
                $this->partyAccountService->syncAccountForParty($party, $companyId);
            });

        return $this->counterpartyAccounts();
    }

    // ---------------------------------------------------------------------
    // Payment
    // ---------------------------------------------------------------------

    public function indexPayment(Request $request)
    {
        return $this->renderVoucherIndex('payment', $request);
    }

    public function createPayment()
    {
        $companyId            = $this->defaultCompanyId();
        $bankCashAccounts     = $this->bankCashAccounts();
        $counterpartyAccounts = $this->paymentCounterpartyAccounts();
        $tdsSections          = TdsSection::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('accounting.vouchers.payment', compact(
            'companyId',
            'bankCashAccounts',
            'counterpartyAccounts',
            'tdsSections'
        ));
    }

    public function indexCashPayment(Request $request)
    {
        return $this->renderVoucherIndex('payment', $request, [
            'forced_payment_type' => 'cash_expense',
            'forced_bank_type' => 'cash',
            'page_title' => 'Cash Payments',
            'create_route' => route('accounting.cash-payments.create'),
            'reset_route' => route('accounting.cash-payments.index'),
            'view_name' => 'accounting.vouchers.cash_payment_index',
            'counterparty_accounts' => $this->cashExpenseAccounts(),
            'bank_cash_accounts' => $this->cashAccounts(),
        ]);
    }

    public function createCashPayment()
    {
        $companyId = $this->defaultCompanyId();
        $cashAccounts = $this->cashAccounts();
        $expenseAccounts = $this->cashExpenseAccounts();
        $projects = Project::query()->orderBy('code')->get();

        return view('accounting.vouchers.cash_payment', compact(
            'companyId',
            'cashAccounts',
            'expenseAccounts',
            'projects'
        ));
    }

    public function storeCashPayment(Request $request)
    {
        $companyId = (int) $request->input('company_id', $this->defaultCompanyId());

        $data = $request->validate([
            'company_id' => ['required', 'integer'],
            'voucher_date' => ['required', 'date'],
            'cash_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'narration' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array'],
            'lines.*.account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.amount' => ['nullable', 'numeric', 'gt:0'],
        ]);

        app(AccountingDateControlService::class)->assertDateOpenForValidation(
            $data['voucher_date'],
            $companyId,
            'voucher_date',
            'Voucher date'
        );

        $cashAccount = Account::query()
            ->where('company_id', $companyId)
            ->where('id', (int) $data['cash_account_id'])
            ->where('is_active', true)
            ->where('type', 'cash')
            ->firstOrFail();

        $expenseAccounts = $this->cashExpenseAccounts()->keyBy('id');
        $validLineExists = false;

        foreach ($data['lines'] as $index => $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $amount = round((float) ($line['amount'] ?? 0), 2);

            if ($accountId <= 0 && $amount <= 0) {
                continue;
            }

            if (! $expenseAccounts->has($accountId)) {
                throw ValidationException::withMessages([
                    'lines.' . $index . '.account_id' => 'Cash payment lines must use active expense ledgers only.',
                ]);
            }

            $validLineExists = true;
        }

        if (! $validLineExists) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one expense line with amount.',
            ]);
        }

        $resolvedCostCenterId = ! empty($data['project_id'])
            ? ProjectCostCenterResolver::resolveId($companyId, (int) $data['project_id'])
            : null;

        $voucher = $this->paymentReceiptPostingService->createPayment([
            'company_id' => $companyId,
            'bank_account_id' => $cashAccount->id,
            'voucher_date' => $data['voucher_date'],
            'project_id' => $data['project_id'] ?? null,
            'cost_center_id' => $resolvedCostCenterId,
            'reference' => $data['reference'] ?? null,
            'narration' => $data['narration'] ?? null,
            'created_by' => auth()->id(),
            'payment_type' => 'cash_expense',
            'lines' => collect($data['lines'])->map(function (array $line) use ($resolvedCostCenterId) {
                return [
                    'account_id' => $line['account_id'] ?? null,
                    'cost_center_id' => $resolvedCostCenterId,
                    'description' => $line['description'] ?? null,
                    'amount' => $line['amount'] ?? null,
                ];
            })->all(),
        ]);

        ActivityLog::logCreated($voucher, 'Created voucher ' . $voucher->voucher_no . ' (Cash Payment)');
        ActivityLog::logCustom(
            'voucher_posted',
            'Posted voucher ' . $voucher->voucher_no,
            $voucher,
            [
                'voucher_type' => 'payment',
                'payment_type' => 'cash_expense',
                'bank_account_id' => $cashAccount->id,
                'payee_count' => (int) $voucher->lines()->where('debit', '>', 0)->count(),
            ]
        );

        return redirect()
            ->route('accounting.cash-payments.index')
            ->with('success', 'Cash payment voucher created successfully.');
    }

    public function storePayment(Request $request)
    {
        $companyId = (int) $request->input('company_id', $this->defaultCompanyId());

        $rules = [
            'company_id'       => ['required', 'integer'],
            'voucher_date'     => ['required', 'date'],
            'bank_account_id'  => ['required', 'integer', 'exists:accounts,id'],
            'party_account_id' => ['nullable', 'integer', 'exists:accounts,id', 'different:bank_account_id'],
            'payment_type'     => ['required', Rule::in(['bill_allocation', 'on_account', 'advance_po', 'advance_work_order'])],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'subcontractor_work_order_id' => ['nullable', 'integer', 'exists:subcontractor_work_orders,id'],
            'amount'           => ['nullable', 'numeric', 'gt:0'],
            'narration'        => ['nullable', 'string', 'max:500'],
            'reference'        => ['nullable', 'string', 'max:100'],
            'on_account_rows' => ['nullable', 'array'],
            'on_account_rows.*.party_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'on_account_rows.*.amount' => ['nullable', 'numeric', 'gt:0'],

            'purchase_allocations'               => ['nullable', 'array'],
            'purchase_allocations.*.bill_type'   => ['nullable', 'string'],
            'purchase_allocations.*.bill_id'     => ['nullable', 'integer'],
            'purchase_allocations.*.selected'    => ['nullable'],
            'purchase_allocations.*.amount'      => ['nullable', 'numeric', 'min:0'],
        ];

        $data = $request->validate($rules);
        app(AccountingDateControlService::class)->assertDateOpenForValidation(
            $data['voucher_date'],
            $companyId,
            'voucher_date',
            'Voucher date'
        );

        $bankAccount = Account::where('id', $data['bank_account_id'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('type', ['bank', 'cash'])
            ->firstOrFail();

        $paymentType = (string) ($data['payment_type'] ?? 'on_account');
        $voucherDate = Carbon::parse((string) $data['voucher_date'])->startOfDay();
        $allocRows = [];
        $purchaseOrder = null;
        $subcontractorWorkOrder = null;
        $partyAccount = null;
        $party = null;
        $onAccountRows = collect();

        if ($paymentType === 'on_account') {
            $onAccountRows = collect($data['on_account_rows'] ?? [])
                ->map(function ($row) {
                    return [
                        'party_account_id' => (int) ($row['party_account_id'] ?? 0),
                        'amount' => round((float) ($row['amount'] ?? 0), 2),
                    ];
                })
                ->filter(fn (array $row) => $row['party_account_id'] > 0 && $row['amount'] > 0)
                ->values();

            if ($onAccountRows->isEmpty()) {
                if (empty($data['party_account_id']) || (float) ($data['amount'] ?? 0) <= 0) {
                    throw ValidationException::withMessages([
                        'on_account_rows' => 'Add at least one payee row for on-account payment.',
                    ]);
                }

                $onAccountRows = collect([[
                    'party_account_id' => (int) $data['party_account_id'],
                    'amount' => round((float) $data['amount'], 2),
                ]]);
            }

            $payeeAccounts = Account::query()
                ->whereIn('id', $onAccountRows->pluck('party_account_id')->unique()->all())
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereNotIn('type', ['bank', 'cash'])
                ->get()
                ->keyBy('id');

            $onAccountRows = $onAccountRows->map(function (array $row) use ($payeeAccounts, $bankAccount) {
                $account = $payeeAccounts->get($row['party_account_id']);

                if (! $account) {
                    throw ValidationException::withMessages([
                        'on_account_rows' => 'Each on-account row must use an active non-bank/cash payee ledger.',
                    ]);
                }

                if ((int) $account->id === (int) $bankAccount->id) {
                    throw ValidationException::withMessages([
                        'on_account_rows' => 'Payee ledger cannot be the same as bank/cash account.',
                    ]);
                }

                $resolvedParty = null;
                if ($account->related_model_type === Party::class && ! empty($account->related_model_id)) {
                    $resolvedParty = Party::query()->find((int) $account->related_model_id);
                }

                return [
                    'account' => $account,
                    'party' => $resolvedParty,
                    'amount' => $row['amount'],
                ];
            });

            $data['amount'] = round((float) $onAccountRows->sum('amount'), 2);
        } else {
            if (empty($data['party_account_id']) || (float) ($data['amount'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'party_account_id' => 'Select a payee ledger and enter amount.',
                ]);
            }

            $partyAccount = Account::where('id', $data['party_account_id'])
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->firstOrFail();

            if ($partyAccount->related_model_type === Party::class && ! empty($partyAccount->related_model_id)) {
                $party = $partyAccount->relationLoaded('relatedModel')
                    ? $partyAccount->relatedModel
                    : Party::query()->find((int) $partyAccount->related_model_id);
            }
        }

        if ($paymentType === 'bill_allocation'
            && (! $party || (! $party->is_supplier && ! $party->is_contractor))) {
            throw ValidationException::withMessages([
                'payment_type' => 'Selected payee ledger must be linked to a supplier or subcontractor for bill allocation.',
            ]);
        }

        if ($paymentType === 'advance_po'
            && (! $party || ! $party->is_supplier)) {
            throw ValidationException::withMessages([
                'payment_type' => 'Selected payee ledger must be linked to a supplier for PO advance.',
            ]);
        }

        if ($paymentType === 'advance_work_order'
            && (! $party || ! $party->is_contractor)) {
            throw ValidationException::withMessages([
                'payment_type' => 'Selected payee ledger must be linked to a subcontractor for work-order advance.',
            ]);
        }

        if ($paymentType === 'bill_allocation') {
            $allocRows = $this->billAllocationService->validatePurchasePaymentAllocations(
                $partyAccount,
                (float) $data['amount'],
                $data['purchase_allocations'] ?? [],
                $voucherDate
            );
        } elseif ($paymentType === 'advance_po') {
            $purchaseOrderQuery = PurchaseOrder::query()
                ->where('id', (int) ($data['purchase_order_id'] ?? 0))
                ->where('vendor_party_id', (int) ($party?->id ?? 0));

            if (Schema::hasColumn('purchase_orders', 'deleted_at')) {
                $purchaseOrderQuery->whereNull('deleted_at');
            }

            if (Schema::hasColumn('purchase_orders', 'status')) {
                $purchaseOrderQuery->where('status', '<>', 'cancelled');
            }

            $purchaseOrder = $purchaseOrderQuery->first();

            if (! $purchaseOrder) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'Select a valid purchase order for the chosen supplier.',
                ]);
            }
        } elseif ($paymentType === 'advance_work_order') {
            $workOrderQuery = SubcontractorWorkOrder::query()
                ->where('company_id', $companyId)
                ->where('id', (int) ($data['subcontractor_work_order_id'] ?? 0))
                ->where('subcontractor_id', (int) ($party?->id ?? 0));

            if (Schema::hasColumn('subcontractor_work_orders', 'status')) {
                $workOrderQuery->where('status', '<>', 'cancelled');
            }

            $subcontractorWorkOrder = $workOrderQuery->first();

            if (! $subcontractorWorkOrder) {
                throw ValidationException::withMessages([
                    'subcontractor_work_order_id' => 'Select a valid work order for the chosen subcontractor.',
                ]);
            }
        }

        $allocatedTotal = round(collect($allocRows)->sum('amount'), 2);
        $unallocatedAmount = round(max(0, (float) $data['amount'] - $allocatedTotal), 2);

        DB::transaction(function () use (
            $companyId,
            $data,
            $bankAccount,
            $partyAccount,
            $onAccountRows,
            $allocRows,
            $unallocatedAmount,
            $paymentType,
            $purchaseOrder,
            $subcontractorWorkOrder,
            $party
        ) {
            $voucher               = new Voucher();
            $voucher->company_id   = $companyId;
            // Centralised voucher numbering (Phase 5a)
            $voucher->voucher_no   = $this->voucherNumberService->next('payment', $companyId, $data['voucher_date']);
            $voucher->voucher_type = 'payment';
            $voucher->payment_type = $paymentType;
            $voucher->purchase_order_id = $paymentType === 'advance_po' ? $purchaseOrder?->id : null;
            $voucher->subcontractor_work_order_id = $paymentType === 'advance_work_order' ? $subcontractorWorkOrder?->id : null;
            $voucher->voucher_date = $data['voucher_date'];
            $voucher->reference    = $data['reference'] ?? null;
            $voucher->narration    = $data['narration'] ?? (
                $paymentType === 'on_account' && $onAccountRows->count() > 1
                    ? 'Payment - multiple payees'
                    : 'Payment - ' . ($partyAccount?->name ?? 'payee')
            );
            $voucher->project_id   = null;
            $voucher->cost_center_id = null;
            $voucher->currency_id  = null;
            $voucher->exchange_rate = 1;
            $voucher->amount_base  = $data['amount'];
            // IMPORTANT (Phase 5b): create voucher as DRAFT, insert lines, then POST.
            // This allows the Voucher model guardrail to validate Dr == Cr at posting time.
            $voucher->status       = 'draft';
            $voucher->created_by   = auth()->id();
            $voucher->save();

            $lineNo = 1;
            $payeeAccountIds = [];
            $payeeCount = 0;
            $partyLine = null;

            if ($paymentType === 'on_account' && $onAccountRows->isNotEmpty()) {
                foreach ($onAccountRows as $row) {
                    $line = VoucherLine::create([
                        'voucher_id'     => $voucher->id,
                        'line_no'        => $lineNo++,
                        'account_id'     => $row['account']->id,
                        'cost_center_id' => null,
                        'description'    => $data['narration'] ?? ('Payment - ' . $row['account']->name),
                        'debit'          => $row['amount'],
                        'credit'         => 0,
                        'reference_type' => null,
                        'reference_id'   => null,
                    ]);

                    $payeeAccountIds[] = (int) $row['account']->id;
                    $payeeCount++;

                    if ($row['party'] && ($row['party']->is_supplier || $row['party']->is_contractor)) {
                        $this->billAllocationService->storeOnAccountForPayment($line, $row['amount']);
                    }
                }
            } else {
                // Dr Party / Expense
                $partyLine = VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $partyAccount->id,
                    'cost_center_id' => null,
                    'description'    => $data['narration'] ?? ('Payment - ' . $partyAccount->name),
                    'debit'          => $data['amount'],
                    'credit'         => 0,
                    'reference_type' => null,
                    'reference_id'   => null,
                ]);

                $payeeAccountIds[] = (int) $partyAccount->id;
                $payeeCount = 1;
            }

            // Cr Bank / Cash
            VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => $lineNo++,
                'account_id'     => $bankAccount->id,
                'cost_center_id' => null,
                'description'    => $data['narration'] ?? ('Payment - ' . $bankAccount->name),
                'debit'          => 0,
                'credit'         => $data['amount'],
                'reference_type' => null,
                'reference_id'   => null,
            ]);

            if ($partyLine && ! empty($allocRows)) {
                $this->billAllocationService->storePurchaseAllocationsForPayment($partyLine, $allocRows);
            }

            if ($partyLine && $paymentType === 'advance_po' && $purchaseOrder && $unallocatedAmount > 0) {
                $this->billAllocationService->storeAdvanceForPayment($partyLine, $purchaseOrder, $unallocatedAmount);
            } elseif ($partyLine && $paymentType === 'advance_work_order' && $subcontractorWorkOrder && $unallocatedAmount > 0) {
                $this->billAllocationService->storeAdvanceForSubcontractorPayment($partyLine, $subcontractorWorkOrder, $unallocatedAmount);
            } elseif ($partyLine && $party && ($party->is_supplier || $party->is_contractor) && $unallocatedAmount > 0) {
                $this->billAllocationService->storeOnAccountForPayment($partyLine, $unallocatedAmount);
            }

            // Finalize posting (validates balance + normalizes amount_base)
            $voucher->posted_by = auth()->id();
            $voucher->posted_at = now();
            $voucher->status    = 'posted';
            $voucher->save();

            ActivityLog::logCreated($voucher, 'Created voucher ' . $voucher->voucher_no . ' (Draft)');
            ActivityLog::logCustom(
                'voucher_posted',
                'Posted voucher ' . $voucher->voucher_no,
                $voucher,
                [
                    'voucher_type' => 'payment',
                    'payment_type' => $paymentType,
                    'bank_account_id' => $bankAccount->id,
                    'party_account_id' => $partyAccount?->id,
                    'payee_account_ids' => $payeeAccountIds,
                    'payee_count' => $payeeCount,
                    'party_id' => $party?->id,
                ]
            );
        });

        return redirect()
            ->route('accounting.payments.index')
            ->with('success', 'Payment voucher created successfully.');
    }

    // ---------------------------------------------------------------------
    // Receipt
    // ---------------------------------------------------------------------

    public function indexReceipt(Request $request)
    {
        return $this->renderVoucherIndex('receipt', $request);
    }

    public function createReceipt()
    {
        $companyId            = $this->defaultCompanyId();
        $bankCashAccounts     = $this->bankCashAccounts();
        $counterpartyAccounts = $this->receiptCounterpartyAccounts();
        $tdsSections          = TdsSection::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('accounting.vouchers.receipt', compact(
            'companyId',
            'bankCashAccounts',
            'counterpartyAccounts',
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

            'tds_section'      => ['nullable', 'string', 'max:20', Rule::exists('tds_sections', 'code')->where(fn ($q) => $q->where('company_id', $companyId)->where('is_active', true))],
            'tds_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tds_certificate_no'   => ['nullable', 'string', 'max:100'],
            'tds_certificate_date' => ['nullable', 'date'],

            'receipt_allocations'               => ['nullable', 'array'],
            'receipt_allocations.*.bill_id'     => ['nullable', 'integer'],
            'receipt_allocations.*.selected'    => ['nullable'],
            'receipt_allocations.*.amount'      => ['nullable', 'numeric', 'min:0'],
        ];

        $data = $request->validate($rules);
        app(AccountingDateControlService::class)->assertDateOpenForValidation(
            $data['voucher_date'],
            $companyId,
            'voucher_date',
            'Voucher date'
        );

        $bankAccount = Account::where('id', $data['bank_account_id'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('type', ['bank', 'cash'])
            ->firstOrFail();

        $partyAccount = Account::where('id', $data['party_account_id'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->firstOrFail();

        $party = $this->resolvePartyFromAccount($partyAccount);

        $allocRows = $this->billAllocationService->validateClientReceiptAllocations(
            $partyAccount,
            (float) $data['amount'],
            $data['receipt_allocations'] ?? [],
            Carbon::parse((string) $data['voucher_date'])->startOfDay(),
            'posted'
        );

        DB::transaction(function () use ($companyId, $data, $bankAccount, $partyAccount, $allocRows, $party) {
            $voucher               = new Voucher();
            $voucher->company_id   = $companyId;
            // Centralised voucher numbering (Phase 5a)
            $voucher->voucher_no   = $this->voucherNumberService->next('receipt', $companyId, $data['voucher_date']);
            $voucher->voucher_type = 'receipt';
            $voucher->voucher_date = $data['voucher_date'];
            $voucher->reference    = $data['reference'] ?? null;
            $voucher->narration    = $data['narration'] ?? ('Receipt - ' . $partyAccount->name);
            $voucher->project_id   = null;
            $voucher->cost_center_id = null;
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
                'cost_center_id' => null,
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
                'cost_center_id' => null,
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
            if ($leftover > 0.009 && $party?->is_client) {
                $this->billAllocationService->storeOnAccountForReceipt($clientLine, $leftover);
            }

            // Finalize posting (validates balance + normalizes amount_base)
            $voucher->posted_by = auth()->id();
            $voucher->posted_at = now();
            $voucher->status    = 'posted';
            $voucher->save();

            ActivityLog::logCreated($voucher, 'Created voucher ' . $voucher->voucher_no . ' (Draft)');
            ActivityLog::logCustom(
                'voucher_posted',
                'Posted voucher ' . $voucher->voucher_no,
                $voucher,
                [
                    'voucher_type' => 'receipt',
                    'bank_account_id' => $bankAccount->id,
                    'party_account_id' => $partyAccount->id,
                    'party_id' => $party?->id,
                    'party_is_client' => (bool) ($party?->is_client),
                ]
            );

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
            ->route('accounting.receipts.index')
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

    protected function renderVoucherIndex(string $voucherType, Request $request, array $options = [])
    {
        $companyId = $this->defaultCompanyId();
        $query = Voucher::query()
            ->with(['createdBy', 'lines.account'])
            ->where('company_id', $companyId)
            ->where('voucher_type', $voucherType)
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        if (! empty($options['forced_payment_type'])) {
            $query->where('payment_type', (string) $options['forced_payment_type']);
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('voucher_date', '>=', (string) $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('voucher_date', '<=', (string) $request->input('date_to'));
        }

        if ($request->filled('bank_account_id')) {
            $bankAccountId = (int) $request->input('bank_account_id');
            $query->whereHas('lines.account', function ($lineQuery) use ($bankAccountId) {
                $lineQuery->where('accounts.id', $bankAccountId)
                    ->whereIn('accounts.type', ['bank', 'cash']);
            });
        }

        if (! empty($options['forced_bank_type'])) {
            $query->whereHas('lines.account', function ($lineQuery) use ($options) {
                $lineQuery->where('accounts.type', (string) $options['forced_bank_type']);
            });
        }

        if ($request->filled('party_account_id')) {
            $partyAccountId = (int) $request->input('party_account_id');
            $query->whereHas('lines', function ($lineQuery) use ($partyAccountId) {
                $lineQuery->where('account_id', $partyAccountId);
            });
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($term) {
                $inner->where('voucher_no', 'like', '%' . $term . '%')
                    ->orWhere('reference', 'like', '%' . $term . '%')
                    ->orWhere('narration', 'like', '%' . $term . '%');
            });
        }

        $vouchers = $query->paginate(20)->withQueryString();
        $bankCashAccounts = $options['bank_cash_accounts'] ?? $this->bankCashAccounts();
        $counterpartyAccounts = $options['counterparty_accounts'] ?? ($voucherType === 'payment'
            ? $this->paymentCounterpartyAccounts()
            : $this->receiptCounterpartyAccounts());
        $pageTitle = $options['page_title'] ?? ($voucherType === 'payment' ? 'Payment Vouchers' : 'Receipt Vouchers');
        $createRoute = $options['create_route'] ?? ($voucherType === 'payment'
            ? route('accounting.payments.create')
            : route('accounting.receipts.create'));
        $resetRoute = $options['reset_route'] ?? ($voucherType === 'payment'
            ? route('accounting.payments.index')
            : route('accounting.receipts.index'));
        $viewName = $options['view_name'] ?? ($voucherType === 'payment'
            ? 'accounting.vouchers.payment_index'
            : 'accounting.vouchers.receipt_index');

        return view($viewName, compact(
            'vouchers',
            'bankCashAccounts',
            'counterpartyAccounts',
            'pageTitle',
            'createRoute',
            'resetRoute'
        ));
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
        $companyId = $this->defaultCompanyId();

        if (! $accountId) {
            return response()->json(['data' => []]);
        }

        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('id', $accountId)
            ->where('is_active', true)
            ->firstOrFail();

        $rows = $this->billAllocationService->getOpenPayableBillsForAccount(
            $account,
            $request->input('as_of_date')
        );
        $rows = $rows->filter(function ($row) {
            $bill = $row['bill'] ?? null;

            return $bill && ($bill->status ?? null) === 'posted';
        })->values();

        $data = collect($rows)->map(function (array $row) {
            /** @var \Illuminate\Database\Eloquent\Model $bill */
            $bill = $row['bill'];
            $billType = (string) ($row['bill_type'] ?? '');
            $isPurchase = $billType === \App\Models\PurchaseBill::class;
            $isSubcontractorRa = $billType === SubcontractorRaBill::class;

            return [
                'id'                 => $bill->id,
                'bill_type'          => $billType,
                'bill_kind'          => $isSubcontractorRa ? 'Subcontractor RA Bill' : 'Purchase Bill',
                'bill_number'        => $isSubcontractorRa
                    ? ($bill->ra_number ?: $bill->bill_number)
                    : $bill->bill_number,
                'bill_reference'     => $isSubcontractorRa ? ($bill->bill_number ?: null) : null,
                'reference_no'       => $isPurchase ? $bill->reference_no : null,
                'bill_date'          => $bill->bill_date ? ( ($bill->bill_date instanceof \Carbon\Carbon) ? $bill->bill_date->toDateString() : \Carbon\Carbon::parse($bill->bill_date)->toDateString() ) : null,
                'total_amount'       => (float) ($row['bill_amount'] ?? $bill->total_amount),
                'invoice_total'      => (float) ($bill->total_amount ?? 0),
                'tcs_amount'         => $isPurchase ? (float) ($bill->tcs_amount ?? 0) : 0.0,
                'tds_amount'         => $isPurchase ? (float) ($bill->tds_amount ?? 0) : (float) ($bill->tds_amount ?? 0),
                'allocated_amount'   => (float) $row['allocated'],
                'outstanding_amount' => (float) $row['outstanding'],
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function accountSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'role' => ['required', Rule::in(['bank', 'payee', 'client'])],
            'as_of_date' => ['nullable', 'date'],
        ]);

        $companyId = $this->defaultCompanyId();

        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('id', (int) $data['account_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $asOfDate = $data['as_of_date'] ?? null;

        if ($data['role'] === 'bank') {
            $balance = $this->accountBalanceAsOf($account, $asOfDate);

            return response()->json([
                'data' => [
                    'role' => 'bank',
                    'account_id' => (int) $account->id,
                    'balance' => abs($balance),
                    'balance_side' => $balance >= 0 ? 'dr' : 'cr',
                    'balance_signed' => $balance,
                ],
            ]);
        }

        if ($data['role'] === 'client') {
            $totalOutstanding = (float) $this->billAllocationService
                ->getOpenClientBillsForAccount($account, $asOfDate, 'posted')
                ->sum('outstanding');

            return response()->json([
                'data' => [
                    'role' => 'client',
                    'account_id' => (int) $account->id,
                    'total_outstanding' => round($totalOutstanding, 2),
                ],
            ]);
        }

        $totalOutstanding = (float) $this->billAllocationService
            ->getOpenPayableBillsForAccount($account, $asOfDate)
            ->sum('outstanding');

        return response()->json([
            'data' => [
                'role' => 'payee',
                'account_id' => (int) $account->id,
                'total_outstanding' => round($totalOutstanding, 2),
            ],
        ]);
    }

    public function openSupplierPurchaseOrders(Request $request): JsonResponse
    {
        $accountId = (int) $request->input('party_account_id');
        $companyId = $this->defaultCompanyId();

        if (! $accountId) {
            return response()->json(['data' => []]);
        }

        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('id', $accountId)
            ->where('is_active', true)
            ->firstOrFail();

        $party = $this->resolvePartyFromAccount($account);

        if (! $party || ! $party->is_supplier) {
            return response()->json(['data' => []]);
        }

        $orders = PurchaseOrder::query()
            ->with('project')
            ->where('vendor_party_id', (int) $party->id)
            ->orderByDesc('po_date')
            ->limit(100);

        if (Schema::hasColumn('purchase_orders', 'deleted_at')) {
            $orders->whereNull('deleted_at');
        }

        if (Schema::hasColumn('purchase_orders', 'status')) {
            $orders->where('status', '<>', 'cancelled');
        }

        $orders = $orders->get();

        $data = $orders->map(function (PurchaseOrder $purchaseOrder) {
            return [
                'id' => (int) $purchaseOrder->id,
                'code' => (string) $purchaseOrder->code,
                'po_date' => $purchaseOrder->po_date?->format('Y-m-d'),
                'status' => (string) ($purchaseOrder->status ?? ''),
                'project_code' => (string) ($purchaseOrder->project?->code ?? ''),
                'project_name' => (string) ($purchaseOrder->project?->name ?? ''),
                'total_amount' => (float) ($purchaseOrder->total_amount ?? 0),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function openSubcontractorWorkOrders(Request $request): JsonResponse
    {
        $accountId = (int) $request->input('party_account_id');
        $companyId = $this->defaultCompanyId();

        if (! $accountId) {
            return response()->json(['data' => []]);
        }

        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('id', $accountId)
            ->where('is_active', true)
            ->firstOrFail();

        $party = $this->resolvePartyFromAccount($account);

        if (! $party || ! $party->is_contractor) {
            return response()->json(['data' => []]);
        }

        $workOrders = SubcontractorWorkOrder::query()
            ->with('project')
            ->where('company_id', $companyId)
            ->where('subcontractor_id', (int) $party->id)
            ->orderByDesc('work_order_date')
            ->orderBy('work_order_number')
            ->limit(100);

        if (Schema::hasColumn('subcontractor_work_orders', 'status')) {
            $workOrders->where('status', '<>', 'cancelled');
        }

        $data = $workOrders->get()->map(function (SubcontractorWorkOrder $workOrder) {
            return [
                'id' => (int) $workOrder->id,
                'code' => (string) $workOrder->work_order_number,
                'work_order_date' => $workOrder->work_order_date?->format('Y-m-d'),
                'status' => (string) ($workOrder->status ?? ''),
                'project_code' => (string) ($workOrder->project?->code ?? ''),
                'project_name' => (string) ($workOrder->project?->name ?? ''),
                'payment_terms_days' => $workOrder->payment_terms_days,
                'retention_percent' => (float) ($workOrder->retention_percent ?? 0),
                'security_deposit_percent' => (float) ($workOrder->security_deposit_percent ?? 0),
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
        $companyId = $this->defaultCompanyId();

        if (! $accountId) {
            return response()->json(['data' => []]);
        }

        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('id', $accountId)
            ->where('is_active', true)
            ->firstOrFail();

        $asOfDate = $request->input('as_of_date');
        $status   = (string) $request->input('status', 'posted');

        $rows = $this->billAllocationService->getOpenClientBillsForAccount($account, $asOfDate, $status);

        $data = collect($rows)->map(function (array $row) {
            /** @var \Illuminate\Database\Eloquent\Model $bill */
            $bill = $row['bill'];

            $number = $bill->bill_number ?? $bill->invoice_number ?? ('#' . $bill->id);
            $invoiceAmount = (float) ($bill->total_amount ?? ($row['bill_amount'] ?? 0.0));
            $receivableAmount = (float) ($bill->receivable_amount ?? ($row['bill_amount'] ?? $invoiceAmount));
            if ($receivableAmount <= 0) {
                $receivableAmount = (float) ($row['bill_amount'] ?? $invoiceAmount);
            }
            $tdsAmount = (float) ($bill->tds_amount ?? max(0.0, $invoiceAmount - $receivableAmount));

            return [
                'id'                 => $bill->id,
                'bill_number'        => $number,
                'bill_reference'     => (string) ($bill->invoice_number ?? $number),
                'bill_date'          => $bill->bill_date ? ( ($bill->bill_date instanceof \Carbon\Carbon) ? $bill->bill_date->toDateString() : \Carbon\Carbon::parse($bill->bill_date)->toDateString() ) : null,
                'total_amount'       => $invoiceAmount,
                'invoice_amount'     => $invoiceAmount,
                'tds_amount'         => $tdsAmount,
                'receivable_amount'  => $receivableAmount,
                'tds_section'        => $bill->tds_section ?? null,
                'tds_rate'           => (float) ($bill->tds_rate ?? 0),
                'allocated_amount'   => (float) $row['allocated'],
                'outstanding_amount' => (float) $row['outstanding'],
            ];
        })->values();

        return response()->json(['data' => $data]);
    }
}
