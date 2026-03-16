<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PwaStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_notifications' => (int) $user->unreadNotifications()->count(),
            'active_push_subscriptions' => (int) $user->pushSubscriptions()->whereNull('disabled_at')->count(),
            'server_time' => now()->toIso8601String(),
            'diagnostics_url' => route('pwa.diagnostics', [], false),
        ]);
    }
}
