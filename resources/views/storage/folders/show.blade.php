@extends('layouts.erp')

@section('title', 'Storage - Folder')

@section('content')
@php
    $totalSize = (int) $files->sum('size');
    $formatBytes = function (int $bytes): string {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        return number_format($bytes / (1024 ** $power), $power > 0 ? 1 : 0) . ' ' . $units[$power];
    };
    $folderDescription = trim((string) ($folder->description ?? ''));
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="{{ route('storage.index') }}">Storage</a></li>
                @foreach($breadcrumbs as $crumb)
                    <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">
                        @if($loop->last)
                            {{ $crumb->name }}
                        @else
                            <a href="{{ route('storage.folders.show', $crumb) }}">{{ $crumb->name }}</a>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
        <h4 class="mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-folder2-open text-warning"></i>
            <span>{{ $folder->name }}</span>
        </h4>
        <div class="text-muted small">
            {{ $folderDescription !== '' ? $folderDescription : 'No description' }}
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ $parentFolder ? route('storage.folders.show', $parentFolder) : route('storage.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>

        @can('update', $folder)
            <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#folderSettingsCard">
                <i class="bi bi-sliders me-1"></i> Folder Settings
            </button>
        @endcan

        @can('manageAccess', $folder)
            <a href="{{ route('storage.folders.access.index', $folder) }}" class="btn btn-outline-secondary">
                <i class="bi bi-shield-lock me-1"></i> Manage Access
            </a>
        @endcan
    </div>
</div>

@include('partials.alerts')

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-muted">Subfolders</div>
                <div class="h4 mb-0">{{ $subfolders->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-muted">Files</div>
                <div class="h4 mb-0">{{ $files->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-muted">Total Size</div>
                <div class="h4 mb-0">{{ $formatBytes($totalSize) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-muted">Your Access</div>
                <div class="small mt-1">
                    @if($access)
                        <span class="badge {{ $access->can_view ? 'bg-success' : 'bg-secondary' }}">View</span>
                        <span class="badge {{ $access->can_upload ? 'bg-success' : 'bg-secondary' }}">Upload</span>
                        <span class="badge {{ $access->can_download ? 'bg-success' : 'bg-secondary' }}">Download</span>
                    @else
                        <span class="text-muted">Inherited / Policy-based</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@can('update', $folder)
<div class="collapse mb-3" id="folderSettingsCard">
    <div class="card">
        <div class="card-header"><strong>Edit Folder</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('storage.folders.update', $folder) }}" class="row g-2 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-md-4">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" value="{{ old('name', $folder->name) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" value="{{ old('description', $folder->description) }}" class="form-control">
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Preview</strong>
        <div class="small text-muted" id="previewMeta">Select a file from the list to preview</div>
    </div>
    <div class="card-body">
        <div class="border rounded p-3 bg-light-subtle" id="previewPane" style="min-height: 280px;">
            <div class="text-center text-muted py-5">
                <i class="bi bi-file-earmark-richtext fs-1"></i>
                <div class="mt-2">Preview appears here</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Subfolders</strong>
                <div class="input-group input-group-sm" style="max-width: 220px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="subfolderSearch" type="text" class="form-control" placeholder="Search...">
                </div>
            </div>
            <div class="card-body">
                @can('createSubfolder', $folder)
                    <form method="POST" action="{{ route('storage.folders.store') }}" class="row g-2 mb-3">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $folder->id }}">
                        <div class="col-12">
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="New subfolder name" required>
                        </div>
                        <div class="col-12">
                            <input type="text" name="description" class="form-control form-control-sm" placeholder="Description (optional)">
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-primary btn-sm" type="submit">
                                <i class="bi bi-folder-plus me-1"></i> Create Subfolder
                            </button>
                        </div>
                    </form>
                @endcan

                @if($subfolders->isEmpty())
                    <div class="text-muted">No subfolders.</div>
                @else
                    <div class="list-group" id="subfolderList">
                        @foreach($subfolders as $sf)
                            <div class="list-group-item subfolder-item" data-subfolder-name="{{ strtolower($sf->name) }}">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <a class="text-decoration-none" href="{{ route('storage.folders.show', $sf) }}">
                                        <div class="fw-semibold"><i class="bi bi-folder me-1 text-warning"></i>{{ $sf->name }}</div>
                                        <div class="text-muted small">
                                            {{ $sf->children_count }} subfolders, {{ $sf->files_count }} files
                                        </div>
                                    </a>
                                    @can('delete', $sf)
                                        <form method="POST"
                                              action="{{ route('storage.folders.destroy', $sf) }}"
                                              onsubmit="return confirm('Delete this folder and everything inside it?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div id="subfolderNoMatch" class="text-center text-muted py-3 d-none">No subfolders match your search.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <strong>Files</strong>
                <div class="d-flex gap-2">
                    <div class="input-group input-group-sm" style="width: 240px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input id="fileSearch" type="text" class="form-control" placeholder="Search files...">
                    </div>
                    <select id="fileSort" class="form-select form-select-sm" style="width: 180px;">
                        <option value="name_asc">Name: A-Z</option>
                        <option value="name_desc">Name: Z-A</option>
                        <option value="size_desc">Size: Largest</option>
                        <option value="size_asc">Size: Smallest</option>
                        <option value="date_desc">Newest</option>
                        <option value="date_asc">Oldest</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                @can('upload', $folder)
                    <div class="border rounded p-3 mb-3 upload-dropzone" id="uploadDropZone">
                        <form method="POST" action="{{ route('storage.files.store', $folder) }}" enctype="multipart/form-data" class="row g-2 align-items-center" id="uploadForm">
                            @csrf
                            <div class="col-lg-8">
                                <label class="form-label mb-1">Upload files</label>
                                <input type="file" name="files[]" id="uploadFiles" class="form-control" multiple required>
                                <small class="text-muted">Drag files here or browse. Max 50 MB per file.</small>
                            </div>
                            <div class="col-lg-4 d-grid">
                                <button class="btn btn-primary" type="submit" id="uploadSubmitBtn">
                                    <i class="bi bi-upload me-1"></i> Upload
                                </button>
                            </div>
                            <div class="col-12 d-none" id="uploadProgressWrap">
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="uploadProgressBar" style="width: 0%">0%</div>
                                </div>
                                <small class="text-muted" id="uploadProgressText">Preparing upload...</small>
                            </div>
                        </form>
                    </div>
                @endcan

                @if($files->isEmpty())
                    <div class="text-center py-4 text-muted">No files uploaded.</div>
                @else
                    <form method="POST" id="bulkFilesForm">
                        @csrf
                        <input type="hidden" name="folder_id" value="{{ $folder->id }}">

                        <div class="border rounded p-2 mb-2 d-flex flex-wrap align-items-center gap-2">
                            <div class="form-check me-2">
                                <input type="checkbox" class="form-check-input" id="selectAllFiles">
                                <label class="form-check-label" for="selectAllFiles">Select all</label>
                            </div>
                            <span class="badge bg-light text-dark border" id="selectedCountBadge">0 selected</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkDownloadBtn" disabled>
                                <i class="bi bi-download me-1"></i> Download ZIP
                            </button>
                            <div class="input-group input-group-sm" style="width: 260px;">
                                <select id="bulkMoveTarget" class="form-select">
                                    <option value="">Move to folder...</option>
                                    @foreach($moveTargets as $target)
                                        <option value="{{ $target->id }}">{{ $target->name }} (#{{ $target->id }})</option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-primary" id="bulkMoveBtn" disabled>Move</button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" disabled>
                                <i class="bi bi-trash me-1"></i> Delete
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 36px;"></th>
                                        <th>File</th>
                                        <th>Size</th>
                                        <th>Type</th>
                                        <th>Uploaded By</th>
                                        <th>Uploaded</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="fileTableBody">
                                    @foreach($files as $f)
                                        @php
                                            $ext = strtolower(pathinfo((string) $f->original_name, PATHINFO_EXTENSION));
                                            $icon = match ($ext) {
                                                'pdf' => 'bi-file-earmark-pdf text-danger',
                                                'xls', 'xlsx', 'csv' => 'bi-file-earmark-spreadsheet text-success',
                                                'doc', 'docx' => 'bi-file-earmark-word text-primary',
                                                'zip', 'rar', '7z' => 'bi-file-earmark-zip text-warning',
                                                'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' => 'bi-file-earmark-image text-info',
                                                'mp4', 'avi', 'mov', 'mkv' => 'bi-file-earmark-play text-secondary',
                                                'mp3', 'wav', 'ogg' => 'bi-file-earmark-music text-secondary',
                                                default => 'bi-file-earmark text-secondary',
                                            };
                                        @endphp
                                        <tr
                                            class="file-row"
                                            data-name="{{ strtolower($f->original_name) }}"
                                            data-size="{{ (int) ($f->size ?? 0) }}"
                                            data-date="{{ optional($f->created_at)->timestamp ?? 0 }}"
                                            data-edit-row="editFile{{ $f->id }}"
                                            data-preview-url="{{ route('storage.files.preview', $f) }}"
                                            data-download-url="{{ route('storage.files.download', $f) }}"
                                            data-file-name="{{ $f->original_name }}"
                                            data-file-mime="{{ $f->mime_type }}"
                                            data-file-ext="{{ $ext }}"
                                        >
                                            <td>
                                                <input class="form-check-input file-select" type="checkbox" name="file_ids[]" value="{{ $f->id }}">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-link p-0 text-start text-decoration-none js-preview-file">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="bi {{ $icon }} fs-5"></i>
                                                        <div>
                                                            <div class="fw-semibold">{{ $f->original_name }}</div>
                                                            <div class="text-muted small">{{ $f->mime_type ?: 'Unknown type' }}</div>
                                                        </div>
                                                    </div>
                                                </button>
                                            </td>
                                            <td>{{ $formatBytes((int) ($f->size ?? 0)) }}</td>
                                            <td>{{ strtoupper($ext ?: '-') }}</td>
                                            <td>{{ $f->uploader?->name ?? 'System' }}</td>
                                            <td>{{ $f->created_at?->format('d M Y H:i') }}</td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary js-preview-file" type="button" title="Preview">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    @can('download', $f)
                                                        <a class="btn btn-outline-secondary" href="{{ route('storage.files.download', $f) }}" title="Download">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                    @endcan

                                                    @can('update', $f)
                                                        <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editFile{{ $f->id }}" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    @endcan

                                                    @can('delete', $f)
                                                        <form method="POST" action="{{ route('storage.files.destroy', $f) }}"
                                                              onsubmit="return confirm('Delete this file?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-outline-danger" type="submit" title="Delete">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>

                                        @can('update', $f)
                                            <tr class="collapse" id="editFile{{ $f->id }}">
                                                <td colspan="7">
                                                    <form method="POST" action="{{ route('storage.files.update', $f) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="col-md-5">
                                                            <label class="form-label form-label-sm">File name</label>
                                                            <input type="text" name="original_name" class="form-control form-control-sm" value="{{ $f->original_name }}" required>
                                                        </div>
                                                        <div class="col-md-5">
                                                            <label class="form-label form-label-sm">Replace file (optional)</label>
                                                            <input type="file" name="replace_file" class="form-control form-control-sm">
                                                        </div>
                                                        <div class="col-md-2 d-grid">
                                                            <button class="btn btn-primary btn-sm" type="submit">Update</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endcan
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </form>
                    <div id="fileNoMatch" class="text-center text-muted py-3 d-none">No files match your filter.</div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.upload-dropzone {
    border: 2px dashed #d7dbe3 !important;
    background: linear-gradient(180deg, #fafbfe 0%, #f4f7fc 100%);
}
.upload-dropzone.drag-over {
    border-color: #0d6efd !important;
    background: #eaf2ff;
}
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/mammoth/mammoth.browser.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const subfolderSearch = document.getElementById('subfolderSearch');
    const subfolderItems = Array.from(document.querySelectorAll('.subfolder-item'));
    const subfolderNoMatch = document.getElementById('subfolderNoMatch');

    const fileSearch = document.getElementById('fileSearch');
    const fileSort = document.getElementById('fileSort');
    const fileBody = document.getElementById('fileTableBody');
    const fileNoMatch = document.getElementById('fileNoMatch');
    const fileRows = () => Array.from(document.querySelectorAll('tr.file-row'));

    const previewPane = document.getElementById('previewPane');
    const previewMeta = document.getElementById('previewMeta');

    const bulkForm = document.getElementById('bulkFilesForm');
    const selectAllFiles = document.getElementById('selectAllFiles');
    const selectedCountBadge = document.getElementById('selectedCountBadge');
    const bulkDownloadBtn = document.getElementById('bulkDownloadBtn');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkMoveBtn = document.getElementById('bulkMoveBtn');
    const bulkMoveTarget = document.getElementById('bulkMoveTarget');

    const uploadDropZone = document.getElementById('uploadDropZone');
    const uploadForm = document.getElementById('uploadForm');
    const uploadFiles = document.getElementById('uploadFiles');
    const uploadSubmitBtn = document.getElementById('uploadSubmitBtn');
    const uploadProgressWrap = document.getElementById('uploadProgressWrap');
    const uploadProgressBar = document.getElementById('uploadProgressBar');
    const uploadProgressText = document.getElementById('uploadProgressText');

    const filterSubfolders = () => {
        if (!subfolderSearch || subfolderItems.length === 0) return;
        const q = (subfolderSearch.value || '').trim().toLowerCase();
        let visible = 0;
        subfolderItems.forEach((item) => {
            const name = item.getAttribute('data-subfolder-name') || '';
            const show = !q || name.includes(q);
            item.classList.toggle('d-none', !show);
            if (show) visible++;
        });
        if (subfolderNoMatch) {
            subfolderNoMatch.classList.toggle('d-none', visible > 0);
        }
    };

    const applyFileFilterAndSort = () => {
        if (!fileBody) return;
        const rows = fileRows();
        const query = (fileSearch?.value || '').trim().toLowerCase();
        const sortKey = fileSort?.value || 'name_asc';

        rows.forEach((row) => {
            const name = row.getAttribute('data-name') || '';
            const show = !query || name.includes(query);
            row.classList.toggle('d-none', !show);

            const editRowId = row.getAttribute('data-edit-row');
            const editRow = editRowId ? document.getElementById(editRowId) : null;
            if (editRow) {
                if (!show) editRow.classList.add('d-none');
                else editRow.classList.remove('d-none');
            }
        });

        rows.sort((a, b) => {
            const nameA = a.getAttribute('data-name') || '';
            const nameB = b.getAttribute('data-name') || '';
            const sizeA = Number(a.getAttribute('data-size') || '0');
            const sizeB = Number(b.getAttribute('data-size') || '0');
            const dateA = Number(a.getAttribute('data-date') || '0');
            const dateB = Number(b.getAttribute('data-date') || '0');

            switch (sortKey) {
                case 'name_desc': return nameB.localeCompare(nameA);
                case 'size_desc': return sizeB - sizeA;
                case 'size_asc': return sizeA - sizeB;
                case 'date_desc': return dateB - dateA;
                case 'date_asc': return dateA - dateB;
                default: return nameA.localeCompare(nameB);
            }
        });

        rows.forEach((row) => {
            fileBody.appendChild(row);
            const editRowId = row.getAttribute('data-edit-row');
            const editRow = editRowId ? document.getElementById(editRowId) : null;
            if (editRow) fileBody.appendChild(editRow);
        });

        const visibleRows = rows.filter((row) => !row.classList.contains('d-none')).length;
        if (fileNoMatch) {
            fileNoMatch.classList.toggle('d-none', visibleRows > 0);
        }
    };

    const selectedFileCheckboxes = () => Array.from(document.querySelectorAll('.file-select:checked'));
    const allFileCheckboxes = () => Array.from(document.querySelectorAll('.file-select'));

    const updateBulkState = () => {
        const selected = selectedFileCheckboxes().length;
        if (selectedCountBadge) selectedCountBadge.textContent = selected + ' selected';
        if (bulkDownloadBtn) bulkDownloadBtn.disabled = selected === 0;
        if (bulkDeleteBtn) bulkDeleteBtn.disabled = selected === 0;
        if (bulkMoveBtn) bulkMoveBtn.disabled = selected === 0 || !(bulkMoveTarget && bulkMoveTarget.value);
    };

    const submitBulk = (action, extra = {}) => {
        if (!bulkForm) return;
        if (selectedFileCheckboxes().length === 0) return;
        bulkForm.action = action;

        Array.from(bulkForm.querySelectorAll('.bulk-extra')).forEach((el) => el.remove());
        Object.entries(extra).forEach(([key, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            input.classList.add('bulk-extra');
            bulkForm.appendChild(input);
        });
        bulkForm.submit();
    };

    const escapeHtml = (s) => (s || '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[c]));

    const showPreviewMessage = (title, body) => {
        if (!previewPane) return;
        previewPane.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-file-earmark-text fs-1"></i>
                <div class="mt-2 fw-semibold">${escapeHtml(title)}</div>
                <div class="small mt-1">${escapeHtml(body)}</div>
            </div>
        `;
    };

    const renderPreview = async (row) => {
        if (!row || !previewPane || !previewMeta) return;

        const previewUrl = row.getAttribute('data-preview-url');
        const downloadUrl = row.getAttribute('data-download-url');
        const fileName = row.getAttribute('data-file-name') || 'File';
        const mime = (row.getAttribute('data-file-mime') || '').toLowerCase();
        const ext = (row.getAttribute('data-file-ext') || '').toLowerCase();

        previewMeta.innerHTML = `
            <span class="fw-semibold">${escapeHtml(fileName)}</span>
            <a class="ms-2" href="${downloadUrl}">Download</a>
        `;

        if (!previewUrl) {
            showPreviewMessage(fileName, 'Preview URL not available.');
            return;
        }

        // Images
        if (mime.startsWith('image/') || ['jpg','jpeg','png','gif','webp','svg'].includes(ext)) {
            previewPane.innerHTML = `<div class="text-center"><img src="${previewUrl}" alt="${escapeHtml(fileName)}" class="img-fluid rounded" style="max-height:500px;"></div>`;
            return;
        }

        // PDF
        if (mime.includes('pdf') || ext === 'pdf') {
            previewPane.innerHTML = `<iframe src="${previewUrl}" style="width:100%;height:520px;border:0;" title="${escapeHtml(fileName)}"></iframe>`;
            return;
        }

        // Video / Audio
        if (mime.startsWith('video/')) {
            previewPane.innerHTML = `<video controls style="width:100%;max-height:520px;"><source src="${previewUrl}" type="${mime}">Your browser cannot preview this video.</video>`;
            return;
        }
        if (mime.startsWith('audio/')) {
            previewPane.innerHTML = `<audio controls style="width:100%;"><source src="${previewUrl}" type="${mime}">Your browser cannot preview this audio.</audio>`;
            return;
        }

        // Text-like
        if (mime.startsWith('text/') || ['txt','csv','log','json','xml','md'].includes(ext)) {
            previewPane.innerHTML = `<div class="text-muted small mb-2">Loading text preview...</div>`;
            try {
                const r = await fetch(previewUrl, { credentials: 'same-origin' });
                const text = await r.text();
                previewPane.innerHTML = `<pre class="mb-0" style="max-height:520px;overflow:auto;white-space:pre-wrap;">${escapeHtml(text.slice(0, 150000))}</pre>`;
            } catch (e) {
                showPreviewMessage(fileName, 'Unable to load text preview.');
            }
            return;
        }

        // DOCX via mammoth.js
        if (ext === 'docx') {
            previewPane.innerHTML = `<div class="text-muted small mb-2">Loading DOCX preview...</div>`;
            try {
                if (!(window.mammoth && window.mammoth.convertToHtml)) {
                    showPreviewMessage(fileName, 'DOCX preview library could not be loaded. Use download.');
                    return;
                }
                const r = await fetch(previewUrl, { credentials: 'same-origin' });
                const arrayBuffer = await r.arrayBuffer();
                const result = await window.mammoth.convertToHtml({ arrayBuffer });
                previewPane.innerHTML = `<div style="max-height:520px;overflow:auto;" class="p-2">${result.value || ''}</div>`;
            } catch (e) {
                showPreviewMessage(fileName, 'Unable to preview DOCX. You can still download it.');
            }
            return;
        }

        // Legacy DOC is typically not browser-previewable without server-side conversion.
        if (ext === 'doc') {
            showPreviewMessage(fileName, 'DOC preview is not reliably supported in browser. Please download the file.');
            return;
        }

        showPreviewMessage(fileName, 'Preview is not available for this file type.');
    };

    // Upload: drag-drop + async progress
    if (uploadDropZone && uploadFiles) {
        ['dragenter', 'dragover'].forEach((ev) => {
            uploadDropZone.addEventListener(ev, (e) => {
                e.preventDefault();
                e.stopPropagation();
                uploadDropZone.classList.add('drag-over');
            });
        });
        ['dragleave', 'drop'].forEach((ev) => {
            uploadDropZone.addEventListener(ev, (e) => {
                e.preventDefault();
                e.stopPropagation();
                uploadDropZone.classList.remove('drag-over');
            });
        });
        uploadDropZone.addEventListener('drop', (e) => {
            if (e.dataTransfer?.files?.length) {
                uploadFiles.files = e.dataTransfer.files;
            }
        });
    }

    if (uploadForm) {
        uploadForm.addEventListener('submit', (e) => {
            if (!(window.XMLHttpRequest && uploadFiles && uploadFiles.files.length > 0)) return;
            e.preventDefault();

            const formData = new FormData(uploadForm);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', uploadForm.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            uploadSubmitBtn?.setAttribute('disabled', 'disabled');
            uploadProgressWrap?.classList.remove('d-none');
            if (uploadProgressBar) {
                uploadProgressBar.style.width = '0%';
                uploadProgressBar.textContent = '0%';
            }
            if (uploadProgressText) uploadProgressText.textContent = 'Uploading...';

            xhr.upload.onprogress = (event) => {
                if (!event.lengthComputable || !uploadProgressBar) return;
                const percent = Math.round((event.loaded / event.total) * 100);
                uploadProgressBar.style.width = percent + '%';
                uploadProgressBar.textContent = percent + '%';
            };

            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 400) {
                    if (uploadProgressText) uploadProgressText.textContent = 'Upload complete. Refreshing...';
                    window.location.reload();
                } else {
                    alert('Upload failed. Please try again.');
                    uploadSubmitBtn?.removeAttribute('disabled');
                    uploadProgressWrap?.classList.add('d-none');
                }
            };
            xhr.onerror = () => {
                alert('Upload failed due to network/server error.');
                uploadSubmitBtn?.removeAttribute('disabled');
                uploadProgressWrap?.classList.add('d-none');
            };
            xhr.send(formData);
        });
    }

    // Bind subfolder filter
    subfolderSearch?.addEventListener('input', filterSubfolders);

    // Bind file filter/sort
    fileSearch?.addEventListener('input', applyFileFilterAndSort);
    fileSort?.addEventListener('change', applyFileFilterAndSort);

    // Preview handlers
    document.querySelectorAll('.js-preview-file').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            const row = e.target.closest('tr.file-row') || e.target.closest('button')?.closest('tr.file-row');
            renderPreview(row);
        });
    });

    // Bulk selection
    selectAllFiles?.addEventListener('change', () => {
        allFileCheckboxes().forEach((cb) => { cb.checked = !!selectAllFiles.checked; });
        updateBulkState();
    });
    document.querySelectorAll('.file-select').forEach((cb) => {
        cb.addEventListener('change', updateBulkState);
    });
    bulkMoveTarget?.addEventListener('change', updateBulkState);

    bulkDownloadBtn?.addEventListener('click', () => {
        submitBulk('{{ route('storage.files.bulk-download') }}');
    });
    bulkDeleteBtn?.addEventListener('click', () => {
        if (!confirm('Delete selected files? This cannot be undone.')) return;
        submitBulk('{{ route('storage.files.bulk-destroy') }}');
    });
    bulkMoveBtn?.addEventListener('click', () => {
        if (!bulkMoveTarget?.value) return;
        submitBulk('{{ route('storage.files.bulk-move') }}', { target_folder_id: bulkMoveTarget.value });
    });

    updateBulkState();
    applyFileFilterAndSort();

    // Auto-preview first file for quick entry
    const firstVisible = fileRows().find((row) => !row.classList.contains('d-none'));
    if (firstVisible) {
        renderPreview(firstVisible);
    }
});
</script>
@endpush
@endsection
