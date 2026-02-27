<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class PasswordHistory extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'password',
        'created_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * User this password belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Store password in history.
     */
    public static function storePassword(User $user, string $hashedPassword): self
    {
        return self::create([
            'user_id' => $user->id,
            'password' => $hashedPassword,
            'created_at' => now(),
        ]);
    }

    /**
     * Check if password was used before.
     */
    public static function wasPasswordUsed(User $user, string $plainPassword, int $historyCount = 5): bool
    {
        $recentPasswords = self::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take($historyCount)
            ->get();

        foreach ($recentPasswords as $history) {
            if (Hash::check($plainPassword, $history->password)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean old password history (keep only recent N entries).
     */
    public static function cleanOldHistory(User $user, int $keepCount = 10): void
    {
        $oldestToKeep = self::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->skip($keepCount)
            ->take(1)
            ->value('created_at');

        if ($oldestToKeep) {
            self::where('user_id', $user->id)
                ->where('created_at', '<', $oldestToKeep)
                ->delete();
        }
    }
}
