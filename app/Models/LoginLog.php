<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'location',
        'event_type',
        'failure_reason',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Event types
     */
    const EVENT_LOGIN_SUCCESS = 'login_success';
    const EVENT_LOGIN_FAILED = 'login_failed';
    const EVENT_LOGOUT = 'logout';
    const EVENT_PASSWORD_RESET_REQUESTED = 'password_reset_requested';
    const EVENT_PASSWORD_RESET_COMPLETED = 'password_reset_completed';
    const EVENT_ACCOUNT_LOCKED = 'account_locked';
    const EVENT_ACCOUNT_UNLOCKED = 'account_unlocked';
    const EVENT_SESSION_EXPIRED = 'session_expired';

    /**
     * Failure reasons
     */
    const FAILURE_INVALID_PASSWORD = 'invalid_password';
    const FAILURE_USER_NOT_FOUND = 'user_not_found';
    const FAILURE_ACCOUNT_DISABLED = 'account_disabled';
    const FAILURE_ACCOUNT_LOCKED = 'account_locked';
    const FAILURE_EMAIL_NOT_VERIFIED = 'email_not_verified';

    /**
     * User relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a successful login.
     */
    public static function logSuccess(User $user, string $ipAddress, ?string $userAgent = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'event_type' => self::EVENT_LOGIN_SUCCESS,
            'created_at' => now(),
        ]);
    }

    /**
     * Log a failed login attempt.
     */
    public static function logFailure(
        string $email,
        string $ipAddress,
        string $reason,
        ?string $userAgent = null,
        ?int $userId = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'email' => $email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'event_type' => self::EVENT_LOGIN_FAILED,
            'failure_reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Log a logout event.
     */
    public static function logLogout(User $user, string $ipAddress, ?string $userAgent = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'event_type' => self::EVENT_LOGOUT,
            'created_at' => now(),
        ]);
    }

    /**
     * Log password reset request.
     */
    public static function logPasswordResetRequested(string $email, string $ipAddress, ?int $userId = null): self
    {
        return self::create([
            'user_id' => $userId,
            'email' => $email,
            'ip_address' => $ipAddress,
            'event_type' => self::EVENT_PASSWORD_RESET_REQUESTED,
            'created_at' => now(),
        ]);
    }

    /**
     * Log password reset completion.
     */
    public static function logPasswordResetCompleted(User $user, string $ipAddress): self
    {
        return self::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $ipAddress,
            'event_type' => self::EVENT_PASSWORD_RESET_COMPLETED,
            'created_at' => now(),
        ]);
    }

    /**
     * Log account locked.
     */
    public static function logAccountLocked(string $email, string $ipAddress, ?int $userId = null): self
    {
        return self::create([
            'user_id' => $userId,
            'email' => $email,
            'ip_address' => $ipAddress,
            'event_type' => self::EVENT_ACCOUNT_LOCKED,
            'created_at' => now(),
        ]);
    }

    /**
     * Get recent failed attempts for an email.
     */
    public static function recentFailedAttempts(string $email, int $minutes = 30): int
    {
        return self::where('email', $email)
            ->where('event_type', self::EVENT_LOGIN_FAILED)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    /**
     * Check if account is currently locked.
     */
    public static function isAccountLocked(string $email, int $lockoutMinutes = 30): bool
    {
        return self::where('email', $email)
            ->where('event_type', self::EVENT_ACCOUNT_LOCKED)
            ->where('created_at', '>=', now()->subMinutes($lockoutMinutes))
            ->exists();
    }

    /**
     * Scope for specific event type.
     */
    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for successful logins.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('event_type', self::EVENT_LOGIN_SUCCESS);
    }

    /**
     * Scope for failed attempts.
     */
    public function scopeFailed($query)
    {
        return $query->where('event_type', self::EVENT_LOGIN_FAILED);
    }

    /**
     * Scope for date range.
     */
    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get browser name from user agent.
     */
    public function getBrowserAttribute(): string
    {
        $userAgent = $this->user_agent ?? '';
        
        if (str_contains($userAgent, 'Firefox')) return 'Firefox';
        if (str_contains($userAgent, 'Edge')) return 'Edge';
        if (str_contains($userAgent, 'Chrome')) return 'Chrome';
        if (str_contains($userAgent, 'Safari')) return 'Safari';
        if (str_contains($userAgent, 'Opera')) return 'Opera';
        
        return 'Unknown';
    }

    /**
     * Get platform from user agent.
     */
    public function getPlatformAttribute(): string
    {
        $userAgent = $this->user_agent ?? '';
        
        if (str_contains($userAgent, 'Windows')) return 'Windows';
        if (str_contains($userAgent, 'Mac')) return 'Mac';
        if (str_contains($userAgent, 'Linux')) return 'Linux';
        if (str_contains($userAgent, 'Android')) return 'Android';
        if (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) return 'iOS';
        
        return 'Unknown';
    }
}
