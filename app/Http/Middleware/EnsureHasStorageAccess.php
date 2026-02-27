<?php

namespace App\Http\Middleware;

use App\Models\Storage\StorageFolderUserAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasStorageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        // Module-level access (what your Storage Access page grants)
        $hasModuleAccess = method_exists($user, 'can') && $user->can('storage.view');

        // Admin override
        $isAdmin = method_exists($user, 'can') && $user->can('storage.admin');

        // Backward-compatible: allow if user has at least one folder shared to them
        $hasAnyFolderAccess = StorageFolderUserAccess::query()
            ->where('user_id', $user->id)
            ->where('can_view', true)
            ->exists();

        if (!$hasModuleAccess && !$hasAnyFolderAccess && !$isAdmin) {
            abort(403, 'You do not have access to Storage.');
        }

        return $next($request);
    }
}
