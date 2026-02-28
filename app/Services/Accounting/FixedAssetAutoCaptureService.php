<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Voucher;
use App\Models\FixedAsset;
use App\Models\FixedAssetLink;
use App\Models\PurchaseBill;
use App\Models\PurchaseBillLine;

class FixedAssetAutoCaptureService
{
    public function captureFromPurchaseBill(PurchaseBill $bill, Voucher $voucher, array $voucherLineIdByAccount): int
    {
        $bill->loadMissing('lines.item.type', 'purchaseOrder');

        $created = 0;

        foreach ($bill->lines as $line) {
            $item = $line->item;
            if (! $item || ! $this->isMachineryType($item)) {
                continue;
            }

            if (! $this->isLongTermMachinery($item)) {
                continue;
            }

            $qty = (int) round((float) ($line->qty ?? 0));
            if ($qty <= 0) {
                continue;
            }

            $existingCount = FixedAsset::query()
                ->where('asset_type', 'machinery')
                ->where('item_id', $item->id)
                ->where('purchase_date', $bill->bill_date)
                ->where('vendor_party_id', $bill->supplier_id)
                ->where('name', $item->name)
                ->whereHas('links', function ($q) use ($voucher) {
                    $q->where('link_type', 'purchase_capitalization')
                        ->where('voucher_id', $voucher->id);
                })
                ->count();

            $missing = max(0, $qty - $existingCount);
            if ($missing <= 0) {
                continue;
            }

            $accountId = $this->resolvePurchaseAccountIdForItem($item);
            $voucherLineId = $accountId ? ($voucherLineIdByAccount[$accountId] ?? null) : null;

            $unitCost = $qty > 0 ? round((float) ($line->basic_amount ?? 0) / $qty, 2) : (float) ($line->basic_amount ?? 0);
            $serials = $this->parseSerials((string) ($line->serial_no ?? ''));

            for ($i = 0; $i < $missing; $i++) {
                $asset = FixedAsset::create([
                    'asset_type' => 'machinery',
                    'asset_code' => FixedAsset::generateMachineryCode((int) date('Y', strtotime((string) $bill->bill_date))),
                    'name' => (string) ($item->name ?? 'Machinery'),
                    'item_id' => $item->id,
                    'machine_type' => 'long_term',
                    'serial_no' => $serials[$i] ?? null,
                    'make' => $line->machine_make,
                    'model' => $line->machine_model,
                    'project_id' => $bill->project_id ?: $bill->purchaseOrder?->project_id,
                    'vendor_party_id' => $bill->supplier_id,
                    'purchase_date' => $bill->bill_date,
                    'put_to_use_date' => $bill->bill_date,
                    'opening_wdv' => $unitCost,
                    'original_cost' => $unitCost,
                    'accum_dep_opening' => 0,
                    'status' => 'in_use',
                    'created_by' => $bill->created_by,
                ]);

                FixedAssetLink::create([
                    'fixed_asset_id' => $asset->id,
                    'voucher_id' => $voucher->id,
                    'voucher_line_id' => $voucherLineId,
                    'link_type' => 'purchase_capitalization',
                ]);

                $created++;
            }
        }

        return $created;
    }

    protected function isMachineryType($item): bool
    {
        $code = strtoupper((string) ($item->type->code ?? ''));
        return $code === 'MACHINERY';
    }

    protected function isLongTermMachinery($item): bool
    {
        $usage = strtolower((string) (($item->accounting_usage_override ?: null) ?: ($item->type->accounting_usage ?? '')));
        return $usage === 'fixed_asset';
    }

    protected function resolvePurchaseAccountIdForItem($item): ?int
    {
        if (! $item) {
            return null;
        }

        if (! empty($item->asset_account_id)) {
            return (int) $item->asset_account_id;
        }

        if (! empty($item->subcategory?->asset_account_id)) {
            return (int) $item->subcategory->asset_account_id;
        }

        $code = config('accounting.default_accounts.fixed_asset_machinery_code', 'FA-MACHINERY');
        return (int) \App\Models\Accounting\Account::query()->where('code', $code)->value('id');
    }

    protected function parseSerials(string $serialNo): array
    {
        if (trim($serialNo) === '') {
            return [];
        }

        $parts = preg_split('/[\n,]+/', $serialNo) ?: [];
        return array_values(array_filter(array_map('trim', $parts)));
    }
}
