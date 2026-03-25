<?php

namespace App\Models\ProductionV2;

use App\Models\Party;
use App\Models\Project;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionBillingRate extends Model
{
    use HasFactory;

    protected $table = 'production_v2_billing_rates';

    protected $fillable = [
        'project_id',
        'contractor_party_id',
        'source_type',
        'operation_master_id',
        'qty_basis',
        'rate',
        'rate_uom_id',
        'description',
        'is_active',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function contractor()
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function operationMaster()
    {
        return $this->belongsTo(ProductionOperationMaster::class, 'operation_master_id');
    }

    public function rateUom()
    {
        return $this->belongsTo(Uom::class, 'rate_uom_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
