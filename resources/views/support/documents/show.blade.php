@extends('layouts.erp')

@section('title', 'Support - Document')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">{{ $document->title }}</h4>
        <div class="text-muted">
            Document Library
            @if($document->code)
                <span class="mx-2">&middot;</span>
                <span class="fw-semibold">{{ $document->code }}</span>
            @endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('support.documents.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        @can('support.document.update')
            <a href="{{ route('support.documents.edit', $document) }}" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
        @endcan
    </div>
</div>

@include('partials.alerts')

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted">Folder</div>
                        <div class="fw-semibold">{{ $document->folder?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted">Status</div>
                        @if($document->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </div>

                    <div class="col-12">
                        <div class="text-muted">Description</div>
                        <div>{{ $document->description ?: '-' }}</div>
                    </div>

                    <div class="col-12">
                        <div class="text-muted">Tags</div>
                        @if(is_array($document->tags) && count($document->tags))
                            @foreach($document->tags as $t)
                                <span class="badge bg-light text-dark">{{ $t }}</span>
                            @endforeach
                        @else
                            <div>-</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @can('support.document.delete')
            <div class="mt-3">
                <form action="{{ route('support.documents.destroy', $document) }}" method="POST" onsubmit="return confirm('Delete this document? This will also delete its files.');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit">
                        <i class="bi bi-trash me-1"></i> Delete Document
                    </button>
                </form>
            </div>
        @endcan
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Files</strong>
                <span class="badge bg-light text-dark">{{ $document->attachments->count() }}</span>
            </div>
            <div class="card-body">
                @can('support.document.update')
                    <form action="{{ route('support.documents.attachments.store', $document) }}" method="POST" enctype="multipart/form-data" class="mb-3">
                        @csrf
                        <div class="input-group">
                            <input type="file" name="file" class="form-control" required>
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="bi bi-upload me-1"></i> Upload
                            </button>
                        </div>
                        <div class="form-text">Max 20MB per file.</div>
                    </form>
                @endcan

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
                                    @can('support.document.update')
                                        <form action="{{ route('support.documents.attachments.destroy', [$document, $a]) }}" method="POST" onsubmit="return confirm('Delete this file?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endcan
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
