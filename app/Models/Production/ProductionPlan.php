<?php

namespace App\Models\Production;

use App\Models\Bom;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionPlan extends Model
{
    use HasFactory;

    protected $table = 'production_plans';

    protected $fillable = [
        'project_id',
        'bom_id',
        'plan_number',
        'plan_date',
        'remarks',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'plan_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function bom()
    {
        return $this->belongsTo(Bom::class, 'bom_id');
    }

    public function items()
    {
        return $this->hasMany(ProductionPlanItem::class, 'production_plan_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public static function generateNumberForProject(Project $project): string
    {
        $year = now()->year;

        $last = static::where('project_id', $project->id)
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        $nextSeq = 1;

        if ($last && preg_match('/(\d{4})$/', (string) $last->plan_number, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        $seq = str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        return sprintf('PP-%s-%d-%s', $project->code, $year, $seq);
    }
}
