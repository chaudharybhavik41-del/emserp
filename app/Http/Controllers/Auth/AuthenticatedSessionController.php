<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $email = $request->input('email');
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        // Check if account is locked
        $lockoutMinutes = (int) setting('lockout_duration_minutes', 30);
        if (LoginLog::isAccountLocked($email, $lockoutMinutes)) {
            LoginLog::logFailure($email, $ipAddress, LoginLog::FAILURE_ACCOUNT_LOCKED, $userAgent);
            
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => "Account is temporarily locked. Please try again after {$lockoutMinutes} minutes.",
                ]);
        }

        // Check if user exists and is active
        $user = User::where('email', $email)->first();
        
        if ($user && !$user->is_active) {
            LoginLog::logFailure($email, $ipAddress, LoginLog::FAILURE_ACCOUNT_DISABLED, $userAgent, $user->id);
            
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Your account has been deactivated. Please contact administrator.',
                ]);
        }

        try {
            $request->authenticate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log failed attempt
            $failureReason = $user ? LoginLog::FAILURE_INVALID_PASSWORD : LoginLog::FAILURE_USER_NOT_FOUND;
            LoginLog::logFailure($email, $ipAddress, $failureReason, $userAgent, $user?->id);

            // Check if we need to lock the account
            $maxAttempts = (int) setting('max_login_attempts', 5);
            $recentFailures = LoginLog::recentFailedAttempts($email, $lockoutMinutes);

            if ($recentFailures >= $maxAttempts) {
                LoginLog::logAccountLocked($email, $ipAddress, $user?->id);
                
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => "Too many failed attempts. Account locked for {$lockoutMinutes} minutes.",
                ]);
            }

            $remainingAttempts = $maxAttempts - $recentFailures;
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => "Invalid credentials. {$remainingAttempts} attempts remaining.",
            ]);
        }

        $request->session()->regenerate();

        // Get the authenticated user
        $user = Auth::user();

        // Update last login info
        $user->updateLastLogin($ipAddress);

        // Log successful login
        LoginLog::logSuccess($user, $ipAddress, $userAgent);

        // Log activity
        ActivityLog::logCustom(
            ActivityLog::ACTION_LOGIN,
            "User logged in: {$user->name}",
            $user,
            ['ip_address' => $ipAddress]
        );

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        // Log logout
        if ($user) {
            LoginLog::logLogout($user, $ipAddress, $userAgent);

            ActivityLog::logCustom(
                ActivityLog::ACTION_LOGOUT,
                "User logged out: {$user->name}",
                $user
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
