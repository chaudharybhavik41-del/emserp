@extends('layouts.erp')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="mb-1">New Cutting Plan</h1>
                <div class="text-muted">
                    Project: {{ $project->code ?? $project->name }} |
                    BOM: {{ $bom->bom_number ?? ('BOM #' . $bom->id) }}
                </div>
            </div>
            <div>
                <a href="{{ route('projects.boms.cutting-plans.index', [$project, $bom]) }}"
                   class="btn btn-outline-secondary">
                    Back to Cutting Plans
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('projects.boms.cutting-plans.store', [$project, $bom]) }}"
                      class="row g-3">
                    @csrf

                    <div class="col-md-3">
                        <label class="form-label">Grade</label>
                        <input type="text"
                               name="grade"
                               value="{{ old('grade', $grade) }}"
                               class="form-control @error('grade') is-invalid @enderror"
                               placeholder="E250, E350, ...">
                        @error('grade')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Thickness (mm)</label>
                        <input type="number"
                               name="thickness_mm"
                               value="{{ old('thickness_mm', $thickness_mm) }}"
                               class="form-control @error('thickness_mm') is-invalid @enderror">
                        @error('thickness_mm')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Plan Name</label>
                        <input type="text"
                               name="name"
                               value="{{ old('name') }}"
                               class="form-control @error('name') is-invalid @enderror"
                               placeholder="e.g. E250 10mm - Rev 01">
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes"
                                  rows="3"
                                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                        @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">
                            Create Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
