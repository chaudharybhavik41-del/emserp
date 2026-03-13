<?php

namespace App\Models\ProductionV2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionTrialAssemblyMeasurement extends Model
{
    use HasFactory;

    protected $table = 'production_v2_trial_assembly_measurements';

    protected $fillable = [
        'trial_assembly_id',
        'parameter_name',
        'required_dimension',
        'tolerance',
        'actual_dimension',
        'assembly_id',
        'assembly_ref',
        'ok_status',
        'remarks',
    ];

    protected $casts = [
        'ok_status' => 'boolean',
    ];

    public function trialAssembly()
    {
        return $this->belongsTo(ProductionTrialAssembly::class, 'trial_assembly_id');
    }

    public function assembly()
    {
        return $this->belongsTo(ProductionAssembly::class, 'assembly_id');
    }
}
