<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountBillAllocation;
use App\Models\Accounting\VoucherLine;
use App\Models\Party;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\SubcontractorRaBill;
use App\Models\SubcontractorWorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillAllocationService
{
    /**
     * Synthetic bill_type used to store unallocated (on-account) receipts.
     *
     * This is intentionally NOT a real model class.
     */
    public const ON_ACCOUNT_BILL_TYPE = '__on_account__';

    public const MODE_AGAINST    = 'against';
    public const MODE_ADVANCE    = 'advance';
    public const MODE_ON_ACCOUNT = 'on_account';

    // ---------------------------------------------------------------------
    // Common helpers
    // ---------------------------------------------------------------------

    protected function asOf(Carbon|string|null $asOfDate = null): Carbon
    {
        if ($asOfDate instanceof Carbon) {
            return $asOfDate->copy()->startOfDay();
        }

        if (is_string($asOfDate) && $asOfDate !== '') {
            return Carbon::parse($asOfDate)->startOfDay();
        }

        return now()->startOfDay();
    }

    protected function toDateString(Carbon|string|null $date): string
    {
        return $this->asOf($date)->toDateString();
    }

    /**
     * Sum allocations for a list of bill IDs, considering only POSTED vouchers.
     * Optionally filters by allocation_date <= asOfDate.
     *
     * @param  array<int,int>  $billIds
     * @param  array<int,string>  $modes
     * @return array<int,float>  bill_id => allocated
     */
    protected function sumAllocationsForBillIds(
        int $companyId,
        string $billType,
        array $billIds,
        ?Carbon $asOfDate = null,
        array $modes = [self::MODE_AGAINST]
    ): array {
        if (empty($billIds)) {
            return [];
        }

        $q = AccountBillAllocation::query()
            ->select('account_bill_allocations.bill_id', DB::raw('SUM(account_bill_allocations.amount) as total'))
            ->join('vouchers', 'vouchers.id', '=', 'account_bill_allocations.voucher_id')
            ->where('account_bill_allocations.company_id', $companyId)
            ->where('account_bill_allocations.bill_type', $billType)
            ->whereIn('account_bill_allocations.bill_id', $billIds)
            ->whereIn('account_bill_allocations.mode', $modes)
            ->where('vouchers.status', 'posted')
            ->groupBy('account_bill_allocations.bill_id');

        if ($asOfDate) {
            $q->whereDate('account_bill_allocations.allocation_date', '<=', $asOfDate->toDateString());
        }

        /** @var \Illuminate\Support\Collection<int,string> $map */
        $map = $q->pluck('total', 'account_bill_allocations.bill_id');

        $out = [];
        foreach ($map as $billId => $total) {
            $out[(int) $billId] = (float) $total;
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // AP side (Supplier / Purchase Bills)
    // ---------------------------------------------------------------------

    protected function resolveSupplierParty(Account $account): ?Party
    {
        if ($account->related_model_type !== Party::class || empty($account->related_model_id)) {
            return null;
        }

        /** @var Party|null $party */
        $party = $account->relationLoaded('relatedModel')
            ? $account->relatedModel
            : null;

        if (! $party && ! empty($account->related_model_id)) {
            $party = Party::query()->find((int) $account->related_model_id);
        }

        if (! $party || ! $party->is_supplier) {
            return null;
        }

        return $party;
    }

    protected function resolveClientParty(Account $account): ?Party
    {
        if ($account->related_model_type !== Party::class || empty($account->related_model_id)) {
            return null;
        }

        /** @var Party|null $party */
        $party = $account->relationLoaded('relatedModel')
            ? $account->relatedModel
            : null;

        if (! $party && ! empty($account->related_model_id)) {
            $party = Party::query()->find((int) $account->related_model_id);
        }

        if (! $party || ! $party->is_client) {
            return null;
        }

        return $party;
    }

    protected function resolveSubcontractorParty(Account $account): ?Party
    {
        if ($account->related_model_type !== Party::class || empty($account->related_model_id)) {
            return null;
        }

        /** @var Party|null $party */
        $party = $account->relationLoaded('relatedModel')
            ? $account->relatedModel
            : null;

        if (! $party && ! empty($account->related_model_id)) {
            $party = Party::query()->find((int) $account->related_model_id);
        }

        if (! $party || ! $party->is_contractor) {
            return null;
        }

        return $party;
    }

    /**
     * Get open purchase bills (with outstanding amount) for a given supplier ledger account.
     *
     * @return Collection<int, array{bill: PurchaseBill, bill_amount: float, allocated: float, outstanding: float}>
     */
    public function getOpenPurchaseBillsForAccount(Account $account, Carbon|string|null $asOfDate = null): Collection
    {
        $party = $this->resolveSupplierParty($account);
        if (! $party) {
            return collect();
        }

        $supplierId = (int) $account->related_model_id;
        $companyId  = (int) $account->company_id;
        $asOf       = $this->asOf($asOfDate);

        $bills = PurchaseBill::where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->where('status', 'posted')
            ->whereDate('bill_date', '<=', $asOf->toDateString())
            ->orderBy('bill_date')
            ->orderBy('id')
            ->get();

        if ($bills->isEmpty()) {
            return collect();
        }

        $billIds = $bills->pluck('id')->all();

        // IMPORTANT: only posted vouchers should affect outstanding.
        $allocations = $this->sumAllocationsForBillIds(
            $companyId,
            PurchaseBill::class,
            $billIds,
            $asOf,
            [self::MODE_AGAINST]
        );

        return $bills->map(function (PurchaseBill $bill) use ($allocations) {
            $billAmount  = (float) ($bill->total_amount ?? 0)
                + (float) ($bill->tcs_amount ?? 0)
                - (float) ($bill->tds_amount ?? 0);
            $allocated   = (float) ($allocations[$bill->id] ?? 0.0);
            $outstanding = max(0.0, $billAmount - $allocated);

            return [
                'bill'        => $bill,
                'bill_amount' => $billAmount,
                'allocated'   => $allocated,
                'outstanding' => $outstanding,
            ];
        })->filter(fn (array $row) => $row['outstanding'] > 0.0)->values();
    }

    /**
     * Get open subcontractor RA bills (with outstanding amount) for a contractor ledger account.
     *
     * @return Collection<int, array{bill: SubcontractorRaBill, bill_amount: float, allocated: float, outstanding: float}>
     */
    public function getOpenSubcontractorRaBillsForAccount(Account $account, Carbon|string|null $asOfDate = null): Collection
    {
        $party = $this->resolveSubcontractorParty($account);
        if (! $party) {
            return collect();
        }

        $subcontractorId = (int) $account->related_model_id;
        $companyId = (int) $account->company_id;
        $asOf = $this->asOf($asOfDate);

        $bills = SubcontractorRaBill::query()
            ->where('company_id', $companyId)
            ->where('subcontractor_id', $subcontractorId)
            ->where('status', 'posted')
            ->whereDate('bill_date', '<=', $asOf->toDateString())
            ->orderBy('bill_date')
            ->orderBy('id')
            ->get();

        if ($bills->isEmpty()) {
            return collect();
        }

        $billIds = $bills->pluck('id')->all();
        $allocations = $this->sumAllocationsForBillIds(
            $companyId,
            SubcontractorRaBill::class,
            $billIds,
            $asOf,
            [self::MODE_AGAINST]
        );

        return $bills->map(function (SubcontractorRaBill $bill) use ($allocations) {
            $billAmount = (float) ($bill->total_amount ?? 0);
            $allocated = (float) ($allocations[$bill->id] ?? 0.0);
            $outstanding = max(0.0, $billAmount - $allocated);

            return [
                'bill' => $bill,
                'bill_amount' => $billAmount,
                'allocated' => $allocated,
                'outstanding' => $outstanding,
            ];
        })->filter(fn (array $row) => $row['outstanding'] > 0.0)->values();
    }

    /**
     * Get all open AP bills that can be settled from a payment voucher.
     *
     * Includes supplier purchase bills and subcontractor RA bills for party ledgers
     * that are linked to those roles.
     *
     * @return Collection<int, array{bill: Model, bill_type: string, bill_amount: float, allocated: float, outstanding: float}>
     */
    public function getOpenPayableBillsForAccount(Account $account, Carbon|string|null $asOfDate = null): Collection
    {
        $purchaseBills = $this->getOpenPurchaseBillsForAccount($account, $asOfDate)
            ->map(fn (array $row) => $row + ['bill_type' => PurchaseBill::class]);

        $subcontractorBills = $this->getOpenSubcontractorRaBillsForAccount($account, $asOfDate)
            ->map(fn (array $row) => $row + ['bill_type' => SubcontractorRaBill::class]);

        return $purchaseBills
            ->concat($subcontractorBills)
            ->sortBy(function (array $row) {
                $bill = $row['bill'];
                $billDate = $bill->bill_date;
                $dateKey = $billDate instanceof Carbon
                    ? $billDate->toDateString()
                    : (string) $billDate;

                return $dateKey . '|' . str_pad((string) ((int) ($bill->id ?? 0)), 12, '0', STR_PAD_LEFT);
            })
            ->values();
    }

    /**
     * Validate allocations entered on a payment voucher against payable bills.
     *
     * Supports supplier purchase bills and subcontractor RA bills for the
     * selected party ledger.
     *
     * @param  array<int, array{bill_type?: string|null, bill_id?: int|string|null, amount?: float|string|null}>  $rows
     * @return array<int, array{bill_type: string, bill_id: int, amount: float}>
     *
     * @throws ValidationException
     */
    public function validatePurchasePaymentAllocations(
        Account $supplierAccount,
        float $voucherAmount,
        array $rows,
        Carbon|string|null $asOfDate = null
    ): array
    {
        $normalized = [];
        $totalAlloc = 0.0;

        $supplierParty = $this->resolveSupplierParty($supplierAccount);
        $subcontractorParty = $this->resolveSubcontractorParty($supplierAccount);
        if (! $supplierParty && ! $subcontractorParty) {
            // No allocations allowed unless this is a Party ledger.
            foreach ($rows as $row) {
                if (! empty($row['bill_id']) && (float) ($row['amount'] ?? 0) > 0) {
                    throw ValidationException::withMessages([
                        'party_account_id' => 'Bill allocations are only supported for Party ledgers linked to a supplier or subcontractor.',
                    ]);
                }
            }

            return [];
        }

        $companyId  = (int) $supplierAccount->company_id;
        $asOf       = $this->asOf($asOfDate);
        $openBills  = $this->getOpenPayableBillsForAccount($supplierAccount, $asOf);
        $outstandingMap = [];
        foreach ($openBills as $row) {
            /** @var Model $bill */
            $bill = $row['bill'];
            $outstandingMap[(string) ($row['bill_type'] . ':' . (int) $bill->id)] = (float) ($row['outstanding'] ?? 0.0);
        }

        $amountsByBillKey = [];

        foreach ($rows as $index => $row) {
            $billType = (string) ($row['bill_type'] ?? PurchaseBill::class);
            $billId = (int) ($row['bill_id'] ?? 0);
            $amount = (float) ($row['amount'] ?? 0);

            if ($billId <= 0 || $amount <= 0) {
                continue;
            }

            $bill = null;
            if ($billType === PurchaseBill::class && $supplierParty) {
                $bill = PurchaseBill::query()
                    ->where('company_id', $companyId)
                    ->where('supplier_id', (int) $supplierParty->id)
                    ->where('status', 'posted')
                    ->whereDate('bill_date', '<=', $asOf->toDateString())
                    ->where('id', $billId)
                    ->first();
            } elseif ($billType === SubcontractorRaBill::class && $subcontractorParty) {
                $bill = SubcontractorRaBill::query()
                    ->where('company_id', $companyId)
                    ->where('subcontractor_id', (int) $subcontractorParty->id)
                    ->where('status', 'posted')
                    ->whereDate('bill_date', '<=', $asOf->toDateString())
                    ->where('id', $billId)
                    ->first();
            }

            if (! $bill) {
                throw ValidationException::withMessages([
                    "purchase_allocations.$index.bill_id" => 'Invalid payable bill selected.',
                ]);
            }

            $billKey = $billType . ':' . $billId;
            $amountsByBillKey[$billKey] = ($amountsByBillKey[$billKey] ?? 0.0) + $amount;
            $open = (float) ($outstandingMap[$billKey] ?? 0.0);

            if ($amountsByBillKey[$billKey] - $open > 0.01) {
                throw ValidationException::withMessages([
                    "purchase_allocations.$index.amount" => 'Allocation amount exceeds outstanding for bill ' . ($bill->bill_number ?? $bill->ra_number ?? ('#' . $bill->id)) . '.',
                ]);
            }
        }

        foreach ($amountsByBillKey as $billKey => $amount) {
            [$billType, $billId] = explode(':', $billKey, 2);
            $normalized[] = [
                'bill_type' => $billType,
                'bill_id'   => (int) $billId,
                'amount'    => round((float) $amount, 2),
            ];

            $totalAlloc += (float) $amount;
        }

        if ($totalAlloc - $voucherAmount > 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Total bill allocations (' . number_format($totalAlloc, 2) . ') cannot exceed voucher amount (' . number_format($voucherAmount, 2) . ').',
            ]);
        }

        return $normalized;
    }

    public function storeOnAccountForPayment(VoucherLine $supplierLine, float $amount): void
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            return;
        }

        $voucher = $supplierLine->voucher;
        $companyId = (int) ($voucher->company_id ?? $supplierLine->account->company_id ?? 1);
        $allocDate = $voucher && $voucher->voucher_date
            ? Carbon::parse($voucher->voucher_date)->toDateString()
            : now()->toDateString();

        AccountBillAllocation::updateOrCreate(
            [
                'voucher_line_id' => $supplierLine->id,
                'bill_type'       => self::ON_ACCOUNT_BILL_TYPE,
                'bill_id'         => 0,
                'mode'            => self::MODE_ON_ACCOUNT,
                'allocation_date' => $allocDate,
            ],
            [
                'company_id'      => $companyId,
                'voucher_id'      => $voucher->id,
                'account_id'      => $supplierLine->account_id,
                'amount'          => $amount,
            ]
        );
    }

    public function storeAdvanceForPayment(VoucherLine $supplierLine, PurchaseOrder $purchaseOrder, float $amount): void
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            return;
        }

        $voucher = $supplierLine->voucher;
        $companyId = (int) ($voucher->company_id ?? $supplierLine->account->company_id ?? 1);
        $allocDate = $voucher && $voucher->voucher_date
            ? Carbon::parse($voucher->voucher_date)->toDateString()
            : now()->toDateString();

        AccountBillAllocation::updateOrCreate(
            [
                'voucher_line_id' => $supplierLine->id,
                'bill_type'       => PurchaseOrder::class,
                'bill_id'         => (int) $purchaseOrder->id,
                'mode'            => self::MODE_ADVANCE,
                'allocation_date' => $allocDate,
            ],
            [
                'company_id'      => $companyId,
                'voucher_id'      => $voucher->id,
                'account_id'      => $supplierLine->account_id,
                'amount'          => $amount,
            ]
        );
    }

    public function storeAdvanceForSubcontractorPayment(VoucherLine $partyLine, SubcontractorWorkOrder $workOrder, float $amount): void
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            return;
        }

        $voucher = $partyLine->voucher;
        $companyId = (int) ($voucher->company_id ?? $partyLine->account->company_id ?? 1);
        $allocDate = $voucher && $voucher->voucher_date
            ? Carbon::parse($voucher->voucher_date)->toDateString()
            : now()->toDateString();

        AccountBillAllocation::updateOrCreate(
            [
                'voucher_line_id' => $partyLine->id,
                'bill_type'       => SubcontractorWorkOrder::class,
                'bill_id'         => (int) $workOrder->id,
                'mode'            => self::MODE_ADVANCE,
                'allocation_date' => $allocDate,
            ],
            [
                'company_id'      => $companyId,
                'voucher_id'      => $voucher->id,
                'account_id'      => $partyLine->account_id,
                'amount'          => $amount,
            ]
        );
    }

    /**
     * Persist payable-bill allocations for a payment voucher line.
     *
     * @param  array<int, array{bill_type: string, bill_id: int, amount: float}>  $rows
     */
    public function storePurchaseAllocationsForPayment(VoucherLine $partyLine, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $voucher   = $partyLine->voucher;
        $companyId = (int) ($voucher->company_id ?? $partyLine->account->company_id ?? 1);
        $allocAsOf = $voucher && $voucher->voucher_date
            ? Carbon::parse($voucher->voucher_date)->startOfDay()
            : now()->startOfDay();
        $allocDate = $allocAsOf->toDateString();
        $partyAccount = $partyLine->account;
        $supplierParty = $this->resolveSupplierParty($partyAccount);
        $subcontractorParty = $this->resolveSubcontractorParty($partyAccount);

        foreach (collect($rows)->groupBy('bill_type') as $billType => $groupRows) {
            $billIds = collect($groupRows)
                ->pluck('bill_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($billIds)) {
                continue;
            }

            $bills = match ($billType) {
                PurchaseBill::class => PurchaseBill::query()
                    ->where('company_id', $companyId)
                    ->where('supplier_id', (int) ($supplierParty?->id ?? 0))
                    ->whereIn('id', $billIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id'),
                SubcontractorRaBill::class => SubcontractorRaBill::query()
                    ->where('company_id', $companyId)
                    ->where('subcontractor_id', (int) ($subcontractorParty?->id ?? 0))
                    ->whereIn('id', $billIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id'),
                default => collect(),
            };

            $allocatedMap = $this->sumAllocationsForBillIds(
                $companyId,
                (string) $billType,
                $billIds,
                $allocAsOf,
                [self::MODE_AGAINST]
            );
            $pendingByBillId = [];

            foreach ($groupRows as $row) {
                $billId = (int) ($row['bill_id'] ?? 0);
                $amount = round((float) ($row['amount'] ?? 0), 2);

                if ($billId <= 0 || $amount <= 0) {
                    continue;
                }

                $bill = $bills->get($billId);
                if (! $bill) {
                    throw ValidationException::withMessages([
                        'purchase_allocations' => 'Selected payable bill is invalid for this company.',
                    ]);
                }

                $billAmount = $this->payableBillAmount($bill);
                $pendingByBillId[$billId] = ($pendingByBillId[$billId] ?? 0.0) + $amount;
                $open = max(0.0, $billAmount - (float) ($allocatedMap[$billId] ?? 0.0));

                if ($pendingByBillId[$billId] - $open > 0.01) {
                    throw ValidationException::withMessages([
                        'purchase_allocations' => 'Payable bill outstanding changed before save. Refresh and try again.',
                    ]);
                }
            }
        }

        foreach ($rows as $row) {
            AccountBillAllocation::create([
                'company_id'       => $companyId,
                'voucher_id'       => $voucher->id,
                'voucher_line_id'  => $partyLine->id,
                'account_id'       => $partyLine->account_id,
                'bill_type'        => $row['bill_type'],
                'bill_id'          => $row['bill_id'],
                'mode'             => self::MODE_AGAINST,
                'amount'           => $row['amount'],
                'allocation_date'  => $allocDate,
            ]);
        }
    }

    protected function payableBillAmount(Model $bill): float
    {
        if ($bill instanceof PurchaseBill) {
            return (float) ($bill->total_amount ?? 0)
                + (float) ($bill->tcs_amount ?? 0)
                - (float) ($bill->tds_amount ?? 0);
        }

        if ($bill instanceof SubcontractorRaBill) {
            return (float) ($bill->total_amount ?? 0);
        }

        return 0.0;
    }

    /**
     * Current outstanding for a purchase bill (based only on posted vouchers).
     */
    public function getOutstandingForPurchaseBill(PurchaseBill $bill): float
    {
        $allocated = (float) AccountBillAllocation::query()
            ->join('vouchers', 'vouchers.id', '=', 'account_bill_allocations.voucher_id')
            ->where('account_bill_allocations.company_id', $bill->company_id)
            ->where('account_bill_allocations.bill_type', PurchaseBill::class)
            ->where('account_bill_allocations.bill_id', $bill->id)
            ->where('account_bill_allocations.mode', self::MODE_AGAINST)
            ->where('vouchers.status', 'posted')
            ->sum('account_bill_allocations.amount');

        // Purchase bill payable = Invoice total + TCS - TDS (TCS increases payable; TDS decreases payable)
        $billAmount = (float) ($bill->total_amount ?? 0);
        $billAmount += (float) ($bill->tcs_amount ?? 0);
        $billAmount -= (float) ($bill->tds_amount ?? 0);

        return max(0.0, $billAmount - $allocated);
    }

    // ---------------------------------------------------------------------
    // AR side (Client / RA Bills) – Phase 7
    // ---------------------------------------------------------------------

    /**
     * Resolve AR bill model class from config.
     *
     * @return class-string<Model>|null
     */
    protected function getArBillModelClass(): ?string
    {
        $class = Config::get('accounting.ar_bill_model');

        if (! is_string($class) || $class === '') {
            return null;
        }

        if (! class_exists($class)) {
            return null;
        }

        return $class;
    }

    /**
     * Get amount value for AR bill.
     */
    public function getClientBillAmount(Model $bill): float
    {
        $amount = (float) ($bill->receivable_amount ?? 0.0);

        if ($amount > 0) {
            return $amount;
        }

        $amount = (float) ($bill->total_amount ?? 0.0);
        if ($amount > 0) {
            return $amount;
        }

        // Fallback (helps in some incomplete datasets)
        return (float) ($bill->current_amount ?? 0.0);
    }

    /**
     * Apply a status filter for AR bills.
     */
    protected function applyArBillStatusFilter(Builder $q, string $status): void
    {
        $status = trim($status);

        if ($status === '' || $status === 'all') {
            $q->where(function ($qq) {
                $qq->whereNull('status')
                    ->orWhereNotIn('status', ['cancelled']);
            });

            return;
        }

        $q->where('status', $status);
    }

    /**
     * Get open client bills (with outstanding amount) for a given debtor ledger account.
     *
     * @return Collection<int, array{bill: Model, bill_amount: float, allocated: float, outstanding: float}>
     */
    public function getOpenClientBillsForAccount(Account $account, Carbon|string|null $asOfDate = null, string $billStatus = 'posted'): Collection
    {
        $party = $this->resolveClientParty($account);
        if (! $party) {
            return collect();
        }

        $modelClass = $this->getArBillModelClass();
        if (! $modelClass) {
            return collect();
        }

        $asOf      = $this->asOf($asOfDate);
        $clientId  = (int) $account->related_model_id;
        $companyId = (int) $account->company_id;

        /** @var Builder $q */
        $q = $modelClass::query()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereDate('bill_date', '<=', $asOf->toDateString())
            ->orderBy('bill_date')
            ->orderBy('id');

        $this->applyArBillStatusFilter($q, $billStatus);

        /** @var \Illuminate\Support\Collection<int,Model> $bills */
        $bills = $q->get();

        if ($bills->isEmpty()) {
            return collect();
        }

        $billIds = $bills->pluck('id')->all();

        // Sum ONLY allocations from posted vouchers up to as-of date, and ONLY "against".
        $allocations = $this->sumAllocationsForBillIds(
            $companyId,
            $modelClass,
            $billIds,
            $asOf,
            [self::MODE_AGAINST]
        );

        return $bills->map(function (Model $bill) use ($allocations) {
            $billAmount  = $this->getClientBillAmount($bill);
            $allocated   = (float) ($allocations[$bill->id] ?? 0.0);
            $outstanding = max(0.0, $billAmount - $allocated);

            return [
                'bill'        => $bill,
                'bill_amount' => $billAmount,
                'allocated'   => $allocated,
                'outstanding' => $outstanding,
            ];
        })->filter(fn (array $row) => $row['outstanding'] > 0.0)->values();
    }

    /**
     * Validate allocations entered on a receipt voucher against client bills.
     *
     * @param  array<int, array{bill_id?: int|string|null, amount?: float|string|null}>  $rows
     * @return array<int, array{bill_type: string, bill_id: int, amount: float}>
     *
     * @throws ValidationException
     */
    public function validateClientReceiptAllocations(
        Account $clientAccount,
        float $voucherAmount,
        array $rows,
        Carbon|string|null $asOfDate = null,
        string $billStatus = 'posted'
    ): array {
        $normalized = [];
        $totalAlloc = 0.0;
        $companyId  = (int) $clientAccount->company_id;

        $party = $this->resolveClientParty($clientAccount);
        if (! $party) {
            foreach ($rows as $row) {
                if (! empty($row['bill_id']) && (float) ($row['amount'] ?? 0) > 0) {
                    throw ValidationException::withMessages([
                        'party_account_id' => 'Bill allocations are only supported for Party ledgers linked to a client.',
                    ]);
                }
            }

            return [];
        }

        $modelClass = $this->getArBillModelClass();
        if (! $modelClass) {
            // AR not configured yet. Ignore allocations.
            return [];
        }

        $asOf = $this->asOf($asOfDate);

        // Build a map of open bill outstanding as-of date
        $openBills = $this->getOpenClientBillsForAccount($clientAccount, $asOf, $billStatus);
        $outstandingMap = [];
        foreach ($openBills as $row) {
            /** @var Model $bill */
            $bill = $row['bill'];
            $outstandingMap[(int) $bill->id] = (float) ($row['outstanding'] ?? 0.0);
        }

        $amountsByBillId = [];

        foreach ($rows as $index => $row) {
            $billId = (int) ($row['bill_id'] ?? 0);
            $amount = (float) ($row['amount'] ?? 0);

            if ($billId <= 0 || $amount <= 0) {
                continue;
            }

            if (! array_key_exists($billId, $outstandingMap)) {
                throw ValidationException::withMessages([
                    "receipt_allocations.$index.bill_id" => 'Invalid or closed client bill selected for this as-of date/status.',
                ]);
            }

            $amountsByBillId[$billId] = ($amountsByBillId[$billId] ?? 0.0) + $amount;
            $open = (float) $outstandingMap[$billId];

            if ($amountsByBillId[$billId] - $open > 0.01) {
                throw ValidationException::withMessages([
                    "receipt_allocations.$index.amount" => 'Allocation amount exceeds outstanding for bill #' . $billId . '.',
                ]);
            }
        }

        foreach ($amountsByBillId as $billId => $amount) {
            $normalized[] = [
                'bill_type' => $modelClass,
                'bill_id'   => (int) $billId,
                'amount'    => round((float) $amount, 2),
            ];

            $totalAlloc += (float) $amount;
        }

        if ($totalAlloc - $voucherAmount > 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Total bill allocations (' . number_format($totalAlloc, 2) . ') cannot exceed voucher amount (' . number_format($voucherAmount, 2) . ').',
            ]);
        }

        return $normalized;
    }

    /**
     * Persist client bill allocations for a receipt voucher line (debtor ledger line).
     *
     * @param  array<int, array{bill_type: string, bill_id: int, amount: float}>  $rows
     */
    public function storeClientAllocationsForReceipt(VoucherLine $clientLine, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $voucher   = $clientLine->voucher;
        $companyId = (int) ($voucher->company_id ?? $clientLine->account->company_id ?? 1);
        $allocAsOf = $voucher && $voucher->voucher_date
            ? Carbon::parse($voucher->voucher_date)->startOfDay()
            : now()->startOfDay();
        $allocDate = $allocAsOf->toDateString();
        $billTypes = collect($rows)
            ->pluck('bill_type')
            ->filter(fn ($type) => is_string($type) && $type !== '')
            ->unique()
            ->values();

        if ($billTypes->count() !== 1) {
            throw ValidationException::withMessages([
                'receipt_allocations' => 'Receipt allocations must target a single bill model.',
            ]);
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $billTypes->first();
        $billIds = collect($rows)
            ->pluck('bill_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int,Model> $bills */
        $bills = $modelClass::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $billIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $allocatedMap = $this->sumAllocationsForBillIds(
            $companyId,
            $modelClass,
            $billIds,
            $allocAsOf,
            [self::MODE_AGAINST]
        );
        $pendingByBillId = [];

        foreach ($rows as $row) {
            $billId = (int) ($row['bill_id'] ?? 0);
            $amount = round((float) ($row['amount'] ?? 0), 2);

            if ($billId <= 0 || $amount <= 0) {
                continue;
            }

            $bill = $bills->get($billId);
            if (! $bill) {
                throw ValidationException::withMessages([
                    'receipt_allocations' => 'Selected client bill is invalid for this company.',
                ]);
            }

            $pendingByBillId[$billId] = ($pendingByBillId[$billId] ?? 0.0) + $amount;
            $open = max(0.0, $this->getClientBillAmount($bill) - (float) ($allocatedMap[$billId] ?? 0.0));

            if ($pendingByBillId[$billId] - $open > 0.01) {
                throw ValidationException::withMessages([
                    'receipt_allocations' => 'Client bill outstanding changed before save. Refresh and try again.',
                ]);
            }
        }

        foreach ($rows as $row) {
            AccountBillAllocation::create([
                'company_id'       => $companyId,
                'voucher_id'       => $voucher->id,
                'voucher_line_id'  => $clientLine->id,
                'account_id'       => $clientLine->account_id,
                'bill_type'        => $row['bill_type'],
                'bill_id'          => $row['bill_id'],
                'mode'             => self::MODE_AGAINST,
                'amount'           => $row['amount'],
                'allocation_date'  => $allocDate,
            ]);
        }
    }

    /**
     * Current outstanding for a client bill (based only on posted vouchers).
     */
    public function getOutstandingForClientBill(Model $bill): float
    {
        $modelClass = $bill::class;

        $allocated = (float) AccountBillAllocation::query()
            ->join('vouchers', 'vouchers.id', '=', 'account_bill_allocations.voucher_id')
            ->where('account_bill_allocations.company_id', (int) ($bill->company_id ?? 1))
            ->where('account_bill_allocations.bill_type', $modelClass)
            ->where('account_bill_allocations.bill_id', (int) $bill->id)
            ->where('account_bill_allocations.mode', self::MODE_AGAINST)
            ->where('vouchers.status', 'posted')
            ->sum('account_bill_allocations.amount');

        $billAmount = $this->getClientBillAmount($bill);

        return max(0.0, $billAmount - $allocated);
    }

    // ---------------------------------------------------------------------
    // On-Account receipts (Phase 7) + Apply On-Account (Phase 8)
    // ---------------------------------------------------------------------

    /**
     * Store (or update) an On-Account allocation row for a receipt's party line.
     *
     * This represents unallocated receipt amount (advance from client).
     */
    public function storeOnAccountForReceipt(VoucherLine $clientLine, float $amount): void
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            return;
        }

        $voucher = $clientLine->voucher;
        $companyId = (int) ($voucher->company_id ?? $clientLine->account->company_id ?? 1);
        $allocDate = $voucher && $voucher->voucher_date ? Carbon::parse($voucher->voucher_date)->toDateString() : now()->toDateString();

        // One on-account row per voucher_line per allocation_date.
        AccountBillAllocation::updateOrCreate(
            [
                'voucher_line_id' => $clientLine->id,
                'bill_type'       => self::ON_ACCOUNT_BILL_TYPE,
                'bill_id'         => 0,
                'mode'            => self::MODE_ON_ACCOUNT,
                'allocation_date' => $allocDate,
            ],
            [
                'company_id'      => $companyId,
                'voucher_id'      => $voucher->id,
                'account_id'      => $clientLine->account_id,
                'amount'          => $amount,
            ]
        );
    }

    /**
     * Total On-Account (unallocated receipts) balance for a debtor ledger as-of date.
     */
    public function getOnAccountReceiptsAsOf(Account $clientAccount, Carbon|string|null $asOfDate = null): float
    {
        $asOf = $this->asOf($asOfDate);

        return (float) AccountBillAllocation::query()
            ->join('vouchers', 'vouchers.id', '=', 'account_bill_allocations.voucher_id')
            ->where('account_bill_allocations.company_id', (int) $clientAccount->company_id)
            ->where('account_bill_allocations.account_id', (int) $clientAccount->id)
            ->where('account_bill_allocations.bill_type', self::ON_ACCOUNT_BILL_TYPE)
            ->where('account_bill_allocations.mode', self::MODE_ON_ACCOUNT)
            ->whereDate('account_bill_allocations.allocation_date', '<=', $asOf->toDateString())
            ->where('vouchers.status', 'posted')
            ->sum('account_bill_allocations.amount');
    }

    /**
     * On-Account available for a specific receipt party line, as-of date.
     */
    public function getOnAccountAvailableForVoucherLine(VoucherLine $clientLine, Carbon|string|null $asOfDate = null): float
    {
        $asOf = $this->asOf($asOfDate);

        return (float) AccountBillAllocation::query()
            ->join('vouchers', 'vouchers.id', '=', 'account_bill_allocations.voucher_id')
            ->where('account_bill_allocations.voucher_line_id', (int) $clientLine->id)
            ->where('account_bill_allocations.bill_type', self::ON_ACCOUNT_BILL_TYPE)
            ->where('account_bill_allocations.mode', self::MODE_ON_ACCOUNT)
            ->whereDate('account_bill_allocations.allocation_date', '<=', $asOf->toDateString())
            ->where('vouchers.status', 'posted')
            ->sum('account_bill_allocations.amount');
    }

    /**
     * List receipt party lines that have On-Account balance as-of date.
     *
     * @return Collection<int, array{voucher_line: VoucherLine, voucher_no: string|null, voucher_date: string|null, on_account: float}>
     */
    public function listOnAccountReceiptLines(Account $clientAccount, Carbon|string|null $asOfDate = null): Collection
    {
        $asOf = $this->asOf($asOfDate);

        $rows = AccountBillAllocation::query()
            ->select(
                'account_bill_allocations.voucher_line_id',
                DB::raw('SUM(account_bill_allocations.amount) as on_account')
            )
            ->join('vouchers', 'vouchers.id', '=', 'account_bill_allocations.voucher_id')
            ->where('account_bill_allocations.company_id', (int) $clientAccount->company_id)
            ->where('account_bill_allocations.account_id', (int) $clientAccount->id)
            ->where('account_bill_allocations.bill_type', self::ON_ACCOUNT_BILL_TYPE)
            ->where('account_bill_allocations.mode', self::MODE_ON_ACCOUNT)
            ->whereDate('account_bill_allocations.allocation_date', '<=', $asOf->toDateString())
            ->where('vouchers.status', 'posted')
            ->groupBy('account_bill_allocations.voucher_line_id')
            ->havingRaw('ABS(SUM(account_bill_allocations.amount)) > 0.009')
            ->orderBy('account_bill_allocations.voucher_line_id')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $vlineIds = $rows->pluck('voucher_line_id')->map(fn ($x) => (int) $x)->values()->all();

        /** @var \Illuminate\Support\Collection<int,VoucherLine> $lines */
        $lines = VoucherLine::with('voucher')
            ->whereIn('id', $vlineIds)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($rows as $r) {
            $line = $lines->get((int) $r->voucher_line_id);
            if (! $line || ! $line->voucher) {
                continue;
            }

            $out[] = [
                'voucher_line' => $line,
                'voucher_no'   => $line->voucher->voucher_no ?? null,
                'voucher_date' => $line->voucher->voucher_date ? Carbon::parse($line->voucher->voucher_date)->toDateString() : null,
                'on_account'   => (float) $r->on_account,
            ];
        }

        return collect($out);
    }

    /**
     * Apply On-Account balance of a receipt voucher line to selected client bills.
     *
     * @param  array<int, array{bill_id?: int|string|null, amount?: float|string|null}>  $rows
     * @throws ValidationException
     */
    public function applyOnAccountToClientBills(
        VoucherLine $clientLine,
        Carbon|string $allocationDate,
        array $rows,
        string $billStatus = 'posted'
    ): void {
        $voucher = $clientLine->voucher;

        if (! $voucher || $voucher->status !== 'posted') {
            throw ValidationException::withMessages([
                'receipt' => 'Selected receipt voucher must be POSTED before applying On-Account.',
            ]);
        }

        $allocDate = $this->toDateString($allocationDate);
        $receiptDate = $voucher->voucher_date ? Carbon::parse($voucher->voucher_date)->toDateString() : null;

        if ($receiptDate && $allocDate < $receiptDate) {
            throw ValidationException::withMessages([
                'allocation_date' => 'Allocation date cannot be before the receipt date (' . $receiptDate . ').',
            ]);
        }

        // Enforce monotonic allocation dates for this receipt line (prevents back-dated reallocation).
        $maxExisting = AccountBillAllocation::where('voucher_line_id', (int) $clientLine->id)
            ->max('allocation_date');

        if ($maxExisting) {
            $maxExistingStr = Carbon::parse($maxExisting)->toDateString();
            if ($allocDate < $maxExistingStr) {
                throw ValidationException::withMessages([
                    'allocation_date' => 'Allocation date must be on/after last allocation date for this receipt (' . $maxExistingStr . ').',
                ]);
            }
        }

        $account = $clientLine->account;
        if (! $account) {
            throw ValidationException::withMessages([
                'receipt' => 'Invalid receipt line/account.',
            ]);
        }

        // Validate client ledger
        if ($account->related_model_type !== Party::class || empty($account->related_model_id)) {
            throw ValidationException::withMessages([
                'party_account_id' => 'On-Account can only be applied for debtor ledgers linked to a client Party.',
            ]);
        }

        /** @var Party|null $party */
        $party = $account->relatedModel;
        if (! $party || ! $party->is_client) {
            throw ValidationException::withMessages([
                'party_account_id' => 'On-Account can only be applied for client Parties.',
            ]);
        }

        $modelClass = $this->getArBillModelClass();
        if (! $modelClass) {
            throw ValidationException::withMessages([
                'party_account_id' => 'Client bill model is not configured (accounting.ar_bill_model).',
            ]);
        }

        // Normalize rows, and validate against open bills as-of allocation_date.
        $asOf = Carbon::parse($allocDate)->startOfDay();
        $openBills = $this->getOpenClientBillsForAccount($account, $asOf, $billStatus);
        $openMap = [];
        foreach ($openBills as $row) {
            /** @var Model $b */
            $b = $row['bill'];
            $openMap[(int) $b->id] = (float) ($row['outstanding'] ?? 0.0);
        }

        $normalized = [];
        $totalApply = 0.0;
        $amountsByBillId = [];

        foreach ($rows as $i => $row) {
            $billId = (int) ($row['bill_id'] ?? 0);
            $amt    = (float) ($row['amount'] ?? 0);

            if ($billId <= 0 || $amt <= 0) {
                continue;
            }

            if (! array_key_exists($billId, $openMap)) {
                throw ValidationException::withMessages([
                    "apply.$i.bill_id" => 'Invalid or closed bill selected for this allocation date/status.',
                ]);
            }

            $amountsByBillId[$billId] = ($amountsByBillId[$billId] ?? 0.0) + $amt;
            $open = (float) $openMap[$billId];
            if ($amountsByBillId[$billId] - $open > 0.01) {
                throw ValidationException::withMessages([
                    "apply.$i.amount" => 'Amount exceeds outstanding for bill #' . $billId . '.',
                ]);
            }
        }

        foreach ($amountsByBillId as $billId => $amount) {
            $normalized[] = [
                'bill_type' => $modelClass,
                'bill_id'   => (int) $billId,
                'amount'    => round((float) $amount, 2),
            ];
            $totalApply += (float) $amount;
        }

        if ($totalApply <= 0) {
            throw ValidationException::withMessages([
                'apply' => 'Please enter at least one allocation amount.',
            ]);
        }

        DB::transaction(function () use ($clientLine, $allocDate, $normalized, $totalApply, $asOf, $modelClass) {
            // Lock all allocations for this voucher line (concurrency safety)
            $locked = AccountBillAllocation::where('voucher_line_id', (int) $clientLine->id)
                ->lockForUpdate()
                ->get();

            // Compute available On-Account as-of allocation_date from locked rows
            $available = 0.0;
            foreach ($locked as $a) {
                if ($a->bill_type === self::ON_ACCOUNT_BILL_TYPE && $a->mode === self::MODE_ON_ACCOUNT) {
                    $d = $a->allocation_date ? Carbon::parse($a->allocation_date)->toDateString() : null;
                    if ($d && $d <= $asOf->toDateString()) {
                        $available += (float) $a->amount;
                    }
                }
            }

            if ($totalApply - $available > 0.01) {
                throw ValidationException::withMessages([
                    'apply' => 'Total allocation (' . number_format($totalApply, 2) . ') exceeds available On-Account (' . number_format($available, 2) . ') for this receipt.',
                ]);
            }

            $voucher   = $clientLine->voucher;
            $companyId = (int) ($voucher->company_id ?? $clientLine->account->company_id ?? 1);
            $billIds   = collect($normalized)
                ->pluck('bill_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            /** @var Collection<int,Model> $bills */
            $bills = $modelClass::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $billIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $allocatedMap = $this->sumAllocationsForBillIds(
                $companyId,
                $modelClass,
                $billIds,
                $asOf,
                [self::MODE_AGAINST]
            );
            $pendingByBillId = [];

            foreach ($normalized as $row) {
                $billId = (int) ($row['bill_id'] ?? 0);
                $amount = round((float) ($row['amount'] ?? 0), 2);

                if ($billId <= 0 || $amount <= 0) {
                    continue;
                }

                $bill = $bills->get($billId);
                if (! $bill) {
                    throw ValidationException::withMessages([
                        'apply' => 'Selected client bill no longer exists for this company.',
                    ]);
                }

                $pendingByBillId[$billId] = ($pendingByBillId[$billId] ?? 0.0) + $amount;
                $open = max(0.0, $this->getClientBillAmount($bill) - (float) ($allocatedMap[$billId] ?? 0.0));

                if ($pendingByBillId[$billId] - $open > 0.01) {
                    throw ValidationException::withMessages([
                        'apply' => 'Client bill outstanding changed before apply. Refresh and try again.',
                    ]);
                }
            }

            // Create "against" allocations
            foreach ($normalized as $row) {
                AccountBillAllocation::create([
                    'company_id'       => $companyId,
                    'voucher_id'       => $voucher->id,
                    'voucher_line_id'  => $clientLine->id,
                    'account_id'       => $clientLine->account_id,
                    'bill_type'        => $row['bill_type'],
                    'bill_id'          => $row['bill_id'],
                    'mode'             => self::MODE_AGAINST,
                    'amount'           => $row['amount'],
                    'allocation_date'  => $allocDate,
                ]);
            }

            // Store negative On-Account adjustment for the same allocation_date
            $key = [
                'voucher_line_id' => $clientLine->id,
                'bill_type'       => self::ON_ACCOUNT_BILL_TYPE,
                'bill_id'         => 0,
                'mode'            => self::MODE_ON_ACCOUNT,
                'allocation_date' => $allocDate,
            ];

            $existing = AccountBillAllocation::where($key)->first();
            $delta    = -round((float) $totalApply, 2);

            if ($existing) {
                $existing->amount = round(((float) $existing->amount) + $delta, 2);
                $existing->save();
            } else {
                AccountBillAllocation::create(array_merge($key, [
                    'company_id'      => $companyId,
                    'voucher_id'      => $voucher->id,
                    'account_id'      => $clientLine->account_id,
                    'amount'          => $delta,
                ]));
            }
        });
    }
}
