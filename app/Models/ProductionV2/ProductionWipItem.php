<?php

namespace App\Models\ProductionV2;

use App\Models\Project;
use App\Models\StoreStockItem;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionWipItem extends Model
{
    use HasFactory;

    protected $table = 'production_v2_wip_items';

    protected $fillable = [
        'project_id',
        'part_definition_id',
        'cut_batch_id',
        'piece_no',
        'lot_no',
        'qty',
        'uom_id',
        'thickness_mm',
        'width_mm',
        'length_mm',
        'weight_kg',
        'mother_stock_item_id',
        'plate_number',
        'heat_number',
        'mtc_number',
        'is_interchangeable',
        'reserved_for_assembly_id',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'thickness_mm' => 'decimal:3',
        'width_mm' => 'decimal:3',
        'length_mm' => 'decimal:3',
        'weight_kg' => 'decimal:3',
        'is_interchangeable' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function partDefinition()
    {
        return $this->belongsTo(ProductionPartDefinition::class, 'part_definition_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function motherStock()
    {
        return $this->belongsTo(StoreStockItem::class, 'mother_stock_item_id');
    }

    public function cutBatch()
    {
        return $this->belongsTo(ProductionCutBatch::class, 'cut_batch_id');
    }

    public static function generateReference(string $projectCode, string $prefix = 'WIP', ?string $planNumber = null): string
    {
        $base = strtoupper($prefix) . '-' . static::compactProjectCode($projectCode);

        if ($planNumber) {
            $base .= '-' . static::compactPlanNumber($planNumber);
        }

        $base .= '-';

        $last = static::query()
            ->where(function ($query) use ($base) {
                $query->where('piece_no', 'like', $base . '%')
                    ->orWhere('lot_no', 'like', $base . '%');
            })
            ->orderByDesc('id')
            ->first(['piece_no', 'lot_no']);

        $lastRef = $last?->piece_no ?: $last?->lot_no;
        $seq = 1;

        if ($lastRef && preg_match('/(\d+)$/', $lastRef, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $base . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    protected static function compactProjectCode(string $projectCode): string
    {
        $compact = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $projectCode));

        return preg_replace('/^([A-Z]+)20(\d+)$/', '$1$2', $compact) ?: $compact;
    }

    protected static function compactPlanNumber(string $planNumber): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $planNumber));
    }
}
