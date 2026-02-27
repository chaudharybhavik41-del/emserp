<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:core.activity_log.view');
    }

    /**
     * Display activity logs listing.
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')
            ->latest();

        // Search
        if ($search = trim($request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('subject_name', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%");
            });
        }

        // Filter by user
        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        // Filter by action
        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        // Filter by subject type
        if ($subjectType = $request->get('subject_type')) {
            $query->where('subject_type', $subjectType);
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
        $users = User::orderBy('name')->get(['id', 'name']);
        $actions = ActivityLog::distinct()->pluck('action')->sort()->values();
        $subjectTypes = ActivityLog::distinct()
            ->whereNotNull('subject_type')
            ->pluck('subject_type')
            ->map(fn($type) => class_basename($type))
            ->unique()
            ->sort()
            ->values();

        return view('activity-logs.index', compact('logs', 'users', 'actions', 'subjectTypes'));
    }

    /**
     * Display specific activity log details.
     */
    public function show(ActivityLog $activityLog)
    {
        $activityLog->load('user');

        return view('activity-logs.show', compact('activityLog'));
    }

    /**
     * Export activity logs.
     */
    public function export(Request $request)
    {
        $query = ActivityLog::with('user')->latest();

        // Apply same filters as index
        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }
        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->take(10000)->get();

        $filename = 'activity-logs-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Date/Time',
                'User',
                'Action',
                'Description',
                'Subject Type',
                'Subject',
                'IP Address',
                'URL',
            ]);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user_name ?? 'System',
                    $log->action,
                    $log->description,
                    $log->subject_type ? class_basename($log->subject_type) : '',
                    $log->subject_name,
                    $log->ip_address,
                    $log->url,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Clear old activity logs (admin only).
     */
    public function clear(Request $request)
    {
        $request->validate([
            'days' => ['required', 'integer', 'min:30'],
        ]);

        $days = $request->input('days');
        $cutoffDate = now()->subDays($days);

        $count = ActivityLog::where('created_at', '<', $cutoffDate)->count();
        ActivityLog::where('created_at', '<', $cutoffDate)->delete();

        ActivityLog::logCustom(
            'logs_cleared',
            "Cleared {$count} activity logs older than {$days} days"
        );

        return back()->with('success', "Cleared {$count} activity log entries.");
    }
}
