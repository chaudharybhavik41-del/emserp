<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class AccessUserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:core.access.manage');
    }

    public function index()
    {
        $users = User::with('roles')
            ->orderBy('name')
            ->paginate(25);

        return view('access.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        $groupedPermissions = $permissions->groupBy(function (Permission $perm) {
            $parts = explode('.', $perm->name);
            if (count($parts) >= 2) {
                return $parts[0] . '.' . $parts[1];
            }
            return $parts[0];
        });

        // Direct permissions assigned specifically to this user
        $directPermissionNames = $user->permissions->pluck('name')->toArray();

        // Permissions inherited via roles (for display only)
        $viaRolesPermissionNames = $user->getPermissionsViaRoles()
            ->pluck('name')
            ->unique()
            ->toArray();

        return view('access.users.edit', [
            'user'                   => $user,
            'groupedPermissions'     => $groupedPermissions,
            'directPermissionNames'  => $directPermissionNames,
            'viaRolesPermissionNames'=> $viaRolesPermissionNames,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $permissionNames = $data['permissions'] ?? [];

        // Only direct permissions are synced here.
        // Role-based permissions remain untouched.
        $user->syncPermissions($permissionNames);

        return redirect()
            ->route('access.users.edit', $user)
            ->with('success', 'User permissions updated successfully.');
    }
}
