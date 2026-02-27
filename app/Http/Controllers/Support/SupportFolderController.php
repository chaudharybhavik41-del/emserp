<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportFolderController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:support.document.view')->only(['index']);
        $this->middleware('permission:support.document.update')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index(): View
    {
        $folders = SupportFolder::query()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $flattened = $this->flattenFolders($folders);

        return view('support.folders.index', [
            'folders'   => $folders,
            'flat'      => $flattened,
        ]);
    }

    public function create(): View
    {
        $folders = SupportFolder::query()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('support.folders.create', [
            'folders' => $this->flattenFolders($folders),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'parent_id'   => ['nullable', 'integer', 'exists:support_folders,id'],
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;

        SupportFolder::create($data);

        return redirect()->route('support.folders.index')
            ->with('success', 'Folder created successfully.');
    }

    public function edit(SupportFolder $folder): View
    {
        $folders = SupportFolder::query()
            ->where('id', '!=', $folder->id)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('support.folders.edit', [
            'folder'  => $folder,
            'folders' => $this->flattenFolders($folders),
        ]);
    }

    public function update(Request $request, SupportFolder $folder): RedirectResponse
    {
        $data = $request->validate([
            'parent_id'   => ['nullable', 'integer', 'exists:support_folders,id'],
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        // Prevent self-parenting
        if (!empty($data['parent_id']) && (int) $data['parent_id'] === (int) $folder->id) {
            return back()->withErrors(['parent_id' => 'A folder cannot be its own parent.']);
        }

        $folder->fill([
            'parent_id'   => $data['parent_id'] ?? null,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => (bool) ($data['is_active'] ?? true),
            'updated_by'  => $request->user()?->id,
        ])->save();

        return redirect()->route('support.folders.index')
            ->with('success', 'Folder updated successfully.');
    }

    public function destroy(SupportFolder $folder): RedirectResponse
    {
        $hasChildren = SupportFolder::query()->where('parent_id', $folder->id)->exists();
        if ($hasChildren) {
            return back()->with('error', 'Cannot delete a folder that has sub-folders.');
        }

        $hasDocs = $folder->documents()->exists();
        if ($hasDocs) {
            return back()->with('error', 'Cannot delete a folder that contains documents.');
        }

        $folder->delete();

        return redirect()->route('support.folders.index')
            ->with('success', 'Folder deleted successfully.');
    }

    /**
     * Build a flattened list with depth for indentation in UI.
     *
     * @param \Illuminate\Support\Collection<int,SupportFolder> $folders
     */
    protected function flattenFolders($folders, ?int $parentId = null, int $depth = 0): array
    {
        $out = [];

        foreach ($folders->where('parent_id', $parentId) as $folder) {
            $out[] = [
                'id'    => $folder->id,
                'name'  => $folder->name,
                'depth' => $depth,
            ];
            $out = array_merge($out, $this->flattenFolders($folders, $folder->id, $depth + 1));
        }

        return $out;
    }
}
