<?php

namespace App\Models\Production;

use App\Models\Party;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionDispatch extends Model
{
    use HasFactory;

    protected $table = 'production_dispatches';

    protected $fillable = [
        'project_id',
        'production_plan_id',
        'client_party_id',
        'dispatch_number',
        'dispatch_date',
        'status',
        'vehicle_number',
        'lr_number',
        'transporter_name',
        'total_qty',
        'total_weight_kg',
        'remarks',
        'created_by',
        'updated_by',
        'finalized_by',
        'finalized_at',
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

    public function plan()
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function client()
    {
        return $this->belongsTo(Party::class, 'client_party_id');
    }

    public function lines()
    {
        return $this->hasMany(ProductionDispatchLine::class, 'production_dispatch_id');
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isDraft(): bool { return $this->status === 'draft'; }
    public function isFinalized(): bool { return $this->status === 'finalized'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public static function nextDispatchNumber(Project $project): string
    {
        $year = now()->year;
        $prefix = "PD-{$project->code}-{$year}-";

        $last = static::query()
            ->where('dispatch_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('dispatch_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
