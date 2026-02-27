<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'material_type_id',
        'material_category_id',
        'material_subcategory_id',
        'code',
        'name',
        'short_name',
        'make',
        'model',
        'serial_number',
        'grade', // For capacity/rating
        'spec',
        'year_of_manufacture',
        'supplier_party_id',
        'purchase_date',
        'purchase_price',
        'accounting_treatment',
        'purchase_invoice_no',
        'purchase_bill_id',
        'purchase_bill_line_id',
        'warranty_months',
        'warranty_expiry_date',
        'rated_capacity',
        'power_rating',
        'fuel_type',
        'operating_hours_total',
        'current_location',
        'department_id',
        'status',
        'is_issued',
        'current_assignment_type',
        'current_contractor_party_id',
        'current_worker_user_id',
        'current_project_id',
        'assigned_date',
        'maintenance_frequency_days',
        'last_maintenance_date',
        'next_maintenance_due_date',
        'maintenance_alert_days',
        'manual_document_path',
        'calibration_certificate_path',
        'insurance_document_path',
        'description',
        'remarks',
        'is_active',
        'created_by',
        'updated_by',
      	// Calibration fields
		'requires_calibration',
		'calibration_frequency_months',
		'calibration_agency',
		'last_calibration_date',
		'next_calibration_due_date',
   		 ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry_date' => 'date',
        'assigned_date' => 'date',
        'last_maintenance_date' => 'date',
        'next_maintenance_due_date' => 'date',
        'purchase_price' => 'decimal:2',
        'operating_hours_total' => 'decimal:2',
        'is_issued' => 'boolean',
        'is_active' => 'boolean',
        'warranty_months' => 'integer',
        'maintenance_frequency_days' => 'integer',
        'maintenance_alert_days' => 'integer',
      	'requires_calibration' => 'boolean',
		'last_calibration_date' => 'date',
		'next_calibration_due_date' => 'date',
    ];

    // Relationships - Following existing pattern

    public function materialType(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(MaterialSubcategory::class, 'material_subcategory_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_party_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function currentContractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'current_contractor_party_id');
    }

    public function currentWorker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_worker_user_id');
    }

    public function currentProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'current_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', '!=', 'disposed');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'active')
            ->where('is_issued', false);
    }

    public function scopeIssued($query)
    {
        return $query->where('is_issued', true);
    }

    public function scopeMaintenanceDue($query, $daysAhead = 7)
    {
        return $query->whereNotNull('next_maintenance_due_date')
            ->whereDate('next_maintenance_due_date', '<=', now()->addDays($daysAhead));
    }

    public function scopeMaintenanceOverdue($query)
    {
        return $query->whereNotNull('next_maintenance_due_date')
            ->whereDate('next_maintenance_due_date', '<', now());
    }

    // Helpers

    public function isAvailable(): bool
    {
        return $this->is_active 
            && $this->status === 'active' 
            && !$this->is_issued;
    }

    public function isMaintenanceDue(): bool
    {
        if (!$this->next_maintenance_due_date) {
            return false;
        }

        return $this->next_maintenance_due_date->lte(now()->addDays($this->maintenance_alert_days));
    }

    public function isMaintenanceOverdue(): bool
    {
        if (!$this->next_maintenance_due_date) {
            return false;
        }

        return $this->next_maintenance_due_date->lt(now());
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'active' => 'success',
            'under_maintenance' => 'warning',
            'breakdown' => 'danger',
            'retired' => 'secondary',
            'disposed' => 'dark',
            default => 'info',
        };
    }
		public function maintenancePlans()
	{
    return $this->hasMany(MachineMaintenancePlan::class);
	}

	public function maintenanceLogs()
	{
    return $this->hasMany(MachineMaintenanceLog::class);
	}

	public function breakdowns()
	{
    return $this->hasMany(MachineBreakdownRegister::class);
	}

	public function activePlan()
	{
    return $this->hasOne(MachineMaintenancePlan::class)->where('is_active', true);
	}

	public function latestBreakdown()
	{
    return $this->hasOne(MachineBreakdownRegister::class)->latest('reported_at');
	}
  
    public function getAssignmentTypeLabel(): string
    {
        return match($this->current_assignment_type) {
            'contractor' => 'Contractor',
            'company_worker' => 'Company Worker',
            'unassigned' => 'Unassigned',
            default => 'Unknown',
        };
    }

    /**
     * Generate next machine code for a given category
     * Format: CAT-YYYY-NNNN (e.g., CUT-2025-0001)
     */
    public static function generateCode(int $categoryId): string
    {
        $category = MaterialCategory::find($categoryId);
        if (!$category) {
            throw new \Exception('Machine category not found');
        }

        $year = now()->format('Y');
        $prefix = $category->code . '-' . $year . '-';

        $lastMachine = self::where('code', 'like', $prefix . '%')
            ->orderByDesc('code')
            ->first();

        if ($lastMachine) {
            $lastNum = (int) substr($lastMachine->code, -4);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
 


    public function purchaseBill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function purchaseBillLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseBillLine::class, 'purchase_bill_line_id');
    }

}