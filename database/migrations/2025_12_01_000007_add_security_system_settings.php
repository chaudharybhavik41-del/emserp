<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add security-related system settings
     */
    public function up(): void
    {
        $settings = [
            [
                'key' => 'password_min_length',
                'value' => '8',
                'type' => 'integer',
                'group' => 'security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'password_require_uppercase',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'password_require_number',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'password_require_special',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'password_expiry_days',
                'value' => '0',
                'type' => 'integer',
                'group' => 'security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'password_history_count',
                'value' => '5',
                'type' => 'integer',
                'group' => 'security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'max_login_attempts',
                'value' => '5',
                'type' => 'integer',
                'group' => 'security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'lockout_duration_minutes',
                'value' => '30',
                'type' => 'integer',
                'group' => 'security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'session_timeout_minutes',
                'value' => '120',
                'type' => 'integer',
                'group' => 'security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'allow_multiple_sessions',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')
            ->where('group', 'security')
            ->delete();
    }
};
