<?php

namespace App\Services;

use App\Models\PwaPushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WebPushService
{
    protected static bool $transportMissingLogged = false;

    /**
     * Send a web push payload to all active subscriptions of a user.
     *
     * @return array{queued:int,sent:int,failed:int,expired:int,skipped:bool,reason:?string}
     */
    public function sendToUser(User $user, array $payload): array
    {
        $subscriptions = PwaPushSubscription::query()
            ->where('user_id', $user->id)
            ->whereNull('disabled_at')
            ->get();

        return $this->sendToSubscriptions($subscriptions, $payload);
    }

    /**
     * Send a payload to a set of push subscriptions.
     *
     * @param  Collection<int, PwaPushSubscription>|iterable<PwaPushSubscription>  $subscriptions
     * @return array{queued:int,sent:int,failed:int,expired:int,skipped:bool,reason:?string}
     */
    public function sendToSubscriptions(iterable $subscriptions, array $payload): array
    {
        $collection = $subscriptions instanceof Collection
            ? $subscriptions
            : collect($subscriptions);

        if (!config('pwa.push.enabled', true)) {
            return $this->result(skipped: true, reason: 'push_disabled');
        }

        if ($collection->isEmpty()) {
            return $this->result(skipped: true, reason: 'no_subscriptions');
        }

        $config = $this->vapidConfig();
        if (!$config['publicKey'] || !$config['privateKey'] || !$config['subject']) {
            return $this->result(skipped: true, reason: 'vapid_not_configured');
        }

        $webPushClass = 'Minishlink\\WebPush\\WebPush';
        $subscriptionClass = 'Minishlink\\WebPush\\Subscription';

        if (!class_exists($webPushClass) || !class_exists($subscriptionClass)) {
            if (!self::$transportMissingLogged) {
                Log::warning('Web push transport package missing. Install minishlink/web-push to enable server push dispatch.');
                self::$transportMissingLogged = true;
            }
            return $this->result(skipped: true, reason: 'transport_missing');
        }

        $auth = [
            'VAPID' => [
                'subject' => $config['subject'],
                'publicKey' => $config['publicKey'],
                'privateKey' => $config['privateKey'],
            ],
        ];

        $defaultOptions = [
            'TTL' => max(60, (int) config('pwa.push.ttl', 300)),
            'urgency' => (string) config('pwa.push.urgency', 'normal'),
        ];

        $queued = 0;
        $sent = 0;
        $failed = 0;
        $expired = 0;
        $byEndpoint = [];
        $byId = [];

        try {
            $webPush = new $webPushClass($auth, $defaultOptions);
            if (method_exists($webPush, 'setReuseVAPIDHeaders')) {
                $webPush->setReuseVAPIDHeaders(true);
            }

            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                $body = json_encode(['title' => 'ERP Alert', 'body' => 'You have a new ERP notification.']);
            }

            /** @var PwaPushSubscription $subscription */
            foreach ($collection as $subscription) {
                if (!($subscription instanceof PwaPushSubscription) || empty($subscription->endpoint)) {
                    continue;
                }

                $byEndpoint[(string) $subscription->endpoint] = $subscription;
                $byId[(int) $subscription->id] = $subscription;

                $client = $subscriptionClass::create([
                    'endpoint' => (string) $subscription->endpoint,
                    'publicKey' => $subscription->public_key ?: null,
                    'authToken' => $subscription->auth_token ?: null,
                    'contentEncoding' => $subscription->content_encoding ?: null,
                ]);

                $webPush->queueNotification($client, $body, $defaultOptions);
                $queued++;
            }

            foreach ($webPush->flush() as $report) {
                $endpoint = '';
                $reason = null;
                $isSuccess = false;
                $isExpired = false;

                try {
                    $isSuccess = (bool) $report->isSuccess();
                    $isExpired = method_exists($report, 'isSubscriptionExpired') && $report->isSubscriptionExpired();
                    $reason = method_exists($report, 'getReason') ? $report->getReason() : null;
                    if (method_exists($report, 'getRequest')) {
                        $request = $report->getRequest();
                        if ($request && method_exists($request, 'getUri')) {
                            $uri = $request->getUri();
                            $endpoint = is_object($uri) && method_exists($uri, '__toString')
                                ? (string) $uri
                                : (string) $uri;
                        }
                    }
                } catch (\Throwable $e) {
                    $reason = $e->getMessage();
                }

                $record = $endpoint ? ($byEndpoint[$endpoint] ?? null) : null;

                if ($isSuccess) {
                    $sent++;
                    if ($record) {
                        $this->recordDelivery(
                            $record,
                            status: 'sent',
                            error: null,
                            markSuccess: true,
                            disableSubscription: false
                        );
                    }
                    continue;
                }

                $failed++;
                if ($record && $isExpired) {
                    $expired++;
                }

                if ($record) {
                    $this->recordDelivery(
                        $record,
                        status: $isExpired ? 'expired' : 'failed',
                        error: $reason,
                        markSuccess: false,
                        disableSubscription: $isExpired
                    );
                }

                Log::warning('Web push delivery failed', [
                    'subscription_id' => $record?->id,
                    'endpoint' => $endpoint ?: null,
                    'reason' => $reason,
                    'expired' => $isExpired,
                ]);
            }
        } catch (\Throwable $e) {
            foreach ($byId as $record) {
                $this->recordDelivery(
                    $record,
                    status: 'failed',
                    error: 'dispatch_error: ' . $e->getMessage(),
                    markSuccess: false,
                    disableSubscription: false
                );
            }

            Log::error('Web push dispatch crashed', [
                'error' => $e->getMessage(),
            ]);

            return $this->result(
                queued: $queued,
                sent: $sent,
                failed: max($failed, $queued > 0 ? $queued - $sent : 0),
                expired: $expired,
                skipped: false,
                reason: 'dispatch_error'
            );
        }

        return $this->result(
            queued: $queued,
            sent: $sent,
            failed: $failed,
            expired: $expired,
            skipped: false,
            reason: null
        );
    }

    /**
     * Build a standard ERP push payload.
     */
    public function makeAlertPayload(
        string $title,
        string $message,
        ?string $url = null,
        ?string $level = null,
        ?array $meta = null,
        ?string $type = null
    ): array {
        return [
            'title' => $title,
            'body' => $message,
            'url' => $url ?: '/notifications',
            'tag' => $type ?: 'system-alert',
            'data' => [
                'level' => $level,
                'type' => $type ?: 'system_alert',
                'meta' => $meta ?: [],
                'emitted_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array{publicKey:string,privateKey:string,subject:string}
     */
    protected function vapidConfig(): array
    {
        return [
            'publicKey' => (string) config('pwa.push.vapid_public_key', ''),
            'privateKey' => (string) config('pwa.push.vapid_private_key', ''),
            'subject' => (string) config('pwa.push.vapid_subject', ''),
        ];
    }

    /**
     * @return array{queued:int,sent:int,failed:int,expired:int,skipped:bool,reason:?string}
     */
    protected function result(
        int $queued = 0,
        int $sent = 0,
        int $failed = 0,
        int $expired = 0,
        bool $skipped = false,
        ?string $reason = null
    ): array {
        return compact('queued', 'sent', 'failed', 'expired', 'skipped', 'reason');
    }

    protected function recordDelivery(
        PwaPushSubscription $record,
        string $status,
        ?string $error,
        bool $markSuccess,
        bool $disableSubscription
    ): void {
        try {
            $update = [
                'last_push_attempt_at' => now(),
                'last_push_status' => $status,
                'last_push_error' => $error ? mb_substr($error, 0, 2000) : null,
                'push_attempt_count' => max(0, (int) $record->push_attempt_count) + 1,
            ];

            if ($markSuccess) {
                $update['last_push_success_at'] = now();
                $update['last_seen_at'] = now();
                $update['disabled_at'] = null;
                $update['last_push_error'] = null;
            } elseif ($disableSubscription) {
                $update['disabled_at'] = now();
            }

            $record->forceFill($update)->save();
        } catch (\Throwable $e) {
            Log::warning('Failed to persist push delivery status', [
                'subscription_id' => $record->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
