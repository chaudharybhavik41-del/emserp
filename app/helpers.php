<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (!function_exists('setting')) {
    /**
     * Get a system setting value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function setting(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'system_setting_' . $key;

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = DB::table('system_settings')
                ->where('key', $key)
                ->first();

            if (!$setting) {
                return $default;
            }

            $value = $setting->value;

            // Cast based on type
            return match ($setting->type) {
                'integer' => (int) $value,
                'float' => (float) $value,
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'array', 'json' => json_decode($value, true),
                default => $value,
            };
        });
    }
}

if (!function_exists('set_setting')) {
    /**
     * Set a system setting value.
     *
     * @param string $key
     * @param mixed $value
     * @param string $type
     * @param string $group
     * @return void
     */
    function set_setting(string $key, mixed $value, string $type = 'string', string $group = 'general'): void
    {
        // Convert value to string for storage
        $storedValue = match ($type) {
            'array', 'json' => json_encode($value),
            'boolean' => $value ? 'true' : 'false',
            default => (string) $value,
        };

        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'group' => $group,
                'updated_at' => now(),
            ]
        );

        // Clear cache
        Cache::forget('system_setting_' . $key);
    }
}

if (!function_exists('clear_settings_cache')) {
    /**
     * Clear all settings cache.
     *
     * @return void
     */
    function clear_settings_cache(): void
    {
        $keys = DB::table('system_settings')->pluck('key');

        foreach ($keys as $key) {
            Cache::forget('system_setting_' . $key);
        }
    }
}
