<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use App\Models\MaterialReceiptLine;
use App\Models\PurchaseBillLine;
use App\Models\StoreStockAdjustment;
use App\Models\StoreStockAdjustmentLine;
use App\Models\StoreStockItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Posting service for Store Stock Adjustments.
 *
 * Supported (valued) adjustment types:
 * - increase: Dr Inventory, Cr Stock Adjustment Gain
 * - decrease: Dr Stock Adjustment Loss, Cr Inventory
 *
 * Notes:
 * - Opening adjustments can be posted ONLY if each opening stock row has opening_unit_rate filled.
 * - Client-supplied material: NO accounting (quantity-only)
 */
class StoreStockAdjustmentPostingService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService
    ) {
    }

    /**
     * @return Voucher|null Returns a Voucher when a valued posting is created.
     *                      Returns null when no accounting entry is required.
     *
     * @throws RuntimeException
     */
    public function post(StoreStockAdjustment $adjustment): ?Voucher
    {
        if (! Config::get('accounting.enable_store_issue_posting', false)) {
            throw new RuntimeException(
                'Posting Stock Adjustments to Accounts is disabled until the Accounts module is configured.'
            );
        }

        if (($adjustment->accounting_status ?? null) === 'not_required') {
            return null;
        }

        $adjustment->loadMissing('lines', 'lines.stockItem', 'lines.stockItem.item', 'lines.item');

        if ($adjustment->lines->isEmpty()) {
            throw new RuntimeException('Cannot post a stock adjustment without lines.');
        }

        $companyId = (int) Config::get('accounting.default_company_id', 1);

        // Resolve accounts
        $factoryExpCode = Config::get('accounting.store.factory_consumable_expense_account_code');
        if (! $factoryExpCode) {
            throw new RuntimeException('Config accounting.store.factory_consumable_expense_account_code is not set.');
        }

        $factoryExpenseAccount = Account::where('code', $factoryExpCode)->first();
        if (! $factoryExpenseAccount) {
            throw new RuntimeException('Factory consumable expense account not found for code: ' . $factoryExpCode);
        }

        $gainCode = Config::get('accounting.store.stock_adjustment_gain_account_code');
        $lossCode = Config::get('accounting.store.stock_adjustment_loss_account_code');

        $gainAccount = $gainCode ? Account::where('code', $gainCode)->first() : null;
        $lossAccount = $lossCode ? Account::where('code', $lossCode)->first() : null;

        // Fallbacks (keeps system working even if accounts mapping isn't configured yet)
        $gainAccount = $gainAccount ?: $factoryExpenseAccount;
        $lossAccount = $lossAccount ?: $factoryExpenseAccount;

        return DB::transaction(function () use ($adjustment, $companyId, $gainAccount, $lossAccount) {
            // Lock header to avoid double posting
            $adjustment = StoreStockAdjustment::whereKey($adjustment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! empty($adjustment->voucher_id)) {
                $existing = Voucher::find($adjustment->voucher_id);
                if ($existing) {
                    return $existing;
                }
                throw new RuntimeException('Stock adjustment is already linked to a voucher, but the voucher was not found.');
            }

            $adjType = (string) ($adjustment->adjustment_type ?? 'opening');

            $isOpening  = $adjType === 'opening';
            $isIncrease = $adjType === 'increase';
            $isDecrease = $adjType === 'decrease';

            if (! $isOpening && ! $isIncrease && ! $isDecrease) {
                throw new RuntimeException('Unsupported adjustment type for posting: ' . $adjType);
            }

            $adjustment->loadMissing('lines', 'lines.stockItem', 'lines.stockItem.item', 'lines.item');

            // Group amounts by debit/credit account
            $debits  = []; // [account_id => amount]
            $credits = []; // [account_id => amount]

            // Special case: Opening (valued by opening_unit_rate)
            $openingOffsetAccount = null;
            if ($isOpening) {
                $openingOffsetAccount = $this->getOrCreateOpeningAdjustmentAccount($companyId);
            }

            foreach ($adjustment->lines as $line) {
                /** @var StoreStockAdjustmentLine $line */
                $stockItem = $line->stockItem;

                if (! $stockItem instanceof StoreStockItem) {
                    continue;
                }

                // Skip client-supplied material (non-valued)
                if ($stockItem->is_client_material) {
                    continue;
                }

                if ($isOpening) {
                    $amount = $this->resolveOpeningValue($line, $stockItem);

                    if ($amount <= 0) {
                        $itemLabel = $stockItem->item?->name ?? ($line->item?->name ?? ('Item #' . ($line->item_id ?? '?')));
                        throw new RuntimeException(
                            'Opening stock line #' . $line->id . ' (' . $itemLabel . ') has no value. ' .
                            'Please enter Unit Rate in Stock Adjustment / Opening so it can be posted to accounts.'
                        );
                    }
                } else {
                    $amount = $this->resolveAdjustmentValue($line, $stockItem);
                    if ($amount <= 0) {
                        $itemLabel = $stockItem->item?->name ?? ($line->item?->name ?? ('Item #' . ($line->item_id ?? '?')));
                        throw new RuntimeException(
                            'Cannot resolve value for Stock Adjustment line #' . $line->id . ' (' . $itemLabel . '). ' .
                            'Ensure the linked GRN/Purchase Bill is posted with basic amount.'
                        );
                    }
                }

                // Inventory account
                $item             = $stockItem->item ?? $line->item;
                $inventoryAccount = null;

                if ($item && $item->inventory_account_id) {
                    $inventoryAccount = Account::find($item->inventory_account_id);
                }

                if (! $inventoryAccount) {
                    $invCode = Config::get('accounting.store.inventory_consumables_account_code');
                    if (! $invCode) {
                        throw new RuntimeException(
                            'Inventory account not found for Stock Adjustment line ID ' . $line->id .
                            '; please configure accounting.store.inventory_consumables_account_code or set inventory_account_id on item.'
                        );
                    }
                    $inventoryAccount = Account::where('code', $invCode)->first();
                    if (! $inventoryAccount) {
                        throw new RuntimeException('Inventory account not found for code: ' . $invCode);
                    }
                    // Guardrail: the selected inventory ledger MUST be an ASSET group.
                    $inventoryAccount->loadMissing('group');
                    if ((string) ($inventoryAccount->group?->nature ?? '') !== 'asset') {
                        throw new RuntimeException(
                            'Inventory ledger ' . ($inventoryAccount->code ?? '') . ' must be under an ASSET group (e.g., Inventory). ' .
                            'Currently it is under "' . ($inventoryAccount->group?->name ?? 'unknown') . '" (' . ($inventoryAccount->group?->nature ?? 'unknown') . '). ' .
                            'Please fix Chart of Accounts for this ledger.'
                        );
                    }

                }

                if ($isOpening) {
                    // Dr Inventory, Cr Opening Balance Adjustment (Equity)
                    $debits[$inventoryAccount->id] = ($debits[$inventoryAccount->id] ?? 0) + $amount;
                    $credits[$openingOffsetAccount->id] = ($credits[$openingOffsetAccount->id] ?? 0) + $amount;
                } elseif ($isIncrease) {
                    // Dr Inventory, Cr Gain
                    $debits[$inventoryAccount->id] = ($debits[$inventoryAccount->id] ?? 0) + $amount;
                    $credits[$gainAccount->id]     = ($credits[$gainAccount->id] ?? 0) + $amount;
                } else {
                    // decrease: Dr Loss, Cr Inventory
                    $debits[$lossAccount->id]      = ($debits[$lossAccount->id] ?? 0) + $amount;
                    $credits[$inventoryAccount->id]= ($credits[$inventoryAccount->id] ?? 0) + $amount;
                }
            }

            if (empty($debits) && empty($credits)) {
                // No valued lines (e.g., client-supplied material only). Mark as not required.
                $adjustment->accounting_status    = 'not_required';
                $adjustment->accounting_posted_by = Auth::id();
                $adjustment->accounting_posted_at = now();
                $adjustment->save();

                ActivityLog::logCustom(
                    'accounts_posting_not_required',
                    'Stock adjustment ' . ($adjustment->reference_number ?: ('#' . $adjustment->id)) . ' has no own-material lines. No accounting entry required.',
                    $adjustment,
                    [
                        'accounting_status' => 'not_required',
                        'business_date'     => optional($adjustment->adjustment_date)->toDateString(),
                    ]
                );

                return null;
            }

            // Resolve project cost center (Project = Cost Center)
            $costCenterId = null;
            if (! empty($adjustment->project_id)) {
                $costCenterId = ProjectCostCenterResolver::resolveId($companyId, (int) $adjustment->project_id);
            }

            // Create voucher
            $voucher = new Voucher();
            $voucher->company_id     = $companyId;
            $businessDate            = $adjustment->adjustment_date ? Carbon::parse($adjustment->adjustment_date) : now();
            $voucher->voucher_no     = $this->voucherNumberService->next('stock_adjustment', (int) $companyId, $businessDate);
            $voucher->voucher_type   = 'stock_adjustment';
            $voucher->voucher_date   = $businessDate->toDateString();
            $voucher->reference      = $adjustment->reference_number ?: ('STOCK_ADJ#' . $adjustment->id);
            $voucherLabel            = $isOpening ? 'Stock Opening' : 'Stock Adjustment';
            $voucher->narration      = trim($voucherLabel . ' ' . ($adjustment->reference_number ?: ('#' . $adjustment->id)) . ' - ' . (string) ($adjustment->remarks ?? ''));
            $voucher->project_id     = $adjustment->project_id;
            $voucher->cost_center_id = $costCenterId;
            $voucher->currency_id    = null;
            $voucher->exchange_rate  = 1;
            $voucher->status         = 'draft';
            $voucher->created_by     = $adjustment->created_by;
            $voucher->amount_base    = array_sum($debits);
            $voucher->save();

            $lineNo = 1;

            foreach ($debits as $accountId => $amount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $accountId,
                    'cost_center_id' => $costCenterId,
                    'description'    => ($isOpening ? 'Stock Opening' : 'Stock Adjustment') . ' - Debit',
                    'debit'          => round($amount, 2),
                    'credit'         => 0,
                    'reference_type' => StoreStockAdjustment::class,
                    'reference_id'   => $adjustment->id,
                ]);
            }

            foreach ($credits as $accountId => $amount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $accountId,
                    'cost_center_id' => $costCenterId,
                    'description'    => ($isOpening ? 'Stock Opening' : 'Stock Adjustment') . ' - Credit',
                    'debit'          => 0,
                    'credit'         => round($amount, 2),
                    'reference_type' => StoreStockAdjustment::class,
                    'reference_id'   => $adjustment->id,
                ]);
            }

            // Finalize posting
            $voucher->posted_by = Auth::id();
            $voucher->posted_at = now();
            $voucher->status    = 'posted';
            $voucher->save();

            // Link adjustment to voucher
            $adjustment->voucher_id           = $voucher->id;
            $adjustment->accounting_status    = 'posted';
            $adjustment->accounting_posted_by = Auth::id();
            $adjustment->accounting_posted_at = now();
            $adjustment->save();

            ActivityLog::logCustom(
                'posted_to_accounts',
                'Stock adjustment ' . ($adjustment->reference_number ?: ('#' . $adjustment->id)) . ' posted to accounts as voucher ' . $voucher->voucher_no,
                $adjustment,
                [
                    'accounting_status' => 'posted',
                    'voucher_id'        => $voucher->id,
                    'voucher_no'        => $voucher->voucher_no,
                    'business_date'     => optional($adjustment->adjustment_date)->toDateString(),
                ]
            );

            return $voucher;
        });
    }

    /**
     * Resolve value of an OPENING line using opening_unit_rate stored on the stock item.
     *
     * We treat StoreStockAdjustmentLine.quantity as the base qty in item UOM
     * (for store we currently store it in weight_kg_* columns for non-raw).
     */
    protected function resolveOpeningValue(StoreStockAdjustmentLine $line, StoreStockItem $stockItem): float
    {
        $rate = (float) ($stockItem->opening_unit_rate ?? 0);
        if ($rate <= 0) {
            return 0.0;
        }

        $qty = 0.0;
        if (! is_null($line->quantity)) {
            $qty = abs((float) $line->quantity);
        }

        if ($qty <= 0) {
            return 0.0;
        }

        return round($rate * $qty, 2);
    }

    /**
     * Ensure "Opening Balance Adjustment" account exists (used for stock opening valuation posting).
     */
    protected function getOrCreateOpeningAdjustmentAccount(int $companyId): Account
    {
        $code = (string) data_get(config('accounting.default_accounts', []), 'opening_balance_adjustment_code', 'OPENING-ADJ');
        $code = trim($code) !== '' ? trim($code) : 'OPENING-ADJ';

        $existing = Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Prefer Equity group
        $group = AccountGroup::where('company_id', $companyId)
            ->where('code', 'EQUITY')
            ->first();

        if (! $group) {
            $group = AccountGroup::where('company_id', $companyId)
                ->where('nature', 'equity')
                ->orderBy('id')
                ->first();
        }

        if (! $group) {
            $group = AccountGroup::where('company_id', $companyId)->orderBy('id')->firstOrFail();
        }

        return Account::create([
            'company_id'            => $companyId,
            'account_group_id'      => $group->id,
            'name'                  => 'Opening Balance Adjustment',
            'code'                  => $code,
            'type'                  => 'ledger',
            'opening_balance'       => 0,
            'opening_balance_type'  => 'dr',
            'opening_balance_date'  => null,
            'is_active'             => true,
            'is_system'             => true,
            'system_key'            => 'opening_balance_adjustment',
        ]);
    }

    /**
     * Resolve value of a stock adjustment line using avg rate from the linked GRN/Purchase Bill.
     */
    protected function resolveAdjustmentValue(StoreStockAdjustmentLine $line, StoreStockItem $stockItem): float
    {
        $mrLineId = $stockItem->material_receipt_line_id ?? null;
        if (! $mrLineId) {
            return 0.0;
        }

        $mrLine = MaterialReceiptLine::find($mrLineId);
        if (! $mrLine) {
            return 0.0;
        }

        $totalBasic = (float) PurchaseBillLine::where('material_receipt_line_id', $mrLineId)->sum('basic_amount');
        if ($totalBasic <= 0) {
            return 0.0;
        }

        $receivedWeight = (float) ($mrLine->received_weight_kg ?? 0);
        $receivedPcs    = (float) ($mrLine->qty_pcs ?? 0);

        $baseQty = 0.0;
        if ($receivedWeight > 0) {
            $baseQty = $receivedWeight;
        } elseif ($receivedPcs > 0) {
            $baseQty = $receivedPcs;
        }

        if ($baseQty <= 0) {
            return 0.0;
        }

        $avgRate = $totalBasic / $baseQty;

        // Adjustment qty: abs(quantity) (prefer weight, fall back to pcs)
        $qty = 0.0;
        if (! is_null($line->quantity)) {
            $qty = abs((float) $line->quantity);
        } elseif (! is_null($line->quantity_pcs)) {
            $qty = abs((float) $line->quantity_pcs);
        }

        if ($qty <= 0) {
            return 0.0;
        }

        $amount = $avgRate * $qty;

        return round($amount, 2);
    }
}



