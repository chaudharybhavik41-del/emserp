<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class StorageAccessController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        // Only super-admins (or whoever has this) can manage access
        $this->middleware('permission:core.access.manage');
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $usersQuery = User::query()->with('roles');

        if ($q !== '') {
            $usersQuery->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $users = $usersQuery->orderBy('name')->paginate(25)->withQueryString();

        $storagePermission = 'storage.view';

        return view('storage.access.index', compact('users', 'q', 'storagePermission'));
    }

    public function update(Request $request, User $user)
    {
        $storagePermission = 'storage.view';
        $grant = $request->boolean('grant');

        // grant/revoke only affects DIRECT permissions
        if ($grant) {
            $user->givePermissionTo($storagePermission);
        } else {
            $user->revokePermissionTo($storagePermission);
        }

        return back()->with('success', 'Storage access updated for ' . ($user->name ?? $user->email));
    }
}
