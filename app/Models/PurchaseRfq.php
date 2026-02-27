<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRfq extends Model
{
    use HasFactory, SoftDeletes;
protected $fillable = [
        'code',
        'project_id',
        'department_id',
        'purchase_indent_id',
        'created_by',
        'rfq_date',
        'due_date',
        'status',
        'remarks',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'rfq_date' => 'date',
        'due_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRfqItem::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(PurchaseRfqVendor::class);
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

    public function indent(): BelongsTo
    {
        return $this->belongsTo(PurchaseIndent::class, 'purchase_indent_id');
    }

public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'purchase_rfq_id');
    }
}
