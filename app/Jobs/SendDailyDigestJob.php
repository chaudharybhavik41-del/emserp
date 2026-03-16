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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SendDailyDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * Mail template code used for digest newsletter.
     *
     * NOTE: This template is seeded by migration: 2026_01_19_000050_seed_support_digest_mail_template.php
     */
    private const TEMPLATE_CODE = 'support_daily_digest';

    public function __construct(
        public ?string $digestDate = null,
        public ?int $triggeredByUserId = null,
        public bool $force = false
    ) {}

    public function handle(DailyDigestService $service, MailService $mailService): void
    {
        $digestDate = $this->digestDate
            ? Carbon::parse($this->digestDate)->startOfDay()
            : Carbon::yesterday()->startOfDay();
        $digestDateString = $digestDate->toDateString();

        $lock = Cache::lock('support-digest:' . $digestDateString, 600);

        if (!$lock->get()) {
            $this->writeLog(
                digestDate: $digestDate,
                status: 'skipped',
                recipients: [],
                summary: [
                    'meta' => [
                        'force' => $this->force,
                        'skip_reason' => 'concurrent_send_locked',
                    ],
                ],
                error: 'Skipped daily digest because another send for this digest date is already in progress.'
            );

            return;
        }

        try {
            if (!$this->force && $this->alreadyDelivered($digestDateString)) {
                $this->writeLog(
                    digestDate: $digestDate,
                    status: 'skipped',
                    recipients: [],
                    summary: [
                        'meta' => [
                            'force' => false,
                            'skip_reason' => 'already_delivered',
                        ],
                    ],
                    error: 'Skipped daily digest because this digest date was already delivered. Use force resend to send it again.'
                );

                return;
            }

            if (!Schema::hasTable('support_digest_recipients')) {
                $this->writeLog(
                    digestDate: $digestDate,
                    status: 'skipped',
                    recipients: [],
                    summary: [
                        'meta' => [
                            'force' => $this->force,
                            'skip_reason' => 'missing_recipient_table',
                        ],
                    ],
                    error: 'Skipped daily digest because the recipient configuration table is missing.'
                );

                return;
            }

            $recipients = SupportDigestRecipient::query()
                ->with('user:id,email,is_active')
                ->where('is_active', true)
                ->get();

            $emails = [];
            $skipped = [];

            foreach ($recipients as $recipient) {
                $email = strtolower(trim((string) $recipient->resolved_email));

                if ($recipient->user_id !== null && !$recipient->user?->is_active) {
                    $skipped[] = [
                        'recipient' => $recipient->user?->name ?: ('user:' . $recipient->user_id),
                        'reason' => 'inactive_user',
                    ];
                    continue;
                }

                if ($email === '') {
                    $skipped[] = [
                        'recipient' => $recipient->user?->name ?: ('recipient:' . $recipient->id),
                        'reason' => 'missing_email',
                    ];
                    continue;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skipped[] = [
                        'recipient' => $email,
                        'reason' => 'invalid_email',
                    ];
                    continue;
                }

                $emails[] = $email;
            }

            $emails = array_values(array_unique($emails));

            if (empty($emails)) {
                Log::info('Daily digest: no deliverable recipients configured.', [
                    'digest_date' => $digestDateString,
                ]);

                $this->writeLog(
                    digestDate: $digestDate,
                    status: 'skipped',
                    recipients: [],
                    summary: [
                        'meta' => [
                            'force' => $this->force,
                            'attempted_count' => 0,
                            'sent_count' => 0,
                            'failed_count' => 0,
                            'skipped_count' => count($skipped),
                            'skip_reason' => 'no_deliverable_recipients',
                            'skipped_recipients' => $skipped,
                        ],
                    ],
                    error: 'Daily digest was skipped because no active recipients had a deliverable email address.'
                );

                return;
            }

            $digest = $service->build($digestDate);

            // Render digest HTML once (used in mail template placeholder {{digest_html}})
            $digestHtml = view('emails.support.daily_digest', [
                'digest' => $digest,
            ])->render();

            // Optional: used only if the template doesn't have a profile selected and
            // we need to fall back to the profile resolver.
            $company = Company::query()->where('is_default', true)->first();

            $sent = [];
            $errors = [];

            foreach ($emails as $email) {
                try {
                    $mailService->sendTemplate(
                        templateCode: self::TEMPLATE_CODE,
                        to: $email,
                        data: [
                            'date'        => (string) ($digest['date'] ?? $digestDateString),
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
                        'digest_date' => $digestDateString,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $status = empty($errors)
                ? 'sent'
                : (!empty($sent) ? 'partial' : 'failed');

            $this->writeLog(
                digestDate: $digestDate,
                status: $status,
                recipients: $sent,
                summary: [
                    'store'      => $digest['store'] ?? [],
                    'production' => $digest['production'] ?? [],
                    'crm'        => $digest['crm'] ?? [],
                    'purchase'   => $digest['purchase'] ?? [],
                    'payments'   => $digest['payments'] ?? [],
                    'meta'       => [
                        'force' => $this->force,
                        'attempted_count' => count($emails),
                        'sent_count' => count($sent),
                        'failed_count' => count($errors),
                        'skipped_count' => count($skipped),
                        'skipped_recipients' => $skipped,
                    ],
                ],
                error: empty($errors) ? null : implode("\n", $errors)
            );
        } finally {
            rescue(static fn () => $lock->release(), report: false);
        }
    }

    protected function alreadyDelivered(string $digestDate): bool
    {
        if (!Schema::hasTable('support_digest_logs')) {
            return false;
        }

        return SupportDigestLog::query()
            ->whereDate('digest_date', $digestDate)
            ->whereIn('status', ['sent', 'partial'])
            ->exists();
    }

    protected function writeLog(
        Carbon $digestDate,
        string $status,
        array $recipients,
        array $summary,
        ?string $error = null
    ): void {
        if (!Schema::hasTable('support_digest_logs')) {
            return;
        }

        try {
            SupportDigestLog::create([
                'digest_date'  => $digestDate->toDateString(),
                'status'       => $status,
                'sent_at'      => now(),
                'recipients'   => $recipients,
                'summary'      => $summary,
                'error'        => $error,
                'triggered_by' => $this->triggeredByUserId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write digest log', [
                'digest_date' => $digestDate->toDateString(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
