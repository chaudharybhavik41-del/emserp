<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MachineCalibrationRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'calibration_number',
        'calibration_date',
        'due_date',
        'next_due_date',
        'calibration_agency',
        'certificate_number',
        'standard_followed',
        'parameters_calibrated',
        'result',
        'observations',
        'remarks',
        'certificate_file_path',
        'report_file_path',
        'calibration_cost',
        'status',
        'performed_by',
        'verified_by',
        'created_by',
    ];

    protected $casts = [
        'calibration_date' => 'date',
        'due_date' => 'date',
        'next_due_date' => 'date',
        'parameters_calibrated' => 'array',
        'calibration_cost' => 'decimal:2',
    ];

    // Relationships

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
            ->orWhere(function ($q) {
                $q->where('status', 'scheduled')
                    ->whereDate('due_date', '<', now());
            });
    }

    public function scopeDueSoon($query, $days = 15)
    {
        return $query->where('status', 'scheduled')
            ->whereDate('due_date', '<=', now()->addDays($days))
            ->whereDate('due_date', '>=', now());
    }

    // Helpers

    public function isOverdue(): bool
    {
        if ($this->status === 'completed' || $this->status === 'cancelled') {
            return false;
        }

        return $this->due_date && $this->due_date->lt(now());
    }

    public function isDueSoon($days = 15): bool
    {
        if ($this->status === 'completed' || $this->status === 'cancelled') {
            return false;
        }

        if (!$this->due_date) {
            return false;
        }

        return $this->due_date->lte(now()->addDays($days)) && $this->due_date->gte(now());
    }

    public function hasCertificate(): bool
    {
        return !empty($this->certificate_file_path) && Storage::exists($this->certificate_file_path);
    }

    public function hasReport(): bool
    {
        return !empty($this->report_file_path) && Storage::exists($this->report_file_path);
    }

    public function getCertificateUrl(): ?string
    {
        if ($this->hasCertificate()) {
            return Storage::url($this->certificate_file_path);
        }
        return null;
    }

    public function getReportUrl(): ?string
    {
        if ($this->hasReport()) {
            return Storage::url($this->report_file_path);
        }
        return null;
    }

    public function getResultBadgeClass(): string
    {
        return match($this->result) {
            'pass' => 'success',
            'pass_with_adjustment' => 'warning',
            'fail' => 'danger',
            default => 'secondary',
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'completed' => 'success',
            'scheduled' => $this->isDueSoon() ? 'warning' : 'info',
            'overdue' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Generate calibration number: CAL-YY-XXXX
     */
    public static function generateNumber(): string
    {
        $year = now()->format('y');
        $prefix = 'CAL-' . $year . '-';

        $lastRecord = self::where('calibration_number', 'like', $prefix . '%')
            ->orderByDesc('calibration_number')
            ->first();

        if ($lastRecord) {
            $lastNum = (int) substr($lastRecord->calibration_number, -4);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Delete associated files when record is deleted
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($record) {
            if ($record->certificate_file_path) {
                Storage::delete($record->certificate_file_path);
            }
            if ($record->report_file_path) {
                Storage::delete($record->report_file_path);
            }
        });
    }
}
