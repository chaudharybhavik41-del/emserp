<?php

namespace App\Models\Tasks;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class TaskLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'color',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (TaskLabel $label) {
            if (empty($label->slug)) {
                $label->slug = Str::slug($label->name);
            }
        });
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_label')
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where('company_id', $companyId ?? 1);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Helpers
    public function getColorStyleAttribute(): string
    {
        return "background-color: {$this->color}";
    }

    public function getTextColorAttribute(): string
    {
        // Calculate contrasting text color based on background
        $hex = ltrim($this->color, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Using perceived brightness formula
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        
        return $brightness > 128 ? '#000000' : '#ffffff';
    }

    // Default labels for fabrication
    public static function getDefaultLabels(): array
    {
        return [
            ['name' => 'Urgent', 'slug' => 'urgent', 'color' => '#ef4444', 'description' => 'Requires immediate attention'],
            ['name' => 'Bug', 'slug' => 'bug', 'color' => '#dc2626', 'description' => 'Something is not working correctly'],
            ['name' => 'Enhancement', 'slug' => 'enhancement', 'color' => '#8b5cf6', 'description' => 'Improvement or new feature'],
            ['name' => 'Documentation', 'slug' => 'documentation', 'color' => '#3b82f6', 'description' => 'Documentation related'],
            ['name' => 'Quality Issue', 'slug' => 'quality-issue', 'color' => '#f59e0b', 'description' => 'Quality control concern'],
            ['name' => 'Safety', 'slug' => 'safety', 'color' => '#dc2626', 'description' => 'Safety related task'],
            ['name' => 'Client Request', 'slug' => 'client-request', 'color' => '#10b981', 'description' => 'Requested by client'],
            ['name' => 'Internal', 'slug' => 'internal', 'color' => '#6366f1', 'description' => 'Internal team task'],
            ['name' => 'Rework', 'slug' => 'rework', 'color' => '#f97316', 'description' => 'Requires rework'],
            ['name' => 'Waiting Material', 'slug' => 'waiting-material', 'color' => '#06b6d4', 'description' => 'Waiting for material availability'],
            ['name' => 'Waiting Approval', 'slug' => 'waiting-approval', 'color' => '#a855f7', 'description' => 'Pending approval'],
            ['name' => 'Outsourced', 'slug' => 'outsourced', 'color' => '#64748b', 'description' => 'Work outsourced to vendor'],
        ];
    }
}
