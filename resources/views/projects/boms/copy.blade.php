@extends('layouts.erp')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Copy BOM {{ $bom->bom_number }}</h4>
        <small class="text-muted">
            From Project: {{ $project->code }} - {{ $project->name }}
        </small>
    </div>
    <div>
        <a href="{{ route('projects.boms.show', [$project, $bom]) }}" class="btn btn-outline-secondary">
            Back to BOM
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Select Target Project
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('projects.boms.copy-store', [$project, $bom]) }}">
            @csrf

            <div class="mb-3">
                <label for="target_project_id" class="form-label">Target Project</label>
                <select name="target_project_id" id="target_project_id" class="form-select" required>
                    <option value="">-- Select Project --</option>
                    @foreach($projects as $proj)
                        <option value="{{ $proj->id }}">
                            {{ $proj->code }} - {{ $proj->name }}
                        </option>
                    @endforeach
                </select>
                @error('target_project_id')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">
                Copy BOM
            </button>
        </form>
    </div>
</div>
@endsection
