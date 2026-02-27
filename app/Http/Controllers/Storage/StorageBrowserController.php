<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Models\Storage\StorageFolder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorageBrowserController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $folders = StorageFolder::query()
            ->whereNull('parent_id')
            ->viewableBy($user)
            ->withCount(['children', 'files'])
            ->orderBy('name')
            ->get();

        return view('storage.index', [
            'folders' => $folders,
        ]);
    }

    public function show(Request $request, StorageFolder $folder): View
    {
        $this->authorize('view', $folder);

        $user = $request->user();
        $isStorageAdmin = $user && method_exists($user, 'can') && $user->can('storage.admin');

        $subfolders = StorageFolder::query()
            ->where('parent_id', $folder->id)
            ->viewableBy($user)
            ->withCount(['children', 'files'])
            ->orderBy('name')
            ->get();

        $files = $folder->files()
            ->with('uploader')
            ->orderBy('original_name')
            ->get();

        $moveTargetsQuery = StorageFolder::query()
            ->where('id', '!=', $folder->id)
            ->orderBy('name');

        if (!$isStorageAdmin) {
            $moveTargetsQuery->whereHas('accesses', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('can_upload', true);
            });
        }

        $moveTargets = $moveTargetsQuery->get(['id', 'name', 'parent_id']);

        $breadcrumbs = [];
        $cursor = $folder;
        while ($cursor) {
            $breadcrumbs[] = $cursor;
            $cursor = $cursor->parent;
        }
        $breadcrumbs = array_reverse($breadcrumbs);

        return view('storage.folders.show', [
            'folder' => $folder,
            'subfolders' => $subfolders,
            'files' => $files,
            'access' => $folder->accessForUser($user->id),
            'breadcrumbs' => $breadcrumbs,
            'parentFolder' => $folder->parent,
            'moveTargets' => $moveTargets,
        ]);
    }
}
