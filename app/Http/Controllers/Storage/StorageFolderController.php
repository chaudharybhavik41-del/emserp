<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Models\Storage\StorageFolder;
use App\Services\Storage\StorageFolderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StorageFolderController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:storage_folders,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $parent = null;
        if (!empty($data['parent_id'])) {
            $parent = StorageFolder::findOrFail((int) $data['parent_id']);
            $this->authorize('createSubfolder', $parent);
        } else {
            // Root folder creation only for storage admin
            $this->authorize('createRoot', StorageFolder::class);
        }

        $folder = StorageFolder::create([
            'parent_id' => $parent?->id,
            'project_id' => null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        // Inherit access from parent so users can see inside newly created subfolders
        if ($parent) {
            StorageFolderService::copyAccessFromParent($parent, $folder, $user?->id);
        }

        // Ensure creator at least can view/manage (if they created it)
        if ($user) {
            StorageFolderService::applyAccessToTree($folder, $user->id, [
                'can_view' => true,
                'can_upload' => true,
                'can_download' => true,
                'can_edit' => true,
                'can_delete' => true,
                'can_manage_access' => true,
            ], $user->id);
        }

        return redirect()
            ->route('storage.folders.show', $folder)
            ->with('success', 'Folder created successfully.');
    }

    public function update(Request $request, StorageFolder $folder): RedirectResponse
    {
        $this->authorize('update', $folder);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $folder->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool)($data['is_active'] ?? $folder->is_active),
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Folder updated.');
    }

    public function destroy(Request $request, StorageFolder $folder): RedirectResponse
    {
        $this->authorize('delete', $folder);

        $parentId = $folder->parent_id;

        StorageFolderService::deleteFolderTree($folder);

        if ($parentId) {
            return redirect()->route('storage.folders.show', $parentId)->with('success', 'Folder deleted.');
        }

        return redirect()->route('storage.index')->with('success', 'Folder deleted.');
    }
}
