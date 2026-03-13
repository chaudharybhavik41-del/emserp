<?php

namespace App\Models\ProductionV2;

use App\Models\Machine;
use App\Models\Party;
use App\Models\Project;
use App\Models\Production\ProductionDpr;
use App\Models\Production\ProductionRemnant;
use App\Models\StoreIssue;
use App\Models\StoreStockItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionCutBatch extends Model
{
    use HasFactory;

    protected $table = 'production_v2_cut_batches';

    protected $fillable = [
        'project_id',
        'cutting_plan_id',
        'dpr_id',
        'store_issue_id',
        'cut_date',
        'mother_stock_item_id',
        'machine_id',
        'operator_id',
        'contractor_party_id',
        'shift',
        'plate_number_snapshot',
        'heat_number_snapshot',
        'mtc_number_snapshot',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'cut_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function cuttingPlan()
    {
        return $this->belongsTo(ProductionCuttingPlan::class, 'cutting_plan_id');
    }

    public function motherStock()
    {
        return $this->belongsTo(StoreStockItem::class, 'mother_stock_item_id');
    }

    public function storeIssue()
    {
        return $this->belongsTo(StoreIssue::class, 'store_issue_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function contractor()
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function dpr()
    {
        return $this->belongsTo(ProductionDpr::class, 'dpr_id');
    }

    public function wipItems()
    {
        return $this->hasMany(ProductionWipItem::class, 'cut_batch_id');
    }

    public function remnants()
    {
        return $this->hasMany(ProductionRemnant::class, 'production_v2_cut_batch_id');
    }
}
