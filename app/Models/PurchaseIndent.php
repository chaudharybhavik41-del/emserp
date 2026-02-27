<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseIndent extends Model
{
    use HasFactory, SoftDeletes;
protected $fillable = [
        'code',
        'project_id',
        'department_id',
        'created_by',
        'approved_by',
        'required_by_date',
        'status',
        'remarks',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'required_by_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseIndentItem::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

public function rfqs(): HasMany
    {
        return $this->hasMany(PurchaseRfq::class, 'purchase_indent_id');
    }
}
