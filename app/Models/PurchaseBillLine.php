<?php

namespace App\Models;

use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseBillLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_bill_id',
        'material_receipt_id',
        'material_receipt_line_id',
        'item_id',
        'uom_id',
        'qty',
        'rate',
        'discount_percent',
        'discount_amount',
        'basic_amount',
        'tax_rate',
        'tax_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_amount',
        'account_id',
        'line_no',
    ];

    protected $casts = [
        'qty'          => 'float',
        'rate'         => 'float',
        'discount_percent' => 'float',
        'discount_amount'  => 'float',
        'basic_amount'     => 'float',
        'tax_rate'         => 'float',
        'tax_amount'       => 'float',
        'cgst_amount'      => 'float',
        'sgst_amount'      => 'float',
        'igst_amount'      => 'float',
        'total_amount'     => 'float',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function materialReceipt(): BelongsTo
    {
        return $this->belongsTo(MaterialReceipt::class, 'material_receipt_id');
    }

    public function materialReceiptLine(): BelongsTo
    {
        return $this->belongsTo(MaterialReceiptLine::class, 'material_receipt_line_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function fixedAssetLinks(): HasMany
    {
        return $this->hasMany(FixedAssetLink::class, 'source_line_id')
            ->where('source_type', 'purchase_bill');
    }

    /**
     * Posted billed basic totals keyed by material_receipt_line_id.
     *
     * Supports both:
     * - current line-linked purchase bill lines
     * - legacy header-linked bill lines where material_receipt_line_id was not stored
     *
     * Legacy matching is conservative and only applied when a purchase bill line
     * can be mapped uniquely to a GRN line by receipt + item + UOM + comparable qty.
     *
     * @param  array<int, int|string|null>  $mrLineIds
     * @return array<int, float>
     */
    public static function postedBasicByMaterialReceiptLineIds(array $mrLineIds, ?string $asOfDate = null): array
    {
        $lineIds = collect($mrLineIds)
            ->filter(fn ($id) => ! is_null($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($lineIds === []) {
            return [];
        }

        $directQuery = self::query()
            ->join('purchase_bills as pb', 'pb.id', '=', 'purchase_bill_lines.purchase_bill_id')
            ->where('pb.status', 'posted')
            ->whereIn('purchase_bill_lines.material_receipt_line_id', $lineIds);

        if ($asOfDate) {
            $directQuery->whereDate('pb.bill_date', '<=', $asOfDate);
        }

        $totals = $directQuery
            ->groupBy('purchase_bill_lines.material_receipt_line_id')
            ->selectRaw('purchase_bill_lines.material_receipt_line_id as mr_line_id, COALESCE(SUM(purchase_bill_lines.basic_amount),0) as total_basic')
            ->pluck('total_basic', 'mr_line_id')
            ->mapWithKeys(fn ($amount, $lineId) => [(int) $lineId => (float) $amount])
            ->toArray();

        $receiptLines = MaterialReceiptLine::query()
            ->whereIn('id', $lineIds)
            ->get(['id', 'material_receipt_id', 'item_id', 'uom_id', 'qty_pcs', 'received_weight_kg']);

        $uniqueLegacyMatchByKey = [];

        foreach ($receiptLines as $receiptLine) {
            $matchKey = self::legacyHeaderMatchKey(
                (int) $receiptLine->material_receipt_id,
                (int) $receiptLine->item_id,
                $receiptLine->uom_id !== null ? (int) $receiptLine->uom_id : null,
                self::materialReceiptComparableQty($receiptLine)
            );

            if ($matchKey === null) {
                continue;
            }

            if (array_key_exists($matchKey, $uniqueLegacyMatchByKey)) {
                $uniqueLegacyMatchByKey[$matchKey] = null;
                continue;
            }

            $uniqueLegacyMatchByKey[$matchKey] = (int) $receiptLine->id;
        }

        $receiptIds = $receiptLines
            ->pluck('material_receipt_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($receiptIds === []) {
            return $totals;
        }

        $legacyQuery = self::query()
            ->join('purchase_bills as pb', 'pb.id', '=', 'purchase_bill_lines.purchase_bill_id')
            ->where('pb.status', 'posted')
            ->whereNull('purchase_bill_lines.material_receipt_line_id')
            ->whereIn('purchase_bill_lines.material_receipt_id', $receiptIds);

        if ($asOfDate) {
            $legacyQuery->whereDate('pb.bill_date', '<=', $asOfDate);
        }

        $legacyBillLines = $legacyQuery
            ->get([
                'purchase_bill_lines.material_receipt_id',
                'purchase_bill_lines.item_id',
                'purchase_bill_lines.uom_id',
                'purchase_bill_lines.qty',
                'purchase_bill_lines.basic_amount',
            ]);

        foreach ($legacyBillLines as $billLine) {
            $matchKey = self::legacyHeaderMatchKey(
                (int) $billLine->material_receipt_id,
                (int) $billLine->item_id,
                $billLine->uom_id !== null ? (int) $billLine->uom_id : null,
                (float) $billLine->qty
            );

            if ($matchKey === null) {
                continue;
            }

            $mrLineId = $uniqueLegacyMatchByKey[$matchKey] ?? null;
            if (! $mrLineId) {
                continue;
            }

            $totals[$mrLineId] = (float) ($totals[$mrLineId] ?? 0) + (float) $billLine->basic_amount;
        }

        return $totals;
    }

    public static function postedBasicForMaterialReceiptLine(?int $mrLineId, ?string $asOfDate = null): float
    {
        if (! $mrLineId) {
            return 0.0;
        }

        return (float) (self::postedBasicByMaterialReceiptLineIds([$mrLineId], $asOfDate)[$mrLineId] ?? 0.0);
    }

    protected static function materialReceiptComparableQty(MaterialReceiptLine $receiptLine): float
    {
        $weight = (float) ($receiptLine->received_weight_kg ?? 0);
        if ($weight > 0) {
            return $weight;
        }

        return (float) ($receiptLine->qty_pcs ?? 0);
    }

    protected static function legacyHeaderMatchKey(int $receiptId, int $itemId, ?int $uomId, float $qty): ?string
    {
        if ($receiptId <= 0 || $itemId <= 0 || $qty <= 0) {
            return null;
        }

        $normalizedQty = number_format(round($qty, 3), 3, '.', '');

        return implode('|', [
            $receiptId,
            $itemId,
            $uomId ?? 0,
            $normalizedQty,
        ]);
    }
}
