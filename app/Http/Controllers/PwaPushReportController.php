<?php

namespace App\Http\Controllers;

use App\Models\PwaPushSubscription;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PwaPushReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $status = strtolower(trim((string) $request->query('status', '')));
        $search = trim((string) $request->query('q', ''));

        $subscriptionsQuery = PwaPushSubscription::query()
            ->with('user:id,name,email')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($status === 'active') {
            $subscriptionsQuery->whereNull('disabled_at');
        } elseif ($status === 'disabled') {
            $subscriptionsQuery->whereNotNull('disabled_at');
        } elseif ($status === 'failed') {
            $subscriptionsQuery->whereIn('last_push_status', ['failed', 'expired']);
        } elseif ($status === 'sent') {
            $subscriptionsQuery->where('last_push_status', 'sent');
        }

        if ($search !== '') {
            $subscriptionsQuery->where(function ($query) use ($search) {
                $query->where('endpoint', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $subscriptions = $subscriptionsQuery->paginate(30)->withQueryString();

        $staleCutoff = now()->subDays((int) config('pwa.push.prune_days', 90));

        $summary = [
            'total' => PwaPushSubscription::query()->count(),
            'active' => PwaPushSubscription::query()->whereNull('disabled_at')->count(),
            'disabled' => PwaPushSubscription::query()->whereNotNull('disabled_at')->count(),
            'sent_24h' => PwaPushSubscription::query()->where('last_push_success_at', '>=', now()->subDay())->count(),
            'failed_24h' => PwaPushSubscription::query()
                ->whereIn('last_push_status', ['failed', 'expired'])
                ->where('last_push_attempt_at', '>=', now()->subDay())
                ->count(),
            'queue_pending' => DB::table('jobs')->count(),
            'queue_failed' => DB::table('failed_jobs')->count(),
            'stale' => PwaPushSubscription::query()
                ->whereNull('disabled_at')
                ->where(function ($query) use ($staleCutoff) {
                    $query->where('last_seen_at', '<=', $staleCutoff)
                        ->orWhere(function ($q) use ($staleCutoff) {
                            $q->whereNull('last_seen_at')->where('created_at', '<=', $staleCutoff);
                        });
                })
                ->count(),
        ];

        return view('notifications.push_report', [
            'subscriptions' => $subscriptions,
            'summary' => $summary,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function prune(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'in:dry-run,apply,hard-delete'],
            'days' => ['nullable', 'integer', 'min:7', 'max:3650'],
            'delete_days' => ['nullable', 'integer', 'min:7', 'max:3650'],
        ]);

        $mode = (string) ($validated['mode'] ?? 'dry-run');
        $options = [
            '--days' => (int) ($validated['days'] ?? config('pwa.push.prune_days', 90)),
            '--delete-days' => (int) ($validated['delete_days'] ?? config('pwa.push.delete_disabled_after_days', 180)),
        ];

        if ($mode === 'dry-run') {
            $options['--dry-run'] = true;
        }

        if ($mode === 'hard-delete') {
            $options['--hard-delete'] = true;
        }

        Artisan::call('pwa:subscriptions:prune', $options);
        $output = trim((string) Artisan::output());

        return redirect()
            ->route('notifications.push-report')
            ->with('success', $output !== '' ? $output : 'Push subscription cleanup command executed.');
    }

    public function testUser(User $user, NotificationService $notificationService)
    {
        if (!$user->is_active) {
            return redirect()
                ->route('notifications.push-report')
                ->with('error', 'Selected user is inactive. Test push not sent.');
        }

        $notificationService->sendSystemAlertToUser(
            $user,
            'Push Report Test',
            'Push test triggered from PWA Push Delivery Report.',
            ['source' => 'push-report-ui', 'triggered_at' => now()->toDateTimeString()],
            '/notifications/push-report',
            'info',
            'pwa_push_report_test'
        );

        return redirect()
            ->route('notifications.push-report')
            ->with('success', "Queued test notification for {$user->email}.");
    }
}
