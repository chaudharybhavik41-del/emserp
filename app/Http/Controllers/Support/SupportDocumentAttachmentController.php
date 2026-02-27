<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Support\SupportDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupportDocumentAttachmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:support.document.update')->only(['store', 'destroy']);
        $this->middleware('permission:support.document.view')->only(['download']);
    }

    public function store(Request $request, SupportDocument $document): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');

        $disk = 'public';

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

        return back()->with('success', 'File uploaded successfully.');
    }

    public function download(SupportDocument $document, Attachment $attachment): BinaryFileResponse
    {
        $this->ensureAttachmentBelongsToDocument($document, $attachment);

        return Storage::disk('public')->download(
            $attachment->path,
            $attachment->original_name ?? basename((string) $attachment->path)
        );
    }

    public function destroy(SupportDocument $document, Attachment $attachment): RedirectResponse
    {
        $this->ensureAttachmentBelongsToDocument($document, $attachment);

        if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }

        $attachment->delete();

        return back()->with('success', 'File deleted successfully.');
    }

    protected function ensureAttachmentBelongsToDocument(SupportDocument $document, Attachment $attachment): void
    {
        if ($attachment->attachable_type !== SupportDocument::class || (int) $attachment->attachable_id !== (int) $document->id) {
            abort(404);
        }
    }
}
