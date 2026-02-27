@extends('layouts.erp')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Create BOM Template</h4>
    <a href="{{ route('bom-templates.index') }}" class="btn btn-outline-secondary btn-sm">
        Back to Templates
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('bom-templates.store') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">Name *</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Structure Type</label>
                <input type="text" name="structure_type" class="form-control" value="{{ old('structure_type') }}" placeholder="GIRDER, POLE, PLATFORM, ...">
                @error('structure_type') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Status *</label>
                <select name="status" class="form-select" required>
                    @foreach(['draft', 'approved', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $template->status) === $status)>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
                @error('status') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Description / Notes</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                @error('description') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-primary">
                Save Template
            </button>
        </form>
    </div>
</div>
@endsection
