@extends('layouts.erp')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Create BOM from Template</h4>
        <small class="text-muted">
            Template: {{ $template->template_code }} - {{ $template->name }}
        </small>
    </div>
    <a href="{{ route('bom-templates.show', $template) }}" class="btn btn-outline-secondary btn-sm">
        Back to Template
    </a>
</div>

<div class="card">
    <div class="card-header">
        Select Project
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('bom-templates.create-bom-store', $template) }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">Project *</label>
                <select name="project_id" class="form-select" required>
                    <option value="">-- Select Project --</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">
                            {{ $project->code }} - {{ $project->name }}
                        </option>
                    @endforeach
                </select>
                @error('project_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">BOM Remarks</label>
                <textarea name="remarks" class="form-control" rows="3" placeholder="Optional remarks for BOM header">{{ old('remarks') }}</textarea>
                @error('remarks') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-primary">
                Create BOM
            </button>
        </form>
    </div>
</div>
@endsection
