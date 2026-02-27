<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;

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
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->paginate(20);
        $unreadCount   = $user->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
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
        $request->user()->unreadNotifications->markAsRead();

        return redirect()
            ->route('notifications.index')
            ->with('success', 'All notifications marked as read.');
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
