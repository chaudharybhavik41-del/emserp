<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    protected const CACHE_KEY = 'system_settings_all';

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return SystemSetting::query()
                ->get()
                ->groupBy('group')
                ->map(function ($items) {
                    return $items->mapWithKeys(function ($item) {
                        return [$item->key => $item->value];
                    })->toArray();
                })->toArray();
        });
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$group][$key] ?? $default;
    }

    public function set(string $group, string $key, mixed $value, string $type = 'string'): void
    {
        SystemSetting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'type' => $type]
        );

        Cache::forget(self::CACHE_KEY);
    }
}
