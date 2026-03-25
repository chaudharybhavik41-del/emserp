<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use App\Models\FuelIssue;
use App\Models\MaterialReceiptLine;
use App\Models\PurchaseBillLine;
use App\Models\StoreStockItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class FuelIssuePostingService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService
    ) {
    }

    /**
     * @return Voucher|null
     *
     * @throws RuntimeException
     */
    public function post(FuelIssue $fuelIssue): ?Voucher
    {
        if (! Config::get('accounting.enable_fuel_issue_posting', true)) {
            throw new RuntimeException('Posting Fuel Issues to Accounts is disabled.');
        }

        if (! empty($fuelIssue->voucher_id)) {
            $existing = Voucher::find($fuelIssue->voucher_id);
            if ($existing) {
                return $existing;
            }

            throw new RuntimeException('Fuel issue is already linked to a voucher, but voucher was not found.');
        }

        if (($fuelIssue->accounting_status ?? null) === 'not_required') {
            return null;
        }

        $fuelIssue->loadMissing('stockItem.item', 'item', 'machine');

        $stockItem = $fuelIssue->stockItem;
        if (! $stockItem instanceof StoreStockItem) {
            throw new RuntimeException('Fuel issue stock item not found.');
        }

        $qty = (float) ($fuelIssue->qty ?? 0);
        if ($qty <= 0) {
            throw new RuntimeException('Fuel issue quantity must be greater than zero.');
        }

        // Client fuel is non-valued in books.
        if ((bool) ($stockItem->is_client_material ?? false)) {
            $fuelIssue->accounting_status = 'not_required';
            $fuelIssue->accounting_posted_by = Auth::id();
            $fuelIssue->accounting_posted_at = now();
            $fuelIssue->save();

            return null;
        }

        $projectId = (int) ($fuelIssue->project_id ?? 0) ?: null;
        $companyId = (int) Config::get('accounting.default_company_id', 1);

        $projectFuelCode = Config::get('accounting.fuel.project_fuel_expense_account_code');
        $factoryFuelCode = Config::get('accounting.fuel.factory_fuel_expense_account_code');
        $inventoryCode = Config::get('accounting.fuel.inventory_account_code')
            ?: Config::get('accounting.store.inventory_consumables_account_code');

        if (! $projectFuelCode) {
            throw new RuntimeException('Config accounting.fuel.project_fuel_expense_account_code is not set.');
        }
        if (! $factoryFuelCode) {
            throw new RuntimeException('Config accounting.fuel.factory_fuel_expense_account_code is not set.');
        }
        if (! $inventoryCode) {
            throw new RuntimeException('Config accounting.fuel.inventory_account_code is not set.');
        }

        $projectFuelAccount = Account::where('code', $projectFuelCode)->first();
        $factoryFuelAccount = Account::where('code', $factoryFuelCode)->first();

        if (! $projectFuelAccount) {
            throw new RuntimeException('Fuel project expense account not found for code: ' . $projectFuelCode);
        }
        if (! $factoryFuelAccount) {
            throw new RuntimeException('Fuel factory expense account not found for code: ' . $factoryFuelCode);
        }

        $item = $stockItem->item ?? $fuelIssue->item;
        $inventoryAccount = null;

        if ($item && $item->inventory_account_id) {
            $inventoryAccount = Account::find($item->inventory_account_id);
        }
        if (! $inventoryAccount) {
            $inventoryAccount = Account::where('code', $inventoryCode)->first();
        }
        if (! $inventoryAccount) {
            throw new RuntimeException('Fuel inventory account not found for code: ' . $inventoryCode);
        }

        $amount = (float) ($fuelIssue->amount ?? 0);
        if ($amount <= 0) {
            $amount = $this->resolveFuelIssueValue($fuelIssue, $stockItem);
        }
        if ($amount <= 0) {
            throw new RuntimeException('Unable to resolve fuel issue value from linked stock/purchase.');
        }

        return DB::transaction(function () use (
            $fuelIssue,
            $companyId,
            $projectId,
            $projectFuelAccount,
            $factoryFuelAccount,
            $inventoryAccount,
            $amount
        ) {
            $fuelIssue = FuelIssue::whereKey($fuelIssue->id)->lockForUpdate()->firstOrFail();

            if (! empty($fuelIssue->voucher_id)) {
                $existing = Voucher::find($fuelIssue->voucher_id);
                if ($existing) {
                    return $existing;
                }
                throw new RuntimeException('Fuel issue is already linked to a voucher, but voucher was not found.');
            }

            $costCenterId = $projectId
                ? ProjectCostCenterResolver::resolveId($companyId, (int) $projectId)
                : null;

            $debitAccount = $projectId ? $projectFuelAccount : $factoryFuelAccount;

            $voucher = new Voucher();
            $voucher->company_id = $companyId;
            $businessDate = $fuelIssue->issue_date ? Carbon::parse($fuelIssue->issue_date) : now();
            $voucher->voucher_no = $this->voucherNumberService->next('fuel_issue', $companyId, $businessDate);
            $voucher->voucher_type = 'fuel_issue';
            $voucher->voucher_date = $businessDate->toDateString();
            $voucher->reference = $fuelIssue->issue_number ?: ('FUEL#' . $fuelIssue->id);
            $voucher->narration = trim(
                'Fuel Issue ' . ($fuelIssue->issue_number ?: ('#' . $fuelIssue->id))
                . ' - ' . ($fuelIssue->remarks ?? '')
            );
            $voucher->project_id = $projectId;
            $voucher->cost_center_id = $costCenterId;
            $voucher->status = 'draft';
            $voucher->created_by = $fuelIssue->created_by;
            $voucher->amount_base = round($amount, 2);
            $voucher->save();

            $hasMachineDimension = Schema::hasColumn('voucher_lines', 'machine_id');
            $machineId = (int) ($fuelIssue->machine_id ?? 0) ?: null;
            $lineNo = 1;

            $debitLine = [
                'voucher_id' => $voucher->id,
                'line_no' => $lineNo++,
                'account_id' => $debitAccount->id,
                'cost_center_id' => $costCenterId,
                'description' => 'Fuel Issue - Debit',
                'debit' => round($amount, 2),
                'credit' => 0,
                'reference_type' => FuelIssue::class,
                'reference_id' => $fuelIssue->id,
            ];

            if ($hasMachineDimension) {
                $debitLine['machine_id'] = $machineId;
            }

            VoucherLine::create($debitLine);

            $creditLine = [
                'voucher_id' => $voucher->id,
                'line_no' => $lineNo++,
                'account_id' => $inventoryAccount->id,
                'cost_center_id' => $costCenterId,
                'description' => 'Fuel Issue - Credit',
                'debit' => 0,
                'credit' => round($amount, 2),
                'reference_type' => FuelIssue::class,
                'reference_id' => $fuelIssue->id,
            ];

            if ($hasMachineDimension) {
                $creditLine['machine_id'] = $machineId;
            }

            VoucherLine::create($creditLine);

            $voucher->posted_by = Auth::id();
            $voucher->posted_at = now();
            $voucher->status = 'posted';
            $voucher->save();

            $fuelIssue->voucher_id = $voucher->id;
            $fuelIssue->accounting_status = 'posted';
            $fuelIssue->accounting_posted_by = Auth::id();
            $fuelIssue->accounting_posted_at = now();
            $fuelIssue->amount = round($amount, 2);
            $fuelIssue->save();

            ActivityLog::logCustom(
                'posted_to_accounts',
                'Fuel issue ' . ($fuelIssue->issue_number ?: ('#' . $fuelIssue->id))
                . ' posted to accounts as voucher ' . $voucher->voucher_no,
                $fuelIssue,
                [
                    'accounting_status' => 'posted',
                    'voucher_id' => $voucher->id,
                    'voucher_no' => $voucher->voucher_no,
                    'business_date' => optional($fuelIssue->issue_date)->toDateString(),
                ]
            );

            return $voucher;
        });
    }

    protected function resolveFuelIssueValue(FuelIssue $fuelIssue, StoreStockItem $stockItem): float
    {
        $qty = (float) ($fuelIssue->qty ?? 0);
        if ($qty <= 0) {
            return 0.0;
        }

        $mrLineId = $stockItem->material_receipt_line_id ?? null;
        if (! $mrLineId) {
            $rate = (float) ($stockItem->opening_unit_rate ?? 0);
            if ($rate <= 0) {
                return 0.0;
            }

            return round($rate * $qty, 2);
        }

        $mrLine = MaterialReceiptLine::find($mrLineId);
        if (! $mrLine) {
            return 0.0;
        }

        $totalBasic = PurchaseBillLine::postedBasicForMaterialReceiptLine((int) $mrLineId);
        if ($totalBasic <= 0) {
            return 0.0;
        }

        $baseQty = 0.0;
        $receivedWeight = (float) ($mrLine->received_weight_kg ?? 0);
        $receivedPcs = (float) ($mrLine->qty_pcs ?? 0);
        if ($receivedWeight > 0) {
            $baseQty = $receivedWeight;
        } elseif ($receivedPcs > 0) {
            $baseQty = $receivedPcs;
        }
        if ($baseQty <= 0) {
            return 0.0;
        }

        $rate = $totalBasic / $baseQty;

        return round($rate * $qty, 2);
    }
}
