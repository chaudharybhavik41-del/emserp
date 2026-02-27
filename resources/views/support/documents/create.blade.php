@extends('layouts.erp')

@section('title', 'Support - New Document')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">New Document</h4>
        <div class="text-muted">Add a new standard drawing / code / template to the library</div>
    </div>
    <a href="{{ route('support.documents.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

@include('partials.alerts')

<div class="card">
    <div class="card-body">
        <form action="{{ route('support.documents.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Folder (optional)</label>
                    <select name="support_folder_id" class="form-select">
                        <option value="">-- None --</option>
                        @foreach($folders as $f)
                            <option value="{{ $f->id }}" @selected(old('support_folder_id') == $f->id)>{{ $f->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Document Code (optional)</label>
                    <input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="e.g. STD-DWG-001">
                </div>

                <div class="col-12">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Description (optional)</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Tags (optional)</label>
                    <input type="text" name="tags" class="form-control" value="{{ old('tags') }}" placeholder="Comma separated: drawing, code, template">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1" @selected(old('is_active', '1') == '1')>Active</option>
                        <option value="0" @selected(old('is_active') === '0')>Inactive</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Files (optional)</label>
                    <input type="file" name="files[]" class="form-control" multiple>
                    <div class="form-text">You can upload multiple files (PDF/DWG/DOC/ZIP etc). Max 20MB per file.</div>
                </div>
            </div>

            <div class="mt-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save Document
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
