<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Models\UserRoleHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:core.user.view')->only(['index', 'show']);
        $this->middleware('permission:core.user.create')->only(['create', 'store']);
        $this->middleware('permission:core.user.update')->only(['edit', 'update', 'toggleStatus']);
        $this->middleware('permission:core.user.delete')->only(['destroy', 'restore', 'forceDelete']);
    }

    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::with(['roles', 'departments'])
            ->orderBy('name');

        // Search
        if ($search = trim($request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $status = $request->get('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'trashed') {
                $query->onlyTrashed();
            }
        }

        // Filter by role
        if ($roleId = $request->get('role')) {
            $query->whereHas('roles', function ($q) use ($roleId) {
                $q->where('roles.id', $roleId);
            });
        }

        // Filter by department
        if ($departmentId = $request->get('department')) {
            $query->whereHas('departments', function ($q) use ($departmentId) {
                $q->where('departments.id', $departmentId);
            });
        }

        $users = $query->paginate(25)->withQueryString();
        $roles = Role::orderBy('name')->get();
        $departments = Department::active()->orderBy('name')->get();

        return view('users.index', compact('users', 'roles', 'departments'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        $user = new User();
        $user->employee_code = User::generateEmployeeCode();
        
        $roles = Role::orderBy('name')->get();
        $departments = Department::active()->orderBy('name')->get();

        return view('users.create', compact('user', 'roles', 'departments'));
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        
        // Hash password
        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active', true);

        // Handle profile photo
        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $request->file('profile_photo')
                ->store('profile-photos', 'public');
        }

        $user = User::create($data);

        // Store initial password in history
        PasswordHistory::storePassword($user, $data['password']);

        // Assign roles
        if ($request->has('roles')) {
            $roles = Role::whereIn('id', $request->input('roles', []))->get();
            $user->syncRoles($roles);

            // Log role assignments
            foreach ($roles as $role) {
                UserRoleHistory::logAssigned($user, $role, Auth::user(), 'Initial assignment during user creation');
            }
        }

        // Assign departments
        $this->syncDepartments($user, $request);

        // Log activity
        ActivityLog::logCreated($user, "Created user: {$user->name}");

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load(['roles', 'departments', 'loginLogs' => function ($q) {
            $q->latest()->take(10);
        }]);

        $activityLogs = ActivityLog::forSubject($user)
            ->latest()
            ->take(20)
            ->get();

        $roleHistory = $user->roleHistory()
            ->with(['role', 'performer'])
            ->latest('created_at')
            ->take(10)
            ->get();

        return view('users.show', compact('user', 'activityLogs', 'roleHistory'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        $departments = Department::active()->orderBy('name')->get();

        $userRoleIds = $user->roles->pluck('id')->toArray();
        $userDepartmentIds = $user->departments->pluck('id')->toArray();
        $primaryDepartmentId = $user->departments()
            ->wherePivot('is_primary', true)
            ->value('departments.id');

        return view('users.edit', compact(
            'user',
            'roles',
            'departments',
            'userRoleIds',
            'userDepartmentIds',
            'primaryDepartmentId'
        ));
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $oldValues = $user->toArray();
        $data = $request->validated();

        // Handle password update
        if (!empty($data['password'])) {
            // Check password history
            $historyCount = (int) setting('password_history_count', 5);
            if ($historyCount > 0 && PasswordHistory::wasPasswordUsed($user, $data['password'], $historyCount)) {
                return back()
                    ->withInput()
                    ->withErrors(['password' => "You cannot reuse your last {$historyCount} passwords."]);
            }

            $data['password'] = Hash::make($data['password']);
            PasswordHistory::storePassword($user, $data['password']);
        } else {
            unset($data['password']);
        }

        // Handle profile photo
        if ($request->hasFile('profile_photo')) {
            // Delete old photo
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $data['profile_photo'] = $request->file('profile_photo')
                ->store('profile-photos', 'public');
        }

        // Handle photo removal
        if ($request->boolean('remove_photo') && $user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
            $data['profile_photo'] = null;
        }

        $data['is_active'] = $request->boolean('is_active', true);

        $user->update($data);

        // Handle roles
        $this->syncRolesWithHistory($user, $request->input('roles', []));

        // Handle departments
        $this->syncDepartments($user, $request);

        // Log activity
        ActivityLog::logUpdated($user, $oldValues, "Updated user: {$user->name}");

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Toggle user active status.
     */
    public function toggleStatus(User $user)
    {
        // Prevent self-deactivation
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $oldStatus = $user->is_active;
        $user->is_active = !$user->is_active;
        $user->save();

        $action = $user->is_active ? 'activated' : 'deactivated';
        ActivityLog::logCustom(
            $user->is_active ? ActivityLog::ACTION_ACTIVATED : ActivityLog::ACTION_DEACTIVATED,
            ucfirst($action) . " user: {$user->name}",
            $user
        );

        return back()->with('success', "User {$action} successfully.");
    }

    /**
     * Soft delete the specified user.
     */
    public function destroy(User $user)
    {
        // Prevent self-deletion
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Check if user has critical relationships
        if ($user->headOfDepartments()->exists()) {
            return back()->with('error', 'Cannot delete user who is head of departments. Please reassign first.');
        }

        ActivityLog::logDeleted($user, "Deleted user: {$user->name}");

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        ActivityLog::logRestored($user, "Restored user: {$user->name}");

        return redirect()
            ->route('users.index')
            ->with('success', 'User restored successfully.');
    }

    /**
     * Permanently delete a user.
     */
    public function forceDelete($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        
        // Delete profile photo
        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        ActivityLog::logCustom(
            ActivityLog::ACTION_FORCE_DELETED,
            "Permanently deleted user: {$user->name} ({$user->email})",
            null,
            ['user_id' => $user->id, 'email' => $user->email, 'name' => $user->name]
        );

        $user->forceDelete();

        return redirect()
            ->route('users.index')
            ->with('success', 'User permanently deleted.');
    }

    /**
     * Reset user password (by admin).
     */
    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Check password history
        $historyCount = (int) setting('password_history_count', 5);
        if ($historyCount > 0 && PasswordHistory::wasPasswordUsed($user, $request->password, $historyCount)) {
            return back()->withErrors(['password' => "Cannot reuse last {$historyCount} passwords."]);
        }

        $hashedPassword = Hash::make($request->password);
        $user->update(['password' => $hashedPassword]);
        PasswordHistory::storePassword($user, $hashedPassword);

        ActivityLog::logCustom(
            ActivityLog::ACTION_PASSWORD_CHANGED,
            "Password reset by admin for user: {$user->name}",
            $user
        );

        return back()->with('success', 'Password reset successfully.');
    }

    /**
     * Sync roles with history tracking.
     */
    protected function syncRolesWithHistory(User $user, array $newRoleIds): void
    {
        $currentRoleIds = $user->roles->pluck('id')->toArray();
        $performer = Auth::user();

        // Find removed roles
        $removedIds = array_diff($currentRoleIds, $newRoleIds);
        foreach ($removedIds as $roleId) {
            $role = Role::find($roleId);
            if ($role) {
                UserRoleHistory::logRemoved($user, $role, $performer);
            }
        }

        // Find added roles
        $addedIds = array_diff($newRoleIds, $currentRoleIds);
        foreach ($addedIds as $roleId) {
            $role = Role::find($roleId);
            if ($role) {
                UserRoleHistory::logAssigned($user, $role, $performer);
            }
        }

        // Sync roles
        $roles = Role::whereIn('id', $newRoleIds)->get();
        $user->syncRoles($roles);
    }

    /**
     * Sync departments with primary handling.
     */
    protected function syncDepartments(User $user, Request $request): void
    {
        $departmentIds = $request->input('departments', []);
        $primaryDepartmentId = $request->input('primary_department_id');

        $pivotData = [];
        foreach ($departmentIds as $deptId) {
            $pivotData[$deptId] = [
                'is_primary' => ((string) $primaryDepartmentId === (string) $deptId),
            ];
        }

        $user->departments()->sync($pivotData);
    }
}
