<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\NotificationPayloadService;
use App\Services\NotificationPreferenceService;
use Illuminate\Notifications\Events\NotificationSending;

class RespectNotificationPreferences
{
    public function __construct(
        protected NotificationPreferenceService $preferences,
        protected NotificationPayloadService $payloads
    ) {
    }

    public function handle(NotificationSending $event): bool
    {
        if (! $event->notifiable instanceof User) {
            return true;
        }

        $payload = $this->payloads->extract($event->notification, $event->notifiable);
        $type = (string) ($payload['type'] ?? 'system_alert');

        return $this->preferences->channelEnabled($event->notifiable, $type, $event->channel);
    }
}
