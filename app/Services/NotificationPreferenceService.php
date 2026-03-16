<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class NotificationPreferenceService
{
    public function channelLabels(): array
    {
        return (array) config('erp_notifications.channels', []);
    }

    public function channels(): array
    {
        return array_keys($this->channelLabels());
    }

    public function defaultPreferences(): array
    {
        return (array) config('erp_notifications.default_preferences', [
            'channels' => [
                'database' => true,
                'mail' => true,
                'push' => true,
            ],
        ]);
    }

    public function getPreferences(User $user): array
    {
        $stored = is_array($user->notification_preferences ?? null)
            ? $user->notification_preferences
            : [];

        $defaults = $this->defaultPreferences();
        $channels = [];

        foreach ($this->channels() as $channel) {
            $channels[$channel] = (bool) data_get($stored, 'channels.' . $channel, data_get($defaults, 'channels.' . $channel, true));
        }

        $types = [];
        foreach ((array) data_get($stored, 'types', []) as $type => $typeChannels) {
            foreach ($this->channels() as $channel) {
                $types[$type][$channel] = (bool) data_get($typeChannels, $channel, $channels[$channel]);
            }
        }

        return [
            'channels' => $channels,
            'types' => $types,
        ];
    }

    public function channelEnabled(User $user, string $type, string $channel): bool
    {
        $preferences = $this->getPreferences($user);

        if (! array_key_exists($channel, $preferences['channels'])) {
            return true;
        }

        if ($preferences['channels'][$channel] === false) {
            return false;
        }

        return (bool) data_get($preferences, 'types.' . $type . '.' . $channel, true);
    }

    public function availableTypesFor(User $user, NotificationPayloadService $payloads): Collection
    {
        $configTypes = collect((array) config('erp_notifications.types', []))
            ->keys();

        $seenTypes = $user->notifications()
            ->get()
            ->map(function ($notification) use ($payloads) {
                $payload = $payloads->normalize((array) ($notification->data ?? []), class_basename($notification->type));

                return (string) ($payload['type'] ?? '');
            })
            ->filter()
            ->unique()
            ->values();

        return $configTypes
            ->merge($seenTypes)
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $type) use ($payloads) {
                return [
                    'type' => $type,
                    'label' => $payloads->typeLabel($type),
                    'description' => $payloads->typeDescription($type),
                    'encoded' => $this->encodeType($type),
                ];
            });
    }

    public function encodeType(string $type): string
    {
        return rtrim(strtr(base64_encode($type), '+/', '-_'), '=');
    }

    public function decodeType(string $encoded): ?string
    {
        $normalized = strtr($encoded, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }
}
