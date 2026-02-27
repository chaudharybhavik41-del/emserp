<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\CrmLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CrmLeadAttachmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        // Reuse CRM lead permissions
        $this->middleware('permission:crm.lead.update')->only(['store', 'destroy']);
        $this->middleware('permission:crm.lead.view')->only(['download']);
    }

    public function store(Request $request, CrmLead $lead): RedirectResponse
    {
        $data = $request->validate([
            'files'   => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:20480'], // 20 MB per file
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        $disk = 'public';
        $category = $data['category'] ?? 'crm_lead';

        foreach ($request->file('files', []) as $file) {
            if (!$file) {
                continue;
            }

            $original = $file->getClientOriginalName();

            // Safe filename for storage path (keep original_name separately)
            $safeOriginal = preg_replace('/[^A-Za-z0-9._-]/', '_', $original) ?: 'file';
            $filename = now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeOriginal;

            $path = $file->storeAs("crm-leads/{$lead->id}", $filename, $disk);

            $lead->attachments()->create([
                'category'      => $category,
                'path'          => $path,
                'original_name' => $original,
                'mime_type'     => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => $request->user()?->id,
            ]);
        }

        return back()->with('success', 'Attachment(s) uploaded successfully.');
    }

    public function download(CrmLead $lead, Attachment $attachment): BinaryFileResponse
    {
        $this->ensureAttachmentBelongsToLead($lead, $attachment);

        return Storage::disk('public')->download(
            $attachment->path,
            $attachment->original_name ?? basename((string) $attachment->path)
        );
    }

    public function destroy(CrmLead $lead, Attachment $attachment): RedirectResponse
    {
        $this->ensureAttachmentBelongsToLead($lead, $attachment);

        if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }

        $attachment->delete();

        return back()->with('success', 'Attachment deleted successfully.');
    }

    protected function ensureAttachmentBelongsToLead(CrmLead $lead, Attachment $attachment): void
    {
        if ($attachment->attachable_type !== CrmLead::class || (int) $attachment->attachable_id !== (int) $lead->id) {
            abort(404);
        }
    }
}
