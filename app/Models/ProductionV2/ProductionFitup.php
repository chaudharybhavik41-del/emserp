<?php

namespace App\Models\ProductionV2;

use App\Models\Party;
use App\Models\Project;
use App\Models\Production\ProductionDpr;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionFitup extends Model
{
    use HasFactory;

    protected $table = 'production_v2_fitups';

    protected $fillable = [
        'project_id',
        'assembly_id',
        'dpr_id',
        'fitup_date',
        'shift',
        'contractor_party_id',
        'supervisor_id',
        'inspector_id',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fitup_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assembly()
    {
        return $this->belongsTo(ProductionAssembly::class, 'assembly_id');
    }

    public function contractor()
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function dpr()
    {
        return $this->belongsTo(ProductionDpr::class, 'dpr_id');
    }

    public function consumptions()
    {
        return $this->hasMany(ProductionFitupConsumption::class, 'fitup_id');
    }

    public function latestWeldingEvent()
    {
        return $this->hasOne(ProductionWeldingEvent::class, 'assembly_id', 'assembly_id')->latestOfMany();
    }
}
