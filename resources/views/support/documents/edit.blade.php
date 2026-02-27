@extends('layouts.erp')

@section('title', 'Support - Edit Document')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Edit Document</h4>
        <div class="text-muted">Update document details and files</div>
    </div>
    <a href="{{ route('support.documents.show', $document) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

@include('partials.alerts')

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('support.documents.update', $document) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Folder (optional)</label>
                            <select name="support_folder_id" class="form-select">
                                <option value="">-- None --</option>
                                @foreach($folders as $f)
                                    <option value="{{ $f->id }}" @selected(old('support_folder_id', $document->support_folder_id) == $f->id)>{{ $f->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Document Code (optional)</label>
                            <input type="text" name="code" class="form-control" value="{{ old('code', $document->code) }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $document->title) }}" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description (optional)</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description', $document->description) }}</textarea>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Tags (optional)</label>
                            <input type="text" name="tags" class="form-control" value="{{ old('tags', is_array($document->tags) ? implode(', ', $document->tags) : '') }}" placeholder="Comma separated">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-select">
                                <option value="1" @selected(old('is_active', (int)$document->is_active) == 1)>Active</option>
                                <option value="0" @selected(old('is_active', (int)$document->is_active) == 0)>Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Add More Files (optional)</label>
                            <input type="file" name="files[]" class="form-control" multiple>
                            <div class="form-text">Max 20MB per file.</div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Update Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <strong>Files</strong>
            </div>
            <div class="card-body">
                @if($document->attachments->isEmpty())
                    <div class="text-muted">No files uploaded.</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($document->attachments as $a)
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold">{{ $a->original_name ?? basename($a->path) }}</div>
                                    <div class="text-muted small">{{ number_format((int)($a->size ?? 0)/1024, 1) }} KB</div>
                                </div>
                                <div class="d-flex gap-1">
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('support.documents.attachments.download', [$document, $a]) }}" title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <form action="{{ route('support.documents.attachments.destroy', [$document, $a]) }}" method="POST" onsubmit="return confirm('Delete this file?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
