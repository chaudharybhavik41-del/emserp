<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use App\Models\MaterialReceiptLine;
use App\Models\PurchaseBillLine;
use App\Models\StoreReturn;
use App\Models\StoreReturnLine;
use App\Models\StoreStockItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Posting service for Store Returns (contractor returns material back to store).
 *
 * Accounting rules (reversal of Store Issue):
 * - Own material (is_client_material = false):
 *     Dr Inventory (per item inventory ledger)
 *     Cr Project WIP – Material (if project_id set) OR Cr Factory Consumable Expense (if no project)
 * - Client-supplied material: NO accounting (quantity-only)
 */
class StoreReturnPostingService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService
    ) {
    }

    /**
     * @return Voucher|null Returns a Voucher when a valued posting is created.
     *                      Returns null when no accounting entry is required
     *                      (e.g., client-supplied material only).
     *
     * @throws RuntimeException
     */
    public function post(StoreReturn $return): ?Voucher
    {
        if (! Config::get('accounting.enable_store_issue_posting', false)) {
            throw new RuntimeException(
                'Posting Store Returns to Accounts is disabled until the Accounts module is configured.'
            );
        }

        if (($return->accounting_status ?? null) === 'not_required') {
            return null;
        }

        $return->loadMissing('lines', 'lines.stockItem', 'lines.stockItem.item', 'lines.item');

        if ($return->lines->isEmpty()) {
            throw new RuntimeException('Cannot post a store return without lines.');
        }

        $companyId = (int) Config::get('accounting.default_company_id', 1);
        $projectId = $return->project_id;

        // Resolve configured accounts (same as Store Issue)
        $wipCode        = Config::get('accounting.store.project_wip_material_account_code');
        $factoryExpCode = Config::get('accounting.store.factory_consumable_expense_account_code');

        if (! $wipCode) {
            throw new RuntimeException('Config accounting.store.project_wip_material_account_code is not set.');
        }
        if (! $factoryExpCode) {
            throw new RuntimeException('Config accounting.store.factory_consumable_expense_account_code is not set.');
        }

        $wipAccount            = Account::where('code', $wipCode)->first();
        $factoryExpenseAccount = Account::where('code', $factoryExpCode)->first();

        if (! $wipAccount) {
            throw new RuntimeException('Project WIP account not found for code: ' . $wipCode);
        }
        if (! $factoryExpenseAccount) {
            throw new RuntimeException('Factory consumable expense account not found for code: ' . $factoryExpCode);
        }

        return DB::transaction(function () use ($return, $companyId, $projectId, $wipAccount, $factoryExpenseAccount) {
            // Lock header to avoid double posting in concurrent requests
            $return = StoreReturn::whereKey($return->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! empty($return->voucher_id)) {
                $existing = Voucher::find($return->voucher_id);
                if ($existing) {
                    return $existing;
                }
                throw new RuntimeException('Store return is already linked to a voucher, but the voucher was not found.');
            }

            if (($return->accounting_status ?? null) === 'not_required') {
                return null;
            }

            $return->loadMissing('lines', 'lines.stockItem', 'lines.stockItem.item', 'lines.item');

            // Group amounts by debit/credit account
            $debits  = []; // [account_id => amount]
            $credits = []; // [account_id => amount]

            foreach ($return->lines as $line) {
                /** @var StoreReturnLine $line */
                $stockItem = $line->stockItem;

                if (! $stockItem instanceof StoreStockItem) {
                    continue;
                }

                // Skip client-supplied material (non-valued)
                if ($stockItem->is_client_material) {
                    continue;
                }

                $amount = $this->resolveReturnValue($line, $stockItem);
                if ($amount <= 0) {
                    $itemLabel = $stockItem->item?->name ?? ($line->item?->name ?? ('Item #' . ($line->item_id ?? '?')));
                    throw new RuntimeException(
                        'Cannot resolve value for Store Return line #' . $line->id . ' (' . $itemLabel . '). ' .
                        'Ensure the linked GRN/Purchase Bill is posted with basic amount.'
                    );
                }

                // Determine debit account (inventory)
                $item             = $stockItem->item ?? $line->item;
                $inventoryAccount = null;

                if ($item && $item->inventory_account_id) {
                    $inventoryAccount = Account::find($item->inventory_account_id);
                }

                // Optional fallback: a generic inventory account code can be configured if needed.
                if (! $inventoryAccount) {
                    $invCode = Config::get('accounting.store.inventory_consumables_account_code');
                    if (! $invCode) {
                        throw new RuntimeException(
                            'Inventory account not found for Store Return line ID ' . $line->id .
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

                // Credit account is reversal of Store Issue debit side
                $creditAccount = $projectId ? $wipAccount : $factoryExpenseAccount;

                $debits[$inventoryAccount->id]      = ($debits[$inventoryAccount->id] ?? 0) + $amount;
                $credits[$creditAccount->id]        = ($credits[$creditAccount->id] ?? 0) + $amount;
            }

            if (empty($debits) && empty($credits)) {
                // No valued lines (e.g., client-supplied material only). Mark as not required.
                $return->accounting_status    = 'not_required';
                $return->accounting_posted_by = Auth::id();
                $return->accounting_posted_at = now();
                $return->save();

                ActivityLog::logCustom(
                    'accounts_posting_not_required',
                    'Store return ' . ($return->return_number ?: ('#' . $return->id)) . ' has no own-material lines. No accounting entry required.',
                    $return,
                    [
                        'accounting_status' => 'not_required',
                        'business_date'     => optional($return->return_date)->toDateString(),
                    ]
                );

                return null;
            }

            $costCenterId = $projectId ? ProjectCostCenterResolver::resolveId($companyId, (int) $projectId) : null;

            // Create voucher
            $voucher = new Voucher();
            $voucher->company_id     = $companyId;
            $businessDate            = $return->return_date ? Carbon::parse($return->return_date) : now();
            $voucher->voucher_no     = $this->voucherNumberService->next('store_return', (int) $companyId, $businessDate);
            $voucher->voucher_type   = 'store_return';
            $voucher->voucher_date   = $businessDate->toDateString();
            $voucher->reference      = $return->return_number ?: ('STORE_RETURN#' . $return->id);
            $voucher->narration      = trim('Store Return ' . ($return->return_number ?: ('#' . $return->id)) . ' - ' . (string) ($return->remarks ?? ''));
            $voucher->project_id     = $projectId;
            $voucher->cost_center_id = $costCenterId;
            $voucher->currency_id    = null;
            $voucher->exchange_rate  = 1;
            // IMPORTANT: create voucher as DRAFT, insert lines, then POST.
            $voucher->status         = 'draft';
            $voucher->created_by     = $return->created_by;
            $voucher->amount_base    = array_sum($debits); // total debits == total credits
            $voucher->save();

            $lineNo = 1;

            // Debit (Inventory)
            foreach ($debits as $accountId => $amount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $accountId,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'Store Return - Debit',
                    'debit'          => round($amount, 2),
                    'credit'         => 0,
                    'reference_type' => StoreReturn::class,
                    'reference_id'   => $return->id,
                ]);
            }

            // Credit (WIP / Expense)
            foreach ($credits as $accountId => $amount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $accountId,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'Store Return - Credit',
                    'debit'          => 0,
                    'credit'         => round($amount, 2),
                    'reference_type' => StoreReturn::class,
                    'reference_id'   => $return->id,
                ]);
            }

            // Finalize posting
            $voucher->posted_by = Auth::id();
            $voucher->posted_at = now();
            $voucher->status    = 'posted';
            $voucher->save();

            // Link return to voucher
            $return->voucher_id           = $voucher->id;
            $return->accounting_status    = 'posted';
            $return->accounting_posted_by = Auth::id();
            $return->accounting_posted_at = now();
            $return->save();

            ActivityLog::logCustom(
                'posted_to_accounts',
                'Store return ' . ($return->return_number ?: ('#' . $return->id)) . ' posted to accounts as voucher ' . $voucher->voucher_no,
                $return,
                [
                    'accounting_status' => 'posted',
                    'voucher_id'        => $voucher->id,
                    'voucher_no'        => $voucher->voucher_no,
                    'business_date'     => optional($return->return_date)->toDateString(),
                ]
            );

            return $voucher;
        });
    }

    /**
     * Resolve the value of a store return line based on purchase cost (avg rate from GRN/Purchase Bill).
     */
    protected function resolveReturnValue(StoreReturnLine $line, StoreStockItem $stockItem): float
    {
        $mrLineId = $stockItem->material_receipt_line_id ?? null;
        if (! $mrLineId) {
            // Opening / manual stock: use opening_unit_rate if available
            $rate = (float) ($stockItem->opening_unit_rate ?? 0);
            if ($rate <= 0) {
                return 0.0;
            }

            $retWeight = (float) ($line->returned_weight_kg ?? 0);
            $retPcs    = (float) ($line->returned_qty_pcs ?? 0);

            $retQty = $retWeight > 0 ? $retWeight : $retPcs;
            if ($retQty <= 0) {
                return 0.0;
            }

            return round($rate * $retQty, 2);
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

        // Returned qty (prefer weight, fall back to pieces)
        $retWeight = (float) ($line->returned_weight_kg ?? 0);
        $retPcs    = (float) ($line->returned_qty_pcs ?? 0);

        $retQty = $retWeight > 0 ? $retWeight : $retPcs;
        if ($retQty <= 0) {
            return 0.0;
        }

        $amount = $avgRate * $retQty;

        return round($amount, 2);
    }
}
