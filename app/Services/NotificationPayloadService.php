<?php

namespace App\Services;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NotificationPayloadService
{
    public function extract(Notification $notification, object $notifiable): array
    {
        $payload = [];

        foreach (['toArray', 'toDatabase'] as $method) {
            if (! method_exists($notification, $method)) {
                continue;
            }

            try {
                $candidate = (array) $notification->{$method}($notifiable);
            } catch (\Throwable) {
                $candidate = [];
            }

            if (! empty($candidate)) {
                $payload = $candidate;
                break;
            }
        }

        return $this->normalize($payload, class_basename($notification));
    }

    public function normalize(array $payload, ?string $fallbackType = null): array
    {
        $type = trim((string) ($payload['type'] ?? $fallbackType ?? 'system_alert'));
        $title = trim((string) ($payload['title'] ?? 'Notification'));
        $message = trim((string) ($payload['message'] ?? ''));
        $url = isset($payload['url']) ? trim((string) $payload['url']) : null;
        $level = isset($payload['level']) ? trim((string) $payload['level']) : null;

        if ($title === '') {
            $title = Str::headline(str_replace(['.', '_'], ' ', $type));
        }

        return array_merge($payload, [
            'type' => $type !== '' ? $type : 'system_alert',
            'title' => $title,
            'message' => $message,
            'url' => $url !== '' ? $url : null,
            'level' => $level !== '' ? $level : null,
        ]);
    }

    public function typeLabel(string $type): string
    {
        return (string) (config('erp_notifications.types.' . $type . '.label')
            ?? Str::headline(str_replace(['.', '_'], ' ', $type)));
    }

    public function typeDescription(string $type): ?string
    {
        $description = config('erp_notifications.types.' . $type . '.description');

        return is_string($description) && $description !== '' ? $description : null;
    }
}
