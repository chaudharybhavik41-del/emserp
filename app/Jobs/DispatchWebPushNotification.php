<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchWebPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [60, 180, 600];

    /**
     * @param  array<string,mixed>  $payload
     */
    public function __construct(
        public int $userId,
        public array $payload
    ) {
        $this->onQueue((string) config('pwa.push.queue', 'default'));
    }

    public function handle(WebPushService $webPushService): void
    {
        $user = User::query()->find($this->userId);
        if (!$user) {
            return;
        }

        $webPushService->sendToUser($user, $this->payload);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DispatchWebPushNotification job failed', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
