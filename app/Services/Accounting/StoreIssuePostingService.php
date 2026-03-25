<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\MaterialReceiptLine;
use App\Models\MachineSpareConsumption;
use App\Models\PurchaseBillLine;
use App\Models\StoreIssue;
use App\Models\StoreIssueLine;
use App\Models\StoreStockItem;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use RuntimeException;

class StoreIssuePostingService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService,
        protected ItemAccountingResolver $itemAccountingResolver
    ) {
    }

    /**
     * Post a store issue into accounting (Project WIP or Factory Expense).
     *
     * Rules (per design freeze):
     * - If store issue is tagged with a project (project_id not null) AND material is own material:
     *     Dr Project WIP – Material/Consumables
     *     Cr Inventory – Consumables / Raw Material
     * - If no project (general / factory use) and material is own material:
     *     Dr Factory / Other Expenses
     *     Cr Inventory – Consumables / Raw Material
     * - If material is client-supplied: NO accounting (quantity-only).
     *
     * This service assumes:
     * - StoreIssue has many StoreIssueLine records (relationship: lines).
     * - StoreIssueLine has store_stock_item_id linking to StoreStockItem.
     * - StoreStockItem has is_client_material flag and material_receipt_line_id.
     * - PurchaseBillLine rows exist for the material_receipt_line_id, from which basic_amount
     *   can be used to derive average cost per kg / per piece.
     *
     * The actual posting is grouped by account to keep vouchers concise.
     *
     * @throws RuntimeException
     */
    /**
     * @return Voucher|null Returns a Voucher when a valued posting is created.
     *                      Returns null when no accounting entry is required
     *                      (e.g., client-supplied material only).
     */
    public function post(StoreIssue $issue): ?Voucher
    {
        if (! Config::get('accounting.enable_store_issue_posting', false)) {
            throw new RuntimeException(
                'Posting Store Issues to Accounts is disabled until the Accounts module is configured.'
            );
        }

        // Idempotency / guardrails
        if (! empty($issue->voucher_id)) {
            $existing = Voucher::find($issue->voucher_id);
            if ($existing) {
                return $existing;
            }

            throw new RuntimeException('Store issue is already linked to a voucher, but the voucher was not found.');
        }

        if (($issue->accounting_status ?? null) === 'not_required') {
            return null;
        }

        $issue->loadMissing('lines', 'lines.stockItem', 'lines.stockItem.item', 'lines.stockItem.item.type', 'lines.item', 'lines.item.type');

        if ($issue->lines->isEmpty()) {
            throw new RuntimeException('Cannot post a store issue without lines.');
        }

        $companyId = Config::get('accounting.default_company_id', 1);
        $projectId = $issue->project_id;
        $isMachineSpareIssue = Schema::hasColumn('store_issues', 'issue_purpose')
            && (string) ($issue->issue_purpose ?? 'general') === 'machine_spare';

        // Resolve configured accounts
        $wipCode        = Config::get('accounting.store.project_wip_material_account_code');
        $wipConsCode    = Config::get('accounting.store.project_wip_consumable_account_code', 'WIP-CONSUMABLES');
        $factoryExpCode = Config::get('accounting.store.factory_consumable_expense_account_code');
        $machineSpareExpCode = Config::get('accounting.store.machine_maintenance_spare_expense_account_code', 'WIP-MACHINE');

        if (! $wipCode) {
            throw new RuntimeException('Config accounting.store.project_wip_material_account_code is not set.');
        }
        if (! $factoryExpCode) {
            throw new RuntimeException('Config accounting.store.factory_consumable_expense_account_code is not set.');
        }
        if ($isMachineSpareIssue && ! $machineSpareExpCode) {
            throw new RuntimeException('Config accounting.store.machine_maintenance_spare_expense_account_code is not set.');
        }

        $wipAccount           = Account::where('company_id', $companyId)->where('code', $wipCode)->first();
        $wipConsAccount       = Account::where('company_id', $companyId)->where('code', $wipConsCode)->first();
        $factoryExpenseAccount = Account::where('company_id', $companyId)->where('code', $factoryExpCode)->first();
        $machineSpareExpenseAccount = $isMachineSpareIssue
            ? Account::where('company_id', $companyId)->where('code', $machineSpareExpCode)->first()
            : null;

        if (! $wipAccount) {
            throw new RuntimeException('Project WIP (Material) account not found for code: ' . $wipCode);
        }
        if (! $wipConsAccount) {
            throw new RuntimeException('Project WIP (Consumables) account not found for code: ' . $wipConsCode);
        }
        if (! $factoryExpenseAccount) {
            throw new RuntimeException('Factory consumable expense account not found for code: ' . $factoryExpCode);
        }
        if ($isMachineSpareIssue && ! $machineSpareExpenseAccount) {
            throw new RuntimeException('Machine maintenance spare expense account not found for code: ' . $machineSpareExpCode);
        }

        return DB::transaction(function () use (
            $issue,
            $companyId,
            $projectId,
            $isMachineSpareIssue,
            $wipAccount,
            $wipConsAccount,
            $factoryExpenseAccount,
            $machineSpareExpenseAccount
        ) {
            // Lock header to avoid double posting in concurrent requests
            $issue = StoreIssue::whereKey($issue->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! empty($issue->voucher_id)) {
                $existing = Voucher::find($issue->voucher_id);
                if ($existing) {
                    return $existing;
                }
                throw new RuntimeException('Store issue is already linked to a voucher, but the voucher was not found.');
            }

            if (($issue->accounting_status ?? null) === 'not_required') {
                return null;
            }

            $issue->loadMissing('lines', 'lines.stockItem', 'lines.stockItem.item', 'lines.stockItem.item.type', 'lines.item', 'lines.item.type');

            // Group amounts by debit/credit account
            $debits  = []; // [account_id => amount]
            $credits = []; // [account_id => amount]

            foreach ($issue->lines as $line) {
                /** @var StoreIssueLine $line */
                $stockItem = $line->stockItem;
                if (! $stockItem instanceof StoreStockItem) {
                    continue;
                }

                // Skip client-supplied material (non-valued)
                if ($stockItem->is_client_material) {
                    continue;
                }

                $amount = $this->resolveIssueValue($line, $stockItem);
                if ($amount <= 0) {
                    $itemLabel = $stockItem->item?->name ?? ($line->item?->name ?? ('Item #' . ($line->item_id ?? '?')));
                    throw new RuntimeException('Cannot resolve value for store issue line #' . $line->id . ' (' . $itemLabel . '). Please ensure the linked GRN/Purchase Bill is posted with basic amount.');
                }

                // Determine credit account (inventory)
                $item             = $stockItem->item ?? $line->item;
                $inventoryAccount = $this->itemAccountingResolver->resolveInventoryHoldingAccount($item, (int) $companyId);

                if (! $inventoryAccount) {
                    $invCode = Config::get('accounting.store.inventory_consumables_account_code')
                        ?: (Config::get('accounting.default_accounts.inventory_raw_material_code') ?: 'INV-RM');
                    if (! $invCode) {
                        throw new RuntimeException(
                            'Inventory account not found for store issue line ID ' . $line->id .
                            '; please configure inventory defaults or set inventory_account_id on item/subcategory.'
                        );
                    }
                    $inventoryAccount = Account::where('company_id', $companyId)->where('code', $invCode)->first();
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

                // Determine debit account based on project or factory usage
                $debitAccount = $isMachineSpareIssue
                    ? $machineSpareExpenseAccount
                    : ($projectId ? $this->resolveWipAccount($item, $wipAccount, $wipConsAccount) : $factoryExpenseAccount);

                // Grouped debits/credits
                $debits[$debitAccount->id]        = ($debits[$debitAccount->id] ?? 0) + $amount;
                $credits[$inventoryAccount->id]   = ($credits[$inventoryAccount->id] ?? 0) + $amount;
            }

            if (empty($debits) && empty($credits)) {
                // No valued lines (e.g., client-supplied material only). Mark as not required.
                $issue->accounting_status    = 'not_required';
                $issue->accounting_posted_by = Auth::id();
                $issue->accounting_posted_at = now();
                $issue->save();

                ActivityLog::logCustom(
                    'accounts_posting_not_required',
                    'Store issue ' . ($issue->issue_number ?: ('#' . $issue->id)) . ' has no own-material lines. No accounting entry required.',
                    $issue,
                    [
                        'accounting_status' => 'not_required',
                        'business_date'     => optional($issue->issue_date)->toDateString(),
                    ]
                );

                return null;
            }

            $costCenterId = $projectId ? ProjectCostCenterResolver::resolveId($companyId, (int) $projectId) : null;

            // Create voucher
            $voucher = new Voucher();
            $voucher->company_id    = $companyId;
            $businessDate           = $issue->issue_date ? Carbon::parse($issue->issue_date) : now();
            // Centralised voucher numbering (Phase 5a)
            $voucher->voucher_no    = $this->voucherNumberService->next('store_issue', (int) $companyId, $businessDate);
            $voucher->voucher_type  = 'store_issue';
            $voucher->voucher_date  = $businessDate;
            $voucher->reference     = $issue->issue_number ?: ('STORE_ISSUE#' . $issue->id);
            $voucher->narration     = trim('Store Issue ' . $issue->issue_number . ' - ' . (string) $issue->remarks);
            $voucher->project_id    = $projectId;
            $voucher->cost_center_id = $costCenterId;
            $voucher->currency_id   = null;
            $voucher->exchange_rate = 1;
            // IMPORTANT (Phase 5b): create voucher as DRAFT, insert lines, then POST.
            // This allows the Voucher model guardrail to validate Dr == Cr at posting time.
            $voucher->status        = 'draft';
            $voucher->created_by    = $issue->created_by;
            $voucher->amount_base   = array_sum($debits); // total debits == total credits
            $voucher->save();

            $lineNo = 1;
            $hasVoucherLineMachineDimension = Schema::hasColumn('voucher_lines', 'machine_id');
            $issueMachineId = $this->resolveIssueMachineId($issue);

            // Post debit lines
            foreach ($debits as $accountId => $amount) {
                $lineData = [
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $accountId,
                    'cost_center_id' => $costcenterId ?? $costCenterId, // fixed typo if any
                    'description'    => 'Store Issue - Debit',
                    'debit'          => round($amount, 2),
                    'credit'         => 0,
                    'reference_type' => StoreIssue::class,
                    'reference_id'   => $issue->id,
                ];

                if ($hasVoucherLineMachineDimension) {
                    $lineData['machine_id'] = $issueMachineId;
                }

                VoucherLine::create($lineData);
            }

            // Post credit lines
            foreach ($credits as $accountId => $amount) {
                $lineData = [
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $accountId,
                    'cost_center_id' => $costcenterId ?? $costCenterId,
                    'description'    => 'Store Issue - Credit',
                    'debit'          => 0,
                    'credit'         => round($amount, 2),
                    'reference_type' => StoreIssue::class,
                    'reference_id'   => $issue->id,
                ];

                if ($hasVoucherLineMachineDimension) {
                    $lineData['machine_id'] = $issueMachineId;
                }

                VoucherLine::create($lineData);
            }

            // Finalize posting (validates balance + normalizes amount_base)
            $voucher->posted_by = Auth::id();
            $voucher->posted_at = now();
            $voucher->status    = 'posted';
            $voucher->save();

            // Link the issue to the created voucher
            $issue->voucher_id           = $voucher->id;
            $issue->accounting_status    = 'posted';
            $issue->accounting_posted_by = Auth::id();
            $issue->accounting_posted_at = now();
            $issue->save();

            /**
             * Audit: explicit "posted_to_accounts" entry on StoreIssue
             */
            ActivityLog::logCustom(
                'posted_to_accounts',
                'Store issue ' . ($issue->issue_number ?: ('#' . $issue->id)) . ' posted to accounts as voucher ' . $voucher->voucher_no,
                $issue,
                [
                    'accounting_status' => 'posted',
                    'voucher_id'        => $voucher->id,
                    'voucher_no'        => $voucher->voucher_no,
                    'business_date'     => optional($issue->issue_date)->toDateString(),
                ]
            );

            return $voucher;
        });
    }

    /**
     * Resolve the value of a store issue line based on purchase cost.
     *
     * Approach:
     * - From StoreStockItem, get material_receipt_line_id.
     * - Sum basic_amount of all PurchaseBillLine rows linked to that material_receipt_line_id.
     * - Divide by original received quantity/weight from MaterialReceiptLine to get average rate.
     * - Multiply by issued_qty_pcs or issued_weight_kg from StoreIssueLine.
     *
     * This keeps costs consistent with purchase valuation.
     */
    protected function resolveIssueValue(StoreIssueLine $line, StoreStockItem $stockItem): float
    {
        $mrLineId = $stockItem->material_receipt_line_id ?? null;
        if (! $mrLineId) {
            // Opening / manual stock: use opening_unit_rate if available
            $rate = (float) ($stockItem->opening_unit_rate ?? 0);
            if ($rate <= 0) {
                return 0.0;
            }

            $issuedWeight = (float) ($line->issued_weight_kg ?? 0);
            $issuedPcs    = (float) ($line->issued_qty_pcs ?? 0);

            $issueQty = $issuedWeight > 0 ? $issuedWeight : $issuedPcs;
            if ($issueQty <= 0) {
                return 0.0;
            }

            return round($rate * $issueQty, 2);
        }

        $mrLine = MaterialReceiptLine::find($mrLineId);
        if (! $mrLine) {
            return 0.0;
        }

        $totalBasic = PurchaseBillLine::postedBasicForMaterialReceiptLine((int) $mrLineId);
        if ($totalBasic <= 0) {
            return 0.0;
        }

        // Base quantity for average rate
        $baseQty       = 0.0;
        $receivedWeight= (float) $mrLine->received_weight_kg;
        $receivedPcs   = (float) $mrLine->qty_pcs;

        if ($receivedWeight > 0) {
            $baseQty = $receivedWeight;
        } elseif ($receivedPcs > 0) {
            $baseQty = $receivedPcs;
        }

        if ($baseQty <= 0) {
            return 0.0;
        }

        $avgRate = $totalBasic / $baseQty;

        // Issued quantity (prefer weight, fall back to pieces)
        $issuedWeight = (float) $line->issued_weight_kg;
        $issuedPcs    = (float) $line->issued_qty_pcs;

        $issueQty = $issuedWeight > 0 ? $issuedWeight : $issuedPcs;
        if ($issueQty <= 0) {
            return 0.0;
        }

        $amount = $avgRate * $issueQty;

        return round($amount, 2);
    }

    /**
     * Resolve machine dimension for a Store Issue using machine spare consumption mapping.
     * Returns a machine only when mapping is unambiguous (exactly one distinct machine).
     */
    protected function resolveIssueMachineId(StoreIssue $issue): ?int
    {
        if (
            Schema::hasColumn('store_issues', 'machine_id')
            && ! empty($issue->machine_id)
        ) {
            return (int) $issue->machine_id;
        }

        $storeIssueId = (int) $issue->id;
        if ($storeIssueId <= 0 || ! Schema::hasTable('machine_spare_consumptions')) {
            return null;
        }

        $machineIds = MachineSpareConsumption::query()
            ->where('store_issue_id', $storeIssueId)
            ->whereNotNull('machine_id')
            ->distinct()
            ->pluck('machine_id')
            ->values();

        if ($machineIds->count() !== 1) {
            return null;
        }

        return (int) $machineIds->first();
    }

    /**
     * Resolve whether to use WIP-MATERIAL or WIP-CONSUMABLES based on item type.
     */
    protected function resolveWipAccount($item, $wipMaterial, $wipConsumable): Account
    {
        if (! $item) {
            return $wipMaterial;
        }

        $typeCode = strtoupper((string) ($item->type?->code ?? ''));
        if (in_array($typeCode, ['CONSUMABLE', 'SPARE'], true)) {
            return $wipConsumable;
        }

        return $wipMaterial;
    }
}
