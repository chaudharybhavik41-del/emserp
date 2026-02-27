@extends('layouts.erp')

@section('title', 'Support - Document Library')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Standard Document Library</h4>
        <div class="text-muted">Upload and share standard drawings, codes, templates, and reference files</div>
    </div>
    <div class="d-flex gap-2">
        @can('support.document.update')
            <a href="{{ route('support.folders.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-folder me-1"></i> Folders
            </a>
        @endcan
        @can('support.document.create')
            <a href="{{ route('support.documents.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New Document
            </a>
        @endcan
    </div>
</div>

@include('partials.alerts')

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('support.documents.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Folder</label>
                <select name="folder_id" class="form-select">
                    <option value="">-- All --</option>
                    @foreach($folders as $f)
                        <option value="{{ $f->id }}" @selected((int)$folderId === (int)$f->id)>{{ $f->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="Title, code, or description">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary w-100" type="submit">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <a class="btn btn-outline-secondary" href="{{ route('support.documents.index') }}" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th style="width: 140px;">Code</th>
                        <th style="width: 180px;">Folder</th>
                        <th style="width: 120px;">Files</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 140px;">Created</th>
                        <th style="width: 180px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $doc)
                        <tr>
                            <td>
                                <a href="{{ route('support.documents.show', $doc) }}" class="text-decoration-none">
                                    <strong>{{ $doc->title }}</strong>
                                </a>
                                @if(!empty($doc->description))
                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($doc->description, 120) }}</div>
                                @endif
                            </td>
                            <td>{{ $doc->code }}</td>
                            <td>{{ $doc->folder?->name ?? '-' }}</td>
                            <td>
                                <span class="badge bg-light text-dark">{{ (int)$doc->attachments_count }}</span>
                            </td>
                            <td>
                                @if($doc->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ optional($doc->created_at)->format('d-m-Y') }}</td>
                            <td class="text-end">
                                <a href="{{ route('support.documents.show', $doc) }}" class="btn btn-sm btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('support.document.update')
                                    <a href="{{ route('support.documents.edit', $doc) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                                @can('support.document.delete')
                                    <form action="{{ route('support.documents.destroy', $doc) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this document? This will also delete its files.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted">No documents found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $documents->links() }}
        </div>
    </div>
</div>
@endsection
