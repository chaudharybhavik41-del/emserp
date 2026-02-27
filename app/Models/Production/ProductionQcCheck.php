<?php

namespace App\Models\Production;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionQcCheck extends Model
{
    use HasFactory;

    protected $table = 'production_qc_checks';

    protected $fillable = [
        'project_id',
        'production_plan_id',
        'production_activity_id',
        'production_plan_item_id',
        'production_plan_item_activity_id',
        'production_dpr_id',
        'production_dpr_line_id',
        'result',
        'remarks',
        'checked_by',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function planItemActivity()
    {
        return $this->belongsTo(ProductionPlanItemActivity::class, 'production_plan_item_activity_id');
    }

    public function plan()
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function activity()
    {
        return $this->belongsTo(ProductionActivity::class, 'production_activity_id');
    }
}
