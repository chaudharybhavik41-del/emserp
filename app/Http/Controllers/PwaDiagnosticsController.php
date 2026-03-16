<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class PwaDiagnosticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(Request $request): View
    {
        $user = $request->user();

        $serverSummary = [
            'push_enabled' => (bool) config('pwa.push.enabled', true),
            'push_vapid_configured' => filled((string) config('pwa.push.vapid_public_key', ''))
                && filled((string) config('pwa.push.vapid_private_key', ''))
                && filled((string) config('pwa.push.vapid_subject', '')),
            'background_sync_enabled' => (bool) config('pwa.background_sync.enabled', true),
            'safe_update_prompt' => (bool) config('pwa.runtime.safe_update_prompt', true),
            'critical_prefixes' => array_values(config('pwa.background_sync.critical_form_path_prefixes', [])),
            'subscription_count' => (int) $user->pushSubscriptions()->count(),
            'active_subscription_count' => (int) $user->pushSubscriptions()->whereNull('disabled_at')->count(),
            'unread_notifications' => (int) $user->unreadNotifications()->count(),
            'manage_push_report' => $user->can('notifications.manage'),
        ];

        return view('pwa.diagnostics', compact('serverSummary'));
    }
}
