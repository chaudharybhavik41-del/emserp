<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;

class LoginLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:core.login_log.view');
    }

    /**
     * Display login logs listing.
     */
    public function index(Request $request)
    {
        $query = LoginLog::with('user')
            ->latest('created_at');

        // Search
        if ($search = trim($request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        // Filter by user
        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        // Filter by event type
        if ($eventType = $request->get('event_type')) {
            $query->where('event_type', $eventType);
        }

        // Filter by date range
        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->paginate(50)->withQueryString();

        // Get filter options
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $eventTypes = [
            LoginLog::EVENT_LOGIN_SUCCESS => 'Login Success',
            LoginLog::EVENT_LOGIN_FAILED => 'Login Failed',
            LoginLog::EVENT_LOGOUT => 'Logout',
            LoginLog::EVENT_PASSWORD_RESET_REQUESTED => 'Password Reset Requested',
            LoginLog::EVENT_PASSWORD_RESET_COMPLETED => 'Password Reset Completed',
            LoginLog::EVENT_ACCOUNT_LOCKED => 'Account Locked',
            LoginLog::EVENT_ACCOUNT_UNLOCKED => 'Account Unlocked',
        ];

        // Statistics
        $stats = [
            'total_logins_today' => LoginLog::whereDate('created_at', today())
                ->where('event_type', LoginLog::EVENT_LOGIN_SUCCESS)
                ->count(),
            'failed_attempts_today' => LoginLog::whereDate('created_at', today())
                ->where('event_type', LoginLog::EVENT_LOGIN_FAILED)
                ->count(),
            'unique_users_today' => LoginLog::whereDate('created_at', today())
                ->where('event_type', LoginLog::EVENT_LOGIN_SUCCESS)
                ->distinct('user_id')
                ->count('user_id'),
            'locked_accounts' => LoginLog::where('event_type', LoginLog::EVENT_ACCOUNT_LOCKED)
                ->where('created_at', '>=', now()->subMinutes((int) setting('lockout_duration_minutes', 30)))
                ->distinct('email')
                ->count('email'),
        ];

        return view('login-logs.index', compact('logs', 'users', 'eventTypes', 'stats'));
    }

    /**
     * Display login history for specific user.
     */
    public function userHistory(User $user)
    {
        $logs = LoginLog::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->latest('created_at')
            ->paginate(50);

        return view('login-logs.user-history', compact('user', 'logs'));
    }

    /**
     * Export login logs.
     */
    public function export(Request $request)
    {
        $query = LoginLog::with('user')->latest('created_at');

        // Apply filters
        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($eventType = $request->get('event_type')) {
            $query->where('event_type', $eventType);
        }
        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->take(10000)->get();

        $filename = 'login-logs-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Date/Time',
                'Email',
                'User',
                'Event Type',
                'IP Address',
                'Browser',
                'Platform',
                'Failure Reason',
            ]);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->email,
                    $log->user?->name ?? 'N/A',
                    str_replace('_', ' ', ucfirst($log->event_type)),
                    $log->ip_address,
                    $log->browser,
                    $log->platform,
                    $log->failure_reason,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Unlock a locked account.
     */
    public function unlockAccount(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');

        // Remove lock entries
        LoginLog::where('email', $email)
            ->where('event_type', LoginLog::EVENT_ACCOUNT_LOCKED)
            ->where('created_at', '>=', now()->subMinutes((int) setting('lockout_duration_minutes', 30)))
            ->delete();

        // Log the unlock
        $user = User::where('email', $email)->first();
        LoginLog::create([
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $request->ip(),
            'event_type' => LoginLog::EVENT_ACCOUNT_UNLOCKED,
            'created_at' => now(),
        ]);

        return back()->with('success', "Account {$email} has been unlocked.");
    }

    /**
     * Clear old login logs.
     */
    public function clear(Request $request)
    {
        $request->validate([
            'days' => ['required', 'integer', 'min:30'],
        ]);

        $days = $request->input('days');
        $cutoffDate = now()->subDays($days);

        $count = LoginLog::where('created_at', '<', $cutoffDate)->count();
        LoginLog::where('created_at', '<', $cutoffDate)->delete();

        return back()->with('success', "Cleared {$count} login log entries.");
    }
}
