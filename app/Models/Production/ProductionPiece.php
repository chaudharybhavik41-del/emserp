<?php

namespace App\Models\Production;

use App\Models\Project;
use App\Models\StoreStockItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionPiece extends Model
{
    use HasFactory;

    protected $table = 'production_pieces';

    protected $fillable = [
        'project_id',
        'production_plan_id',
        'production_plan_item_id',
        'production_dpr_line_id',
        'mother_stock_item_id',
        'piece_number',
        'piece_tag',
        'thickness_mm',
        'width_mm',
        'length_mm',
        'weight_kg',
        'plate_number',
        'heat_number',
        'mtc_number',
        'status',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:3',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function motherStock()
    {
        return $this->belongsTo(StoreStockItem::class, 'mother_stock_item_id');
    }

    public static function generatePieceNumber(string $projectCode): string
    {
        // PC-PRJ-2025-000001
        $year = now()->year;
        $prefix = "PC-{$projectCode}-{$year}-";

        $last = static::query()
            ->where('piece_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('piece_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
    }
}
