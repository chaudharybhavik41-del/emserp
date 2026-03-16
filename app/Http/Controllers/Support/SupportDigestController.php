<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Jobs\SendDailyDigestJob;
use App\Models\Company;
use App\Models\Support\SupportDigestLog;
use App\Models\Support\SupportDigestRecipient;
use App\Models\User;
use App\Services\MailService;
use App\Services\Support\DailyDigestService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SupportDigestController extends Controller
{
    /**
     * Mail template code used for digest newsletter.
     */
    private const TEMPLATE_CODE = 'support_daily_digest';

    public function __construct()
    {
        $this->middleware('permission:support.digest.view')->only(['preview']);
        $this->middleware('permission:support.digest.send')->only(['send', 'sendTest']);
        $this->middleware('permission:support.digest.update')->only(['recipients', 'updateRecipients']);
    }

    public function preview(Request $request, DailyDigestService $service): View
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $digestDate = !empty($data['date'])
            ? Carbon::parse($data['date'])->startOfDay()
            : Carbon::yesterday()->startOfDay();

        $digest = $service->build($digestDate);

        return view('support.digest.preview', [
            'digest'          => $digest,
            'digestDate'      => $digestDate,
            'recentLogs'      => $this->recentLogs(),
            'recipientHealth' => $this->recipientHealth(),
        ]);
    }

    public function recipients(Request $request): View
    {
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active']);

        if (!Schema::hasTable('support_digest_recipients')) {
            return view('support.digest.recipients', [
                'users'         => $users,
                'activeUserIds' => [],
                'activeEmails'  => [],
                'external'      => collect(),
                'configMissing' => true,
            ]);
        }

        $active = SupportDigestRecipient::query()
            ->where('is_active', true)
            ->get();

        $activeUserIds = $active->whereNotNull('user_id')->pluck('user_id')->all();
        $activeEmails = $active->whereNotNull('email')->pluck('email')->map(fn($e) => strtolower(trim((string)$e)))->all();

        $external = SupportDigestRecipient::query()
            ->whereNotNull('email')
            ->orderBy('email')
            ->get();

        return view('support.digest.recipients', [
            'users'          => $users,
            'activeUserIds'  => $activeUserIds,
            'activeEmails'   => $activeEmails,
            'external'       => $external,
            'configMissing'  => false,
        ]);
    }

    public function updateRecipients(Request $request): RedirectResponse
    {
        if (!Schema::hasTable('support_digest_recipients')) {
            return back()->with('error', 'Digest recipients cannot be updated until the support digest tables are installed.');
        }

        $data = $request->validate([
            'user_ids'   => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'emails'     => ['nullable', 'array'],
            'emails.*'   => ['string', 'email'],
            'add_email'  => ['nullable', 'string', 'email'],
        ]);

        $validator = Validator::make($data, []);
        $validator->after(function ($validator) use ($data): void {
            $userIds = array_values(array_unique(array_map('intval', $data['user_ids'] ?? [])));
            if (empty($userIds)) {
                return;
            }

            $invalidUsers = User::query()
                ->whereIn('id', $userIds)
                ->get(['id', 'name', 'email', 'is_active'])
                ->filter(function (User $user): bool {
                    $email = trim((string) $user->email);

                    return !$user->is_active || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL);
                })
                ->pluck('name')
                ->all();

            if (!empty($invalidUsers)) {
                $validator->errors()->add(
                    'user_ids',
                    'Selected recipients must be active users with a valid email address: ' . implode(', ', $invalidUsers)
                );
            }
        });
        $validator->validate();

        $userIds = array_values(array_unique(array_map('intval', $data['user_ids'] ?? [])));
        $emails = array_map(fn($e) => strtolower(trim((string) $e)), $data['emails'] ?? []);
        $emails = array_values(array_unique(array_filter($emails)));

        if (!empty($data['add_email'])) {
            $emails[] = strtolower(trim((string) $data['add_email']));
            $emails = array_values(array_unique($emails));
        }

        DB::transaction(function () use ($emails, $request, $userIds): void {
            // Keep inactive rows as audit history, but apply the selection atomically.
            SupportDigestRecipient::query()->update(['is_active' => false]);

            foreach ($userIds as $uid) {
                SupportDigestRecipient::updateOrCreate(
                    ['user_id' => $uid],
                    [
                        'email'      => null,
                        'is_active'  => true,
                        'created_by' => $request->user()?->id,
                    ]
                );
            }

            foreach ($emails as $email) {
                SupportDigestRecipient::updateOrCreate(
                    ['email' => $email],
                    [
                        'user_id'    => null,
                        'is_active'  => true,
                        'created_by' => $request->user()?->id,
                    ]
                );
            }
        });

        return back()->with('success', 'Digest recipients updated successfully.');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date'  => ['nullable', 'date'],
            'force' => ['nullable', 'boolean'],
        ]);

        $date = $data['date'] ?? Carbon::yesterday()->toDateString();
        $force = (bool) ($data['force'] ?? false);

        SendDailyDigestJob::dispatch($date, $request->user()?->id, $force);

        return back()->with(
            'success',
            $force
                ? 'Daily digest has been queued for a forced resend.'
                : 'Daily digest has been queued for sending.'
        );
    }

    public function sendTest(Request $request, DailyDigestService $service, MailService $mailService): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $data['date'] ?? Carbon::yesterday()->toDateString();
        $digest = $service->build(Carbon::parse($date));

        $to = $request->user()?->email;
        if (!$to) {
            return back()->with('error', 'Your user does not have an email configured.');
        }

        try {
            $digestHtml = view('emails.support.daily_digest', [
                'digest' => $digest,
            ])->render();

            $company = Company::query()->where('is_default', true)->first();

            $mailService->sendTemplate(
                templateCode: self::TEMPLATE_CODE,
                to: $to,
                data: [
                    'date'       => (string) ($digest['date'] ?? $date),
                    'digest_html' => $digestHtml,
                ],
                company: $company,
                department: null,
                usage: 'supportDigest'
            );

            return back()->with('success', 'Test digest sent to your email: ' . $to);
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to send test digest: ' . $e->getMessage());
        }
    }

    protected function recentLogs()
    {
        if (!Schema::hasTable('support_digest_logs')) {
            return collect();
        }

        return SupportDigestLog::query()
            ->with('triggeredBy:id,name')
            ->latest('sent_at')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    protected function recipientHealth(): array
    {
        if (!Schema::hasTable('support_digest_recipients')) {
            return [
                'active_total'      => 0,
                'deliverable_total' => 0,
                'invalid_total'     => 0,
            ];
        }

        $recipients = SupportDigestRecipient::query()
            ->with('user:id,email,is_active')
            ->where('is_active', true)
            ->get();

        $deliverable = 0;
        foreach ($recipients as $recipient) {
            $email = trim((string) $recipient->resolved_email);
            $isUserRecipient = $recipient->user_id !== null;
            $userIsSendable = !$isUserRecipient
                || ($recipient->user?->is_active && filter_var($email, FILTER_VALIDATE_EMAIL));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $userIsSendable) {
                $deliverable++;
            }
        }

        return [
            'active_total'      => $recipients->count(),
            'deliverable_total' => $deliverable,
            'invalid_total'     => max($recipients->count() - $deliverable, 0),
        ];
    }
}
