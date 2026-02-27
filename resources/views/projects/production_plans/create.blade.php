@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-plus-circle"></i> Create Production Plan</h2>
            <div class="text-muted small">Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}</div>
        </div>
        <a href="{{ route('projects.production-plans.index', $project) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="alert alert-info">
        This plan is a <b>separate Production entity</b>. It will fetch only <b>Approved/Finalized BOM</b> and import items.
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('projects.production-plans.store', $project) }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Approved BOM <span class="text-danger">*</span></label>
                        <select name="bom_id" class="form-select @error('bom_id') is-invalid @enderror">
                            <option value="">— Select —</option>
                            @foreach($boms as $b)
                                <option value="{{ $b->id }}" {{ old('bom_id') == $b->id ? 'selected' : '' }}>
                                    {{ $b->bom_number }} ({{ $b->status->label() }})
                                </option>
                            @endforeach
                        </select>
                        @error('bom_id')<div class="invalid-feedback">{{ $message }}</div>@enderror

                        @if($boms->isEmpty())
                            <div class="form-text text-danger">
                                No approved BOM found. Please finalize BOM first.
                            </div>
                        @endif
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Plan Date</label>
                        <input type="date" name="plan_date" value="{{ old('plan_date', $plan->plan_date) }}"
                               class="form-control @error('plan_date') is-invalid @enderror">
                        @error('plan_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" rows="3" class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks') }}</textarea>
                        @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button class="btn btn-primary" {{ $boms->isEmpty() ? 'disabled' : '' }}>
                        <i class="bi bi-check2-circle"></i> Create & Import BOM
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
