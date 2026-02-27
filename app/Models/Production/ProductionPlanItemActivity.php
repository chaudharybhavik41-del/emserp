<?php

namespace App\Models\Production;

use App\Models\Machine;
use App\Models\Party;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionPlanItemActivity extends Model
{
    use HasFactory;

    protected $table = 'production_plan_item_activities';

    protected $fillable = [
        'production_plan_item_id',
        'production_activity_id',
        'sequence_no',
        'is_enabled',
        'contractor_party_id',
        'worker_user_id',
        'machine_id',
        'rate',
        'rate_uom_id',
        'planned_date',
        'status',
    ];

    protected $casts = [
        'sequence_no' => 'integer',
        'is_enabled' => 'boolean',
        'machine_id' => 'integer',
        'rate' => 'decimal:2',
        'planned_date' => 'date',
    ];

    public function planItem()
    {
        return $this->belongsTo(ProductionPlanItem::class, 'production_plan_item_id');
    }

    public function activity()
    {
        return $this->belongsTo(ProductionActivity::class, 'production_activity_id');
    }

    public function contractor()
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_user_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function rateUom()
    {
        return $this->belongsTo(Uom::class, 'rate_uom_id');
    }
}
