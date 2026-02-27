<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'employee_code',
        'name',
        'email',
        'phone',
        'designation',
        'profile_photo',
        'password',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
        'last_login_at'     => 'datetime',
    ];

    /**
     * Departments this user belongs to.
     */
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_user')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get the user's primary department.
     */
    public function primaryDepartment()
    {
        return $this->departments()->wherePivot('is_primary', true)->first();
    }

    /**
     * Departments headed by this user.
     */
    public function headOfDepartments()
    {
        return $this->hasMany(Department::class, 'head_user_id');
    }

    /**
     * Login history for this user.
     */
    public function loginLogs()
    {
        return $this->hasMany(LoginLog::class);
    }

    /**
     * Activity logs for this user.
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Password history for this user.
     */
    public function passwordHistories()
    {
        return $this->hasMany(PasswordHistory::class);
    }

    /**
     * Role change history for this user.
     */
    public function roleHistory()
    {
        return $this->hasMany(UserRoleHistory::class);
    }

    /**
     * Check if user account is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Activate the user account.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the user account.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Update last login information.
     */
    public function updateLastLogin(string $ipAddress): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);
    }

    /**
     * Get the user's display name with employee code.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->employee_code) {
            return "{$this->name} ({$this->employee_code})";
        }
        return $this->name;
    }

    /**
     * Get the user's initials.
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return substr($initials, 0, 2);
    }

    /**
     * Check if user has specific permission either directly or via roles.
     */
    public function canPerform($permission, $guardName = null): bool
    {
    if (!$this->is_active) {
        return false;
    }
    return $this->hasPermissionTo($permission, $guardName); // Uses trait's method
	}
    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive users.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Generate a unique employee code.
     */
    public static function generateEmployeeCode(string $prefix = 'EMP'): string
    {
        $year = date('Y');
        $lastUser = self::withTrashed()
            ->where('employee_code', 'like', "{$prefix}-{$year}-%")
            ->orderBy('employee_code', 'desc')
            ->first();

        if ($lastUser && preg_match("/{$prefix}-{$year}-(\d+)/", $lastUser->employee_code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf("%s-%s-%04d", $prefix, $year, $nextNumber);
    }
}
