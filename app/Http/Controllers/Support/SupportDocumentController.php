<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Support\SupportDocument;
use App\Models\Support\SupportFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SupportDocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:support.document.view')->only(['index', 'show']);
        $this->middleware('permission:support.document.create')->only(['create', 'store']);
        $this->middleware('permission:support.document.update')->only(['edit', 'update']);
        $this->middleware('permission:support.document.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $folders = SupportFolder::query()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $query = SupportDocument::query()
            ->with(['folder'])
            ->withCount('attachments')
            ->orderByDesc('id');

        $folderId = $request->integer('folder_id');
        if ($folderId) {
            $query->where('support_folder_id', $folderId);
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $documents = $query->paginate(25)->withQueryString();

        return view('support.documents.index', [
            'documents' => $documents,
            'folders'   => $folders,
            'folderId'  => $folderId,
            'q'         => $request->input('q'),
        ]);
    }

    public function create(): View
    {
        $folders = SupportFolder::query()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('support.documents.create', [
            'folders' => $folders,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'support_folder_id' => ['nullable', 'integer', 'exists:support_folders,id'],
            'title'             => ['required', 'string', 'max:255'],
            'code'              => ['nullable', 'string', 'max:50'],
            'description'       => ['nullable', 'string'],
            'tags'              => ['nullable', 'string'], // comma separated
            'is_active'         => ['nullable', 'boolean'],
            'files'             => ['nullable', 'array'],
            'files.*'           => ['file', 'max:20480'], // 20MB per file
        ]);

        $doc = SupportDocument::create([
            'support_folder_id' => $data['support_folder_id'] ?? null,
            'title'             => $data['title'],
            'code'              => $data['code'] ?? null,
            'description'       => $data['description'] ?? null,
            'tags'              => $this->parseTags($data['tags'] ?? null),
            'is_active'         => (bool) ($data['is_active'] ?? true),
            'created_by'        => $request->user()?->id,
            'updated_by'        => $request->user()?->id,
        ]);

        $this->storeAttachmentsFromRequest($request, $doc);

        return redirect()->route('support.documents.show', $doc)
            ->with('success', 'Document created successfully.');
    }

    public function show(SupportDocument $document): View
    {
        $document->load(['folder', 'attachments']);

        return view('support.documents.show', [
            'document' => $document,
        ]);
    }

    public function edit(SupportDocument $document): View
    {
        $folders = SupportFolder::query()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $document->load('attachments');

        return view('support.documents.edit', [
            'document' => $document,
            'folders'  => $folders,
        ]);
    }

    public function update(Request $request, SupportDocument $document): RedirectResponse
    {
        $data = $request->validate([
            'support_folder_id' => ['nullable', 'integer', 'exists:support_folders,id'],
            'title'             => ['required', 'string', 'max:255'],
            'code'              => ['nullable', 'string', 'max:50'],
            'description'       => ['nullable', 'string'],
            'tags'              => ['nullable', 'string'],
            'is_active'         => ['nullable', 'boolean'],
            'files'             => ['nullable', 'array'],
            'files.*'           => ['file', 'max:20480'],
        ]);

        $document->fill([
            'support_folder_id' => $data['support_folder_id'] ?? null,
            'title'             => $data['title'],
            'code'              => $data['code'] ?? null,
            'description'       => $data['description'] ?? null,
            'tags'              => $this->parseTags($data['tags'] ?? null),
            'is_active'         => (bool) ($data['is_active'] ?? true),
            'updated_by'        => $request->user()?->id,
        ])->save();

        $this->storeAttachmentsFromRequest($request, $document);

        return redirect()->route('support.documents.show', $document)
            ->with('success', 'Document updated successfully.');
    }

    public function destroy(SupportDocument $document): RedirectResponse
    {
        $document->load('attachments');

        foreach ($document->attachments as $attachment) {
            $this->deleteAttachmentFile($attachment);
            $attachment->delete();
        }

        $document->delete();

        return redirect()->route('support.documents.index')
            ->with('success', 'Document deleted successfully.');
    }

    protected function parseTags(?string $raw): ?array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $parts = array_filter(array_map(function ($p) {
            $p = trim((string) $p);
            return $p === '' ? null : $p;
        }, preg_split('/,/', $raw) ?: []));

        $parts = array_values(array_unique($parts));

        return empty($parts) ? null : $parts;
    }

    protected function storeAttachmentsFromRequest(Request $request, SupportDocument $document): void
    {
        if (!$request->hasFile('files')) {
            return;
        }

        $disk = 'public';

        foreach ((array) $request->file('files') as $file) {
            if (!$file) {
                continue;
            }

            $original = $file->getClientOriginalName();
            $safeOriginal = preg_replace('/[^A-Za-z0-9._-]/', '_', $original) ?: 'file';
            $filename = now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeOriginal;

            $path = $file->storeAs("support-documents/{$document->id}", $filename, $disk);

            $document->attachments()->create([
                'category'      => 'support_document',
                'path'          => $path,
                'original_name' => $original,
                'mime_type'     => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => $request->user()?->id,
            ]);
        }
    }

    protected function deleteAttachmentFile(Attachment $attachment): void
    {
        $disk = 'public';

        if ($attachment->path && Storage::disk($disk)->exists($attachment->path)) {
            Storage::disk($disk)->delete($attachment->path);
        }
    }
}
