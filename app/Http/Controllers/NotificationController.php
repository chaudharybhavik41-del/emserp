<?php

namespace App\Http\Controllers;

use App\Services\NotificationPayloadService;
use App\Services\NotificationPreferenceService;
use App\Services\NotificationService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function __construct()
    {
        // Everyone logged in can see their own notifications
        $this->middleware('auth');

       
    }

    /**
     * List notifications for the current user.
     */
    public function index(
        Request $request,
        NotificationPayloadService $payloads,
        NotificationPreferenceService $preferences
    )
    {
        $user = $request->user();

        $allNotifications = $user->notifications()->latest()->get();
        $statusFilter = trim((string) $request->query('status', ''));
        $typeFilter = trim((string) $request->query('type', ''));
        $search = trim((string) $request->query('q', ''));
        $perPage = min(100, max(10, (int) $request->integer('per_page', 20)));

        $typeOptions = $allNotifications
            ->map(function ($notification) use ($payloads) {
                $payload = $payloads->normalize((array) ($notification->data ?? []), class_basename($notification->type));

                return (string) ($payload['type'] ?? '');
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $filtered = $allNotifications
            ->when($statusFilter === 'unread', fn ($items) => $items->whereNull('read_at'))
            ->when($statusFilter === 'read', fn ($items) => $items->whereNotNull('read_at'))
            ->when($typeFilter !== '', function ($items) use ($typeFilter) {
                return $items->filter(function ($notification) use ($typeFilter) {
                    return (string) data_get($notification->data, 'type', class_basename($notification->type)) === $typeFilter;
                });
            })
            ->when($search !== '', function ($items) use ($search) {
                $needle = Str::lower($search);

                return $items->filter(function ($notification) use ($needle) {
                    $haystack = Str::lower(implode(' ', array_filter([
                        (string) data_get($notification->data, 'type', class_basename($notification->type)),
                        (string) data_get($notification->data, 'title', ''),
                        (string) data_get($notification->data, 'message', ''),
                        (string) $notification->type,
                    ])));

                    return str_contains($haystack, $needle);
                });
            })
            ->values();

        $page = max(1, (int) $request->integer('page', 1));
        $notifications = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $unreadCount = $allNotifications->whereNull('read_at')->count();
        $readCount = $allNotifications->whereNotNull('read_at')->count();
        $totalCount = $allNotifications->count();
        $channelLabels = $preferences->channelLabels();
        $preferenceState = $preferences->getPreferences($user);
        $preferenceTypes = $preferences->availableTypesFor($user, $payloads);

        return view('notifications.index', compact(
            'notifications',
            'unreadCount',
            'readCount',
            'totalCount',
            'typeOptions',
            'statusFilter',
            'typeFilter',
            'search',
            'perPage',
            'channelLabels',
            'preferenceState',
            'preferenceTypes'
        ));
    }

    /**
     * Mark one notification as read.
     */
    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return redirect()
            ->route('notifications.index')
            ->with('success', 'Notification marked as read.');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return redirect()
            ->route('notifications.index')
            ->with('success', 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return redirect()
            ->route('notifications.index')
            ->with('success', 'Notification deleted.');
    }

    public function clearRead(Request $request)
    {
        $deleted = $request->user()->readNotifications()->delete();

        return redirect()
            ->route('notifications.index')
            ->with('success', $deleted > 0 ? 'Read notifications cleared.' : 'No read notifications to clear.');
    }

    public function updatePreferences(
        Request $request,
        NotificationPreferenceService $preferences,
        NotificationPayloadService $payloads
    )
    {
        $user = $request->user();
        $availableTypes = $preferences->availableTypesFor($user, $payloads)->keyBy('type');
        $channels = $preferences->channels();

        $channelSettings = [];
        foreach ($channels as $channel) {
            $channelSettings[$channel] = $request->boolean('channels.' . $channel);
        }

        $typeSettings = [];
        foreach ((array) $request->input('type_keys', []) as $encodedType) {
            $type = $preferences->decodeType((string) $encodedType);

            if (! $type || ! $availableTypes->has($type)) {
                continue;
            }

            foreach ($channels as $channel) {
                $typeSettings[$type][$channel] = $request->boolean('type_channels.' . $encodedType . '.' . $channel);
            }
        }

        $user->forceFill([
            'notification_preferences' => [
                'channels' => $channelSettings,
                'types' => $typeSettings,
            ],
        ])->save();

        return redirect()
            ->route('notifications.index')
            ->with('success', 'Notification preferences updated.');
    }

    /**
     * Send a test system alert to the current user.
     */
    public function sendTest(NotificationService $notificationService)
    {
        $user = auth()->user();
        $title = 'Test System Alert';
        $message = 'This is a test system notification generated from the EMS Infra ERP notification module.';

        $meta = [
            'triggered_by_user_id' => $user->id,
            'triggered_at' => now()->toDateTimeString(),
        ];

        $notificationService->sendSystemAlertToUser($user, $title, $message, $meta);

        return redirect()
            ->route('notifications.index')
            ->with('success', 'Test notification sent. You should see it in the list below.');
    }
}
