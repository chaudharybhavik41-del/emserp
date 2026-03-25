<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $email = (string) $validated['email'];
        $password = (string) $validated['password'];
        $ipAddress = (string) $request->ip();
        $userAgent = $request->userAgent();
        $deviceName = trim((string) ($validated['device_name'] ?? ''));
        $throttleKey = $this->throttleKey($email, $ipAddress);

        $this->ensureIsNotRateLimited($throttleKey);

        $lockoutMinutes = (int) setting('lockout_duration_minutes', 30);
        if (LoginLog::isAccountLocked($email, $lockoutMinutes)) {
            LoginLog::logFailure($email, $ipAddress, LoginLog::FAILURE_ACCOUNT_LOCKED, $userAgent);

            throw ValidationException::withMessages([
                'email' => "Account is temporarily locked. Please try again after {$lockoutMinutes} minutes.",
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user && ! $user->is_active) {
            LoginLog::logFailure($email, $ipAddress, LoginLog::FAILURE_ACCOUNT_DISABLED, $userAgent, $user->id);
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact administrator.',
            ]);
        }

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            $failureReason = $user ? LoginLog::FAILURE_INVALID_PASSWORD : LoginLog::FAILURE_USER_NOT_FOUND;
            LoginLog::logFailure($email, $ipAddress, $failureReason, $userAgent, $user?->id);
            RateLimiter::hit($throttleKey);

            $maxAttempts = (int) setting('max_login_attempts', 5);
            $recentFailures = LoginLog::recentFailedAttempts($email, $lockoutMinutes);

            if ($recentFailures >= $maxAttempts) {
                LoginLog::logAccountLocked($email, $ipAddress, $user?->id);

                throw ValidationException::withMessages([
                    'email' => "Too many failed attempts. Account locked for {$lockoutMinutes} minutes.",
                ]);
            }

            $remainingAttempts = max(0, $maxAttempts - $recentFailures);

            throw ValidationException::withMessages([
                'email' => "Invalid credentials. {$remainingAttempts} attempts remaining.",
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user->updateLastLogin($ipAddress);
        LoginLog::logSuccess($user, $ipAddress, $userAgent);

        ActivityLog::logCustom(
            ActivityLog::ACTION_LOGIN,
            "User logged in via mobile companion: {$user->name}",
            $user,
            [
                'ip_address' => $ipAddress,
                'source' => 'mobile_companion_api',
                'device_name' => $deviceName !== '' ? $deviceName : null,
            ]
        );

        $token = $user->createToken($this->tokenName($deviceName, $userAgent), ['mobile:companion'])->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_code' => $user->employee_code,
                'designation' => $user->designation,
            ],
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_code' => $user->employee_code,
                'designation' => $user->designation,
                'is_active' => (bool) $user->is_active,
            ],
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            LoginLog::logLogout($user, (string) $request->ip(), $request->userAgent());

            ActivityLog::logCustom(
                ActivityLog::ACTION_LOGOUT,
                "User logged out via mobile companion: {$user->name}",
                $user,
                ['source' => 'mobile_companion_api']
            );
        }

        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }

    private function ensureIsNotRateLimited(string $throttleKey): void
    {
        if (! RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($throttleKey);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(string $email, string $ipAddress): string
    {
        return Str::transliterate(Str::lower($email.'|'.$ipAddress));
    }

    private function tokenName(string $deviceName, ?string $userAgent): string
    {
        $base = $deviceName !== ''
            ? $deviceName
            : (trim((string) $userAgent) !== '' ? (string) $userAgent : 'device');

        $slug = Str::limit(Str::slug($base, '-'), 40, '');

        return 'mobile-' . ($slug !== '' ? $slug : 'device');
    }
}
