<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PartyAttachmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Reuse party permissions
        $this->middleware('permission:core.party.update')->only(['store']);
        $this->middleware('permission:core.party.delete')->only(['destroy']);
    }

    public function store(Request $request, Party $party)
    {
        $request->validate([
            'document' => ['required', 'file', 'max:5120'], // 5 MB
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        $file = $request->file('document');

        $path = $file->store('party_documents', 'public');

        $party->attachments()->create([
            'category'      => $request->input('category', 'party-doc'),
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'uploaded_by'   => $request->user()?->id,
        ]);

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Document uploaded successfully.');
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        /** @var Party $party */
        $party = $attachment->attachable;

        if (!$party instanceof Party) {
            abort(404);
        }

        if (!$request->user()->can('core.party.delete')) {
            abort(403);
        }

        if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }

        $attachment->delete();

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Document deleted successfully.');
    }
}
