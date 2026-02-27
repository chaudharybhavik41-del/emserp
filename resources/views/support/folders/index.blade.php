@extends('layouts.erp')

@section('title', 'Support - Folders')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Support Folders</h4>
        <div class="text-muted">Manage folders for the Standard Document Library</div>
    </div>
    <div class="d-flex gap-2">
        @can('support.document.view')
            <a href="{{ route('support.documents.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-folder2-open me-1"></i> Documents
            </a>
        @endcan
        @can('support.document.update')
            <a href="{{ route('support.folders.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New Folder
            </a>
        @endcan
    </div>
</div>

@include('partials.alerts')

<div class="card">
    <div class="card-body">
        @if(empty($flat))
            <div class="text-muted">No folders yet.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Folder</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 180px;" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($flat as $f)
                            @php
                                $folder = $folders->firstWhere('id', $f['id']);
                            @endphp
                            <tr>
                                <td>
                                    <span class="text-muted">{!! str_repeat('&mdash; ', (int) ($f['depth'] ?? 0)) !!}</span>
                                    <strong>{{ $f['name'] }}</strong>
                                    <div class="text-muted small">ID: {{ $f['id'] }}</div>
                                </td>
                                <td>
                                    @if($folder && $folder->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @can('support.document.update')
                                        <a href="{{ route('support.folders.edit', $f['id']) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('support.folders.destroy', $f['id']) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Delete this folder? This is only allowed if it has no sub-folders and no documents.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
