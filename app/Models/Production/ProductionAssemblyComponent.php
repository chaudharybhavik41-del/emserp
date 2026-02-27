<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionAssemblyComponent extends Model
{
    use HasFactory;

    protected $table = 'production_assembly_components';

    protected $fillable = [
        'production_assembly_id',
        'production_piece_id',
    ];

    public function assembly()
    {
        return $this->belongsTo(ProductionAssembly::class, 'production_assembly_id');
    }

    public function piece()
    {
        return $this->belongsTo(ProductionPiece::class, 'production_piece_id');
    }
}
