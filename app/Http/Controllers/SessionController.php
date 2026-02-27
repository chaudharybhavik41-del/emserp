<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show user's active sessions.
     */
    public function index()
    {
        $user = Auth::user();
        $currentSessionId = session()->getId();

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) use ($currentSessionId) {
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'browser' => $this->getBrowser($session->user_agent),
                    'platform' => $this->getPlatform($session->user_agent),
                    'last_activity' => \Carbon\Carbon::createFromTimestamp($session->last_activity),
                    'is_current' => $session->id === $currentSessionId,
                ];
            });

        return view('profile.sessions', compact('sessions'));
    }

    /**
     * Terminate a specific session.
     */
    public function destroy(Request $request, $sessionId)
    {
        $user = Auth::user();
        $currentSessionId = session()->getId();

        // Prevent terminating current session from here
        if ($sessionId === $currentSessionId) {
            return back()->with('error', 'Cannot terminate current session. Use logout instead.');
        }

        // Verify session belongs to user
        $session = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$session) {
            return back()->with('error', 'Session not found.');
        }

        DB::table('sessions')->where('id', $sessionId)->delete();

        return back()->with('success', 'Session terminated successfully.');
    }

    /**
     * Terminate all other sessions.
     */
    public function destroyOthers(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = Auth::user();
        $currentSessionId = session()->getId();

        $count = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        return back()->with('success', "Terminated {$count} other session(s).");
    }

    /**
     * Get browser name from user agent.
     */
    protected function getBrowser(?string $userAgent): string
    {
        if (!$userAgent) return 'Unknown';

        if (str_contains($userAgent, 'Firefox')) return 'Firefox';
        if (str_contains($userAgent, 'Edge')) return 'Microsoft Edge';
        if (str_contains($userAgent, 'Chrome')) return 'Chrome';
        if (str_contains($userAgent, 'Safari')) return 'Safari';
        if (str_contains($userAgent, 'Opera')) return 'Opera';

        return 'Unknown';
    }

    /**
     * Get platform from user agent.
     */
    protected function getPlatform(?string $userAgent): string
    {
        if (!$userAgent) return 'Unknown';

        if (str_contains($userAgent, 'Windows')) return 'Windows';
        if (str_contains($userAgent, 'Mac')) return 'macOS';
        if (str_contains($userAgent, 'Linux')) return 'Linux';
        if (str_contains($userAgent, 'Android')) return 'Android';
        if (str_contains($userAgent, 'iPhone')) return 'iPhone';
        if (str_contains($userAgent, 'iPad')) return 'iPad';

        return 'Unknown';
    }
}
