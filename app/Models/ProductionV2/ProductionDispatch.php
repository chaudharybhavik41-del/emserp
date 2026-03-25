<?php

namespace App\Models\ProductionV2;

use App\Models\Party;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionDispatch extends Model
{
    use HasFactory;

    protected $table = 'production_v2_dispatches';

    protected $fillable = [
        'project_id',
        'client_party_id',
        'dispatch_number',
        'dispatch_date',
        'vehicle_number',
        'lr_number',
        'transporter_name',
        'gate_pass_ref',
        'total_qty',
        'total_weight_kg',
        'status',
        'remarks',
        'finalized_by',
        'finalized_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dispatch_date' => 'date',
        'total_qty' => 'decimal:3',
        'total_weight_kg' => 'decimal:3',
        'finalized_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function client()
    {
        return $this->belongsTo(Party::class, 'client_party_id');
    }

    public function lines()
    {
        return $this->hasMany(ProductionDispatchLine::class, 'dispatch_id');
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public static function nextDispatchNumber(Project $project): string
    {
        $prefix = 'PV2-DIS-' . str_pad((string) $project->id, 4, '0', STR_PAD_LEFT) . '-';

        $last = static::query()
            ->where('project_id', $project->id)
            ->where('dispatch_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('dispatch_number');

        if (! $last) {
            return $prefix . '0001';
        }

        $lastSeq = (int) substr((string) $last, strrpos((string) $last, '-') + 1);

        return $prefix . str_pad((string) ($lastSeq + 1), 4, '0', STR_PAD_LEFT);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
