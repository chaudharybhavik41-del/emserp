<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Models\Storage\StorageFolder;
use App\Models\Storage\StorageFolderUserAccess;
use App\Models\User;
use App\Services\Storage\StorageFolderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorageFolderAccessController extends Controller
{
    public function index(Request $request, StorageFolder $folder): View
    {
        $this->authorize('manageAccess', $folder);

        $accesses = StorageFolderUserAccess::query()
            ->where('storage_folder_id', $folder->id)
            ->with('user')
            ->orderBy('id', 'desc')
            ->get();

        $users = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('storage.folders.access', [
            'folder' => $folder,
            'accesses' => $accesses,
            'users' => $users,
        ]);
    }

    public function store(Request $request, StorageFolder $folder): RedirectResponse
    {
        $this->authorize('manageAccess', $folder);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],

            'can_view' => ['nullable', 'boolean'],
            'can_upload' => ['nullable', 'boolean'],
            'can_download' => ['nullable', 'boolean'],
            'can_edit' => ['nullable', 'boolean'],
            'can_delete' => ['nullable', 'boolean'],
            'can_manage_access' => ['nullable', 'boolean'],

            // default true: apply same access to subfolders
            'apply_to_subfolders' => ['nullable', 'boolean'],
        ]);

        $perms = [
            'can_view' => (bool)($data['can_view'] ?? false),
            'can_upload' => (bool)($data['can_upload'] ?? false),
            'can_download' => (bool)($data['can_download'] ?? false),
            'can_edit' => (bool)($data['can_edit'] ?? false),
            'can_delete' => (bool)($data['can_delete'] ?? false),
            'can_manage_access' => (bool)($data['can_manage_access'] ?? false),
        ];

        $apply = (bool)($data['apply_to_subfolders'] ?? true);

        if ($apply) {
            StorageFolderService::applyAccessToTree($folder, (int)$data['user_id'], $perms, $request->user()?->id);
        } else {
            StorageFolderUserAccess::updateOrCreate(
                ['storage_folder_id' => $folder->id, 'user_id' => (int)$data['user_id']],
                array_merge($perms, ['created_by' => $request->user()?->id])
            );
        }

        return back()->with('success', 'Access updated.');
    }

    public function destroy(Request $request, StorageFolder $folder, User $user): RedirectResponse
    {
        $this->authorize('manageAccess', $folder);

        StorageFolderService::revokeAccessFromTree($folder, $user->id);

        return back()->with('success', 'Access removed.');
    }
}
