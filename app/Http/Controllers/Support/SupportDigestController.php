<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Jobs\SendDailyDigestJob;
use App\Models\Company;
use App\Models\Support\SupportDigestRecipient;
use App\Models\User;
use App\Services\MailService;
use App\Services\Support\DailyDigestService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportDigestController extends Controller
{
    /**
     * Mail template code used for digest newsletter.
     */
    private const TEMPLATE_CODE = 'support_daily_digest';

    public function __construct()
    {
        $this->middleware('permission:support.digest.view')->only(['preview', 'sendTest']);
        $this->middleware('permission:support.digest.send')->only(['send']);
        $this->middleware('permission:support.digest.update')->only(['recipients', 'updateRecipients']);
    }

    public function preview(Request $request, DailyDigestService $service): View
    {
        $date = $request->input('date');
        $digestDate = $date ? Carbon::parse($date)->startOfDay() : Carbon::yesterday()->startOfDay();

        $digest = $service->build($digestDate);

        return view('support.digest.preview', [
            'digest'     => $digest,
            'digestDate' => $digestDate,
        ]);
    }

    public function recipients(Request $request): View
    {
        $users = User::query()->orderBy('name')->get();

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
        ]);
    }

    public function updateRecipients(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_ids'   => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'emails'     => ['nullable', 'array'],
            'emails.*'   => ['string', 'email'],
            'add_email'  => ['nullable', 'string', 'email'],
        ]);

        $userIds = array_map('intval', $data['user_ids'] ?? []);
        $emails = array_map(fn($e) => strtolower(trim((string) $e)), $data['emails'] ?? []);
        $emails = array_values(array_unique(array_filter($emails)));

        if (!empty($data['add_email'])) {
            $emails[] = strtolower(trim((string) $data['add_email']));
            $emails = array_values(array_unique($emails));
        }

        // Disable all then enable selected (keeps history, but ensures one source of truth)
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

        return back()->with('success', 'Digest recipients updated successfully.');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $data['date'] ?? Carbon::yesterday()->toDateString();

        SendDailyDigestJob::dispatch($date, $request->user()?->id);

        return back()->with('success', 'Daily digest has been queued for sending.');
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
}
