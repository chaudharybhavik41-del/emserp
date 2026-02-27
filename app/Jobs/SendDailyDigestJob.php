<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Support\SupportDigestLog;
use App\Models\Support\SupportDigestRecipient;
use App\Services\MailService;
use App\Services\Support\DailyDigestService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDailyDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Mail template code used for digest newsletter.
     *
     * NOTE: This template is seeded by migration: 2026_01_19_000050_seed_support_digest_mail_template.php
     */
    private const TEMPLATE_CODE = 'support_daily_digest';

    public function __construct(
        public ?string $digestDate = null,
        public ?int $triggeredByUserId = null
    ) {}

    public function handle(DailyDigestService $service, MailService $mailService): void
    {
        $digestDate = $this->digestDate
            ? Carbon::parse($this->digestDate)->startOfDay()
            : Carbon::yesterday()->startOfDay();

        $digest = $service->build($digestDate);

        // Render digest HTML once (used in mail template placeholder {{digest_html}})
        $digestHtml = view('emails.support.daily_digest', [
            'digest' => $digest,
        ])->render();

        // Optional: used only if the template doesn't have a profile selected and
        // we need to fall back to the profile resolver.
        $company = Company::query()->where('is_default', true)->first();

        $recipients = SupportDigestRecipient::query()
            ->with('user')
            ->where('is_active', true)
            ->get();

        $emails = [];
        foreach ($recipients as $r) {
            $email = $r->resolved_email;
            if (!$email) {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $emails[] = strtolower(trim($email));
        }
        $emails = array_values(array_unique($emails));

        if (empty($emails)) {
            Log::info('Daily digest: no active recipients configured.');
            return;
        }

        $sent = [];
        $errors = [];

        foreach ($emails as $email) {
            try {
                $mailService->sendTemplate(
                    templateCode: self::TEMPLATE_CODE,
                    to: $email,
                    data: [
                        'date'       => (string) ($digest['date'] ?? $digestDate->toDateString()),
                        'digest_html' => $digestHtml,
                    ],
                    company: $company,
                    department: null,
                    usage: 'supportDigest'
                );
                $sent[] = $email;
            } catch (\Throwable $e) {
                $errors[] = $email . ': ' . $e->getMessage();
                Log::error('Failed to send daily digest email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Persist log (best-effort; do not throw)
        try {
            SupportDigestLog::create([
                'digest_date'  => $digestDate->toDateString(),
                'status'       => empty($errors) ? 'sent' : 'failed',
                'sent_at'      => now(),
                'recipients'   => $sent,
                'summary'      => [
                    'store'      => $digest['store'] ?? [],
                    'production' => $digest['production'] ?? [],
                    'crm'        => $digest['crm'] ?? [],
                    'purchase'   => $digest['purchase'] ?? [],
                    'payments'   => $digest['payments'] ?? [],
                ],
                'error'        => empty($errors) ? null : implode("\n", $errors),
                'triggered_by' => $this->triggeredByUserId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write digest log', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
