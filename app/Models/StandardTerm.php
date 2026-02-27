<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StandardTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'module',
        'sub_module',
        'version',
        'is_active',
        'is_default',
        'sort_order',
        'content',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (StandardTerm $model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function (StandardTerm $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function scopeForModule($query, string $module, ?string $subModule = null)
    {
        $query->where('module', $module);

        if ($subModule) {
            $query->where('sub_module', $subModule);
        }

        return $query;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
