<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionAuditLog extends Model
{
    use HasFactory;

    protected $table = 'production_audit_logs';

    protected $fillable = [
        'project_id',
        'event',
        'entity_type',
        'entity_id',
        'message',
        'meta',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
