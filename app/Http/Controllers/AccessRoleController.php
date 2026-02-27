<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessRoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:core.access.manage');
    }

    /**
     * Display a listing of roles.
     */
    public function index()
    {
        $roles = Role::where('guard_name', 'web')
            ->withCount('users')
            ->withCount('permissions')
            ->orderBy('name')
            ->get();

        return view('access.roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create()
    {
        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        $groupedPermissions = $this->groupPermissions($permissions);

        return view('access.roles.create', compact('groupedPermissions'));
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        // Create role
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        // Assign permissions
        $permissionNames = $data['permissions'] ?? [];
        $role->syncPermissions($permissionNames);

        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Log activity
        ActivityLog::logCreated($role, "Created role: {$role->name}");

        return redirect()
            ->route('access.roles.index')
            ->with('success', 'Role created successfully.');
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role)
    {
        $role->load(['permissions', 'users']);

        return view('access.roles.show', compact('role'));
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role)
    {
        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        $groupedPermissions = $this->groupPermissions($permissions);
        $rolePermissionNames = $role->permissions->pluck('name')->toArray();

        return view('access.roles.edit', [
            'role' => $role,
            'groupedPermissions' => $groupedPermissions,
            'rolePermissionNames' => $rolePermissionNames,
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role)
    {
        // Prevent editing super-admin role name
        $rules = [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];

        if ($role->name !== 'super-admin') {
            $rules['name'] = ['sometimes', 'filled', 'string', 'max:255', 'unique:roles,name,' . $role->id];
        }

        $data = $request->validate($rules);

        $oldValues = $role->toArray();
        $oldPermissions = $role->permissions->pluck('name')->toArray();

        // Update role name if allowed
        if (isset($data['name']) && $role->name !== 'super-admin') {
            $role->update(['name' => $data['name']]);
        }

        // Sync permissions
        $permissionNames = $data['permissions'] ?? [];
        $role->syncPermissions($permissionNames);

        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Log activity
        ActivityLog::log(
            ActivityLog::ACTION_UPDATED,
            $role,
            "Updated role: {$role->name}",
            ['permissions' => $oldPermissions],
            ['permissions' => $permissionNames]
        );

        return redirect()
            ->route('access.roles.edit', $role)
            ->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role)
    {
        // Prevent deletion of system roles
        if (in_array($role->name, ['super-admin', 'admin', 'viewer'])) {
            return back()->with('error', 'Cannot delete system roles.');
        }

        // Check if role has users
        if ($role->users()->exists()) {
            return back()->with('error', 'Cannot delete role with assigned users. Please remove users first.');
        }

        ActivityLog::logDeleted($role, "Deleted role: {$role->name}");

        $role->delete();

        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('access.roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    /**
     * Duplicate a role.
     */
    public function duplicate(Role $role)
    {
        $newRole = Role::create([
            'name' => $role->name . ' (Copy)',
            'guard_name' => 'web',
        ]);

        // Copy permissions
        $newRole->syncPermissions($role->permissions);

        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        ActivityLog::logCreated($newRole, "Duplicated role from: {$role->name}");

        return redirect()
            ->route('access.roles.edit', $newRole)
            ->with('success', 'Role duplicated successfully. Please rename it.');
    }

    /**
     * Group permissions by module.
     */
    protected function groupPermissions($permissions)
    {
        return $permissions->groupBy(function (Permission $perm) {
            $parts = explode('.', $perm->name);
            if (count($parts) >= 2) {
                return $parts[0] . '.' . $parts[1];
            }
            return $parts[0];
        });
    }
}
