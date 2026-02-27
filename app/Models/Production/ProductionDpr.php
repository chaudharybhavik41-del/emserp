<?php

namespace App\Models\Production;

use App\Models\Party;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionDpr extends Model
{
    use HasFactory;

    protected $table = 'production_dprs';

    protected $fillable = [
        'production_plan_id',
        'production_activity_id',
        'cutting_plan_id',
        'mother_stock_item_id',
        'dpr_date',
        'shift',
        'contractor_party_id',
        'worker_user_id',
        'machine_id',
        'geo_latitude',
        'geo_longitude',
        'geo_accuracy_m',
        'geo_status',
        'status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dpr_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'geo_latitude' => 'decimal:7',
        'geo_longitude' => 'decimal:7',
        'geo_accuracy_m' => 'decimal:2',
    ];

    public function plan()
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function activity()
    {
        return $this->belongsTo(ProductionActivity::class, 'production_activity_id');
    }

    public function cuttingPlan()
    {
        return $this->belongsTo(\App\Models\CuttingPlan::class, 'cutting_plan_id');
    }

    public function motherStockItem()
    {
        return $this->belongsTo(\App\Models\StoreStockItem::class, 'mother_stock_item_id');
    }

    public function project()
    {
        return $this->hasOneThrough(Project::class, ProductionPlan::class, 'id', 'id', 'production_plan_id', 'project_id');
    }

    public function lines()
    {
        return $this->hasMany(ProductionDprLine::class, 'production_dpr_id');
    }

    public function contractor()
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_user_id');
    }

    public function isDraft(): bool { return $this->status === 'draft'; }
    public function isSubmitted(): bool { return $this->status === 'submitted'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
}
