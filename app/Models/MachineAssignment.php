<?php

namespace App\Models;

use App\Models\Accounting\Voucher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MachineAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'assignment_number',
        'assignment_type',
        'contractor_party_id',
        'contractor_person_name',
        'worker_user_id',
        'project_id',
        'assigned_date',
        'expected_return_date',
        'expected_duration_days',
        'extended_reason',
        'actual_return_date',
        'condition_at_issue',
        'condition_at_return',
        'meter_reading_at_issue',
        'meter_reading_at_return',
        'operating_hours_used',
        'status',
        'issue_remarks',
        'return_remarks',
        'issued_by',
        'returned_by',

        // Phase-C (Tool Stock custody accounting)
        'return_disposition',
        'damage_borne_by',
        'damage_recovery_amount',
        'damage_loss_amount',
        'issue_voucher_id',
        'return_voucher_id',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'expected_return_date' => 'date',
        'actual_return_date' => 'date',
        'expected_duration_days' => 'integer',

        'meter_reading_at_issue' => 'decimal:2',
        'meter_reading_at_return' => 'decimal:2',
        'operating_hours_used' => 'decimal:2',

        'damage_recovery_amount' => 'decimal:2',
        'damage_loss_amount' => 'decimal:2',
    ];

    // Relationships

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function issueVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'issue_voucher_id');
    }

    public function returnVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'return_voucher_id');
    }

    // Helpers

    public static function generateNumber(): string
    {
        $year = now()->format('y');
        $prefix = "ASN-{$year}-";

        $last = self::where('assignment_number', 'like', $prefix . '%')
            ->orderBy('assignment_number', 'desc')
            ->first();

        $lastNumber = $last ? (int) substr($last->assignment_number, strlen($prefix)) : 0;
        $nextNumber = $lastNumber + 1;

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isReturned(): bool
    {
        return in_array($this->status, ['returned', 'damaged', 'lost'], true);
    }

    public function isScrapped(): bool
    {
        return ($this->return_disposition ?? null) === 'scrapped';
    }

    /**
     * Overdue = not returned + expected return date is before today.
     */
    public function isOverdue(): bool
    {
        if ($this->isReturned()) {
            return false;
        }

        if (! $this->expected_return_date) {
            return false;
        }

        return $this->expected_return_date->lt(Carbon::today());
    }

    /**
     * Total days used so far.
     *
     * - If returned: assigned_date -> actual_return_date
     * - If active/extended: assigned_date -> today
     */
    public function getDurationDays(): int
    {
        if (! $this->assigned_date) {
            return 0;
        }

        $start = Carbon::parse($this->assigned_date);
        $end = $this->actual_return_date ? Carbon::parse($this->actual_return_date) : Carbon::today();

        $days = $start->diffInDays($end, false);

        return (int) max(0, $days);
    }

    /**
     * Days overdue (only meaningful when isOverdue() is true)
     */
    public function getOverdueDays(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->expected_return_date->diffInDays(Carbon::today());
    }

    public function getStatusBadgeClass(): string
    {
        if ($this->isOverdue()) {
            return 'danger';
        }

        return match ($this->status) {
            'active' => 'primary',
            'returned' => 'success',
            'extended' => 'warning',
            'damaged' => 'danger',
            'lost' => 'dark',
            default => 'secondary',
        };
    }

    public function getAssignedToName(): string
    {
        return match ($this->assignment_type) {
            'contractor' => $this->contractor?->name ?: 'Contractor',
            'company_worker' => $this->worker?->name ?: 'Worker',
            'project' => $this->project?->name ?: 'Project',
            default => 'N/A',
        };
    }

    public function getOperatingHoursUsedAttribute(): float
    {
        if ($this->meter_reading_at_return && $this->meter_reading_at_issue) {
            return max(0, (float) $this->meter_reading_at_return - (float) $this->meter_reading_at_issue);
        }

        return (float) ($this->attributes['operating_hours_used'] ?? 0);
    }

    public function getStatusBadge(): string
    {
        return match ($this->status) {
            'active' => '<span class="badge bg-primary">Active</span>',
            'returned' => '<span class="badge bg-success">Returned</span>',
            'extended' => '<span class="badge bg-warning">Extended</span>',
            'damaged' => '<span class="badge bg-danger">Returned (Damaged)</span>',
            'lost' => '<span class="badge bg-dark">Not Returned / Scrapped</span>',
            default => '<span class="badge bg-secondary">Unknown</span>',
        };
    }
}
