<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineSpareConsumption extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_maintenance_log_id',
        'machine_id',
        'store_issue_id',
        'item_id',
        'uom_id',
        'qty_consumed',
        'unit_cost',
        'total_cost',
        'remarks',
    ];

    protected $casts = [
        'qty_consumed' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    // Relationships

    public function maintenanceLog(): BelongsTo
    {
        return $this->belongsTo(MachineMaintenanceLog::class, 'machine_maintenance_log_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function storeIssue(): BelongsTo
    {
        return $this->belongsTo(StoreIssue::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
