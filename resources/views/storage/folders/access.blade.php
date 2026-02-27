@extends('layouts.erp')

@section('title', 'Storage - Manage Access')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="{{ route('storage.index') }}">Storage</a></li>
                <li class="breadcrumb-item"><a href="{{ route('storage.folders.show', $folder) }}">{{ $folder->name }}</a></li>
                <li class="breadcrumb-item active">Access</li>
            </ol>
        </nav>
        <h4 class="mb-0">Manage Access</h4>
        <div class="text-muted small">Folder: {{ $folder->name }} (ID: {{ $folder->id }})</div>
    </div>
    <a href="{{ route('storage.folders.show', $folder) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Folder
    </a>
</div>

@include('partials.alerts')

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Grant / Update User Access</strong>
        <small class="text-muted">Use preset buttons for faster assignment</small>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('storage.folders.access.store', $folder) }}" class="row g-3 align-items-end" id="accessForm">
            @csrf
            <div class="col-lg-4">
                <label class="form-label">User</label>
                <select name="user_id" class="form-select" required>
                    <option value="">-- Select user --</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-8">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary perm-preset" data-preset="viewer">Viewer</button>
                    <button type="button" class="btn btn-sm btn-outline-primary perm-preset" data-preset="contributor">Contributor</button>
                    <button type="button" class="btn btn-sm btn-outline-success perm-preset" data-preset="manager">Manager</button>
                    <button type="button" class="btn btn-sm btn-outline-dark perm-preset" data-preset="clear">Clear</button>
                </div>

                <div class="d-flex flex-wrap gap-3">
                    <label class="form-check">
                        <input class="form-check-input perm-check" type="checkbox" name="can_view" value="1">
                        <span class="form-check-label">View</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input perm-check" type="checkbox" name="can_upload" value="1">
                        <span class="form-check-label">Upload</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input perm-check" type="checkbox" name="can_download" value="1">
                        <span class="form-check-label">Download</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input perm-check" type="checkbox" name="can_edit" value="1">
                        <span class="form-check-label">Edit</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input perm-check" type="checkbox" name="can_delete" value="1">
                        <span class="form-check-label">Delete</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input perm-check" type="checkbox" name="can_manage_access" value="1">
                        <span class="form-check-label">Manage Access</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="apply_to_subfolders" value="1" checked>
                        <span class="form-check-label">Apply to subfolders</span>
                    </label>
                </div>
            </div>

            <div class="col-12">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-save me-1"></i> Save Access
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Current Access List</strong>
        <span class="text-muted small">{{ $accesses->count() }} assigned user(s)</span>
    </div>
    <div class="card-body">
        @if($accesses->isEmpty())
            <div class="text-muted">No users assigned yet.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th class="text-center">View</th>
                            <th class="text-center">Upload</th>
                            <th class="text-center">Download</th>
                            <th class="text-center">Edit</th>
                            <th class="text-center">Delete</th>
                            <th class="text-center">Manage</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accesses as $a)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $a->user?->name ?? ('User#'.$a->user_id) }}</div>
                                    <div class="small text-muted">{{ $a->user?->email }}</div>
                                </td>
                                <td class="text-center">{!! $a->can_view ? '<span class="badge bg-success">Yes</span>' : '<span class="text-muted">—</span>' !!}</td>
                                <td class="text-center">{!! $a->can_upload ? '<span class="badge bg-success">Yes</span>' : '<span class="text-muted">—</span>' !!}</td>
                                <td class="text-center">{!! $a->can_download ? '<span class="badge bg-success">Yes</span>' : '<span class="text-muted">—</span>' !!}</td>
                                <td class="text-center">{!! $a->can_edit ? '<span class="badge bg-success">Yes</span>' : '<span class="text-muted">—</span>' !!}</td>
                                <td class="text-center">{!! $a->can_delete ? '<span class="badge bg-success">Yes</span>' : '<span class="text-muted">—</span>' !!}</td>
                                <td class="text-center">{!! $a->can_manage_access ? '<span class="badge bg-success">Yes</span>' : '<span class="text-muted">—</span>' !!}</td>
                                <td class="text-end">
                                    <form method="POST"
                                          action="{{ route('storage.folders.access.destroy', [$folder, $a->user_id]) }}"
                                          onsubmit="return confirm('Remove access for this user (also removes from subfolders)?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">
                                            <i class="bi bi-trash"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const presets = document.querySelectorAll('.perm-preset');
    const checks = {
        can_view: document.querySelector('input[name="can_view"]'),
        can_upload: document.querySelector('input[name="can_upload"]'),
        can_download: document.querySelector('input[name="can_download"]'),
        can_edit: document.querySelector('input[name="can_edit"]'),
        can_delete: document.querySelector('input[name="can_delete"]'),
        can_manage_access: document.querySelector('input[name="can_manage_access"]'),
    };

    const applyPreset = (preset) => {
        const set = (map) => Object.entries(map).forEach(([key, value]) => {
            if (checks[key]) checks[key].checked = value;
        });

        if (preset === 'viewer') {
            set({ can_view: true, can_upload: false, can_download: true, can_edit: false, can_delete: false, can_manage_access: false });
        } else if (preset === 'contributor') {
            set({ can_view: true, can_upload: true, can_download: true, can_edit: true, can_delete: false, can_manage_access: false });
        } else if (preset === 'manager') {
            set({ can_view: true, can_upload: true, can_download: true, can_edit: true, can_delete: true, can_manage_access: true });
        } else if (preset === 'clear') {
            set({ can_view: false, can_upload: false, can_download: false, can_edit: false, can_delete: false, can_manage_access: false });
        }
    };

    presets.forEach((btn) => {
        btn.addEventListener('click', () => applyPreset(btn.dataset.preset));
    });
});
</script>
@endpush
@endsection
