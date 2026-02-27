@extends('layouts.erp')

@section('title', 'Storage')

@section('content')
@php
    $totalFolders = $folders->count();
    $totalSubfolders = (int) $folders->sum('children_count');
    $totalFiles = (int) $folders->sum('files_count');
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="mb-0">Storage</h4>
        <div class="text-muted">Your accessible workspace folders</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
</div>

@include('partials.alerts')

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Root Folders</div>
                <div class="h4 mb-0">{{ $totalFolders }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Immediate Subfolders</div>
                <div class="h4 mb-0">{{ $totalSubfolders }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Files at Root Level</div>
                <div class="h4 mb-0">{{ $totalFiles }}</div>
            </div>
        </div>
    </div>
</div>

@can('createRoot', \App\Models\Storage\StorageFolder::class)
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <strong>Create Root Folder</strong>
        <small class="text-muted">Admins only</small>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('storage.folders.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-5">
                <label class="form-label">Folder Name</label>
                <input
                    type="text"
                    name="name"
                    class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name') }}"
                    placeholder="e.g. Company Documents"
                    required
                >
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-5">
                <label class="form-label">Description (optional)</label>
                <input
                    type="text"
                    name="description"
                    class="form-control @error('description') is-invalid @enderror"
                    value="{{ old('description') }}"
                    placeholder="What this folder is used for"
                >
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-folder-plus me-1"></i> Create
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong>Folders</strong>
        <div class="input-group input-group-sm" style="max-width: 320px;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="folderSearch" class="form-control" placeholder="Search folders...">
        </div>
    </div>
    <div class="card-body">
        @if($folders->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-folder2-open fs-1 text-muted"></i>
                <div class="mt-2 text-muted">No folders assigned to you yet.</div>
            </div>
        @else
            <div class="row g-3" id="folderGrid">
                @foreach($folders as $f)
                    <div class="col-md-6 col-xl-4 folder-card" data-folder-name="{{ strtolower($f->name) }}">
                        <a href="{{ route('storage.folders.show', $f) }}" class="text-decoration-none text-reset">
                            <div class="card h-100 border">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex align-items-start justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-folder-fill text-warning fs-4"></i>
                                            <div class="fw-semibold">{{ $f->name }}</div>
                                        </div>
                                        <i class="bi bi-chevron-right text-muted"></i>
                                    </div>
                                    @if($f->description)
                                        <div class="text-muted small mb-2">{{ $f->description }}</div>
                                    @else
                                        <div class="text-muted small mb-2">No description</div>
                                    @endif
                                    <div class="mt-auto d-flex justify-content-between small text-muted">
                                        <span>{{ $f->children_count }} subfolders</span>
                                        <span>{{ $f->files_count }} files</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
            <div id="folderNoMatch" class="text-center text-muted py-4 d-none">
                No folders match your search.
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('folderSearch');
    const cards = Array.from(document.querySelectorAll('.folder-card'));
    const noMatch = document.getElementById('folderNoMatch');

    if (!searchInput || cards.length === 0) return;

    const filter = () => {
        const q = (searchInput.value || '').trim().toLowerCase();
        let visible = 0;

        cards.forEach((card) => {
            const name = card.getAttribute('data-folder-name') || '';
            const show = !q || name.includes(q);
            card.classList.toggle('d-none', !show);
            if (show) visible++;
        });

        if (noMatch) {
            noMatch.classList.toggle('d-none', visible > 0);
        }
    };

    searchInput.addEventListener('input', filter);
});
</script>
@endpush
@endsection
