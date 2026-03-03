<?php

namespace App\Listeners;

use App\Jobs\DispatchWebPushNotification;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Notifications\Events\NotificationSent;

class QueueWebPushForDatabaseNotification
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        if (!config('pwa.push.enabled', true)) {
            return;
        }

        $notifiable = $event->notifiable;
        if (!($notifiable instanceof User)) {
            return;
        }

        $data = [];
        if (method_exists($event->notification, 'toArray')) {
            try {
                $data = (array) $event->notification->toArray($notifiable);
            } catch (\Throwable $e) {
                $data = [];
            }
        }

        $title = trim((string) ($data['title'] ?? 'ERP Alert'));
        $message = trim((string) ($data['message'] ?? 'You have a new notification.'));
        $url = isset($data['url']) ? (string) $data['url'] : '/notifications';
        $level = isset($data['level']) ? (string) $data['level'] : null;
        $type = isset($data['type']) ? (string) $data['type'] : 'system_alert';

        $payload = app(WebPushService::class)->makeAlertPayload(
            title: $title !== '' ? $title : 'ERP Alert',
            message: $message !== '' ? $message : 'You have a new notification.',
            url: $url !== '' ? $url : '/notifications',
            level: $level,
            meta: ['notification_class' => $event->notification::class],
            type: $type
        );

        DispatchWebPushNotification::dispatch((int) $notifiable->id, $payload)
            ->onQueue((string) config('pwa.push.queue', 'default'));
    }
}
