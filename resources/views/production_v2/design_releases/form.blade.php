@extends('layouts.erp')

@section('title', 'Create Production V2 Design Release')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Create Design Release</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.design-releases.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('projects.production-v2.design-releases.store', ['project' => $project->id]) }}">
        @csrf

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Release Number</label>
                        <input name="release_number" class="form-control @error('release_number') is-invalid @enderror" value="{{ old('release_number', $defaultReleaseNumber) }}" required>
                        @error('release_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Release Date</label>
                        <input type="date" name="release_date" class="form-control @error('release_date') is-invalid @enderror" value="{{ old('release_date', now()->toDateString()) }}" required>
                        @error('release_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control">{{ old('remarks') }}</textarea>
                    </div>
                    @if($errors->has('assembly_ids') || $errors->has('cutting_plan_ids') || $errors->has('material_requirement_ids'))
                    <div class="col-12">
                        <div class="alert alert-danger mb-0">
                            <div class="fw-semibold mb-1">Release blocked</div>
                            @foreach($errors->get('assembly_ids') as $message)
                                <div class="small">{{ $message }}</div>
                            @endforeach
                            @foreach($errors->get('cutting_plan_ids') as $message)
                                <div class="small">{{ $message }}</div>
                            @endforeach
                            @foreach($errors->get('material_requirement_ids') as $message)
                                <div class="small">{{ $message }}</div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header">Approved Part Definitions</div>
                    <div class="card-body">
                        @forelse($parts as $part)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="{{ $part->id }}" id="part_{{ $part->id }}" name="part_ids[]" @checked(in_array($part->id, old('part_ids', [])))>
                                <label class="form-check-label" for="part_{{ $part->id }}">{{ $part->part_code }} - {{ $part->part_name }}</label>
                            </div>
                        @empty
                            <div class="text-muted small">No approved unreleased parts available.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header">Approved Assemblies</div>
                    <div class="card-body">
                        @forelse($assemblies as $assembly)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="{{ $assembly->id }}" id="assembly_{{ $assembly->id }}" name="assembly_ids[]" @checked(in_array($assembly->id, old('assembly_ids', [])))>
                                <label class="form-check-label" for="assembly_{{ $assembly->id }}">{{ $assembly->assembly_code }} - {{ $assembly->assembly_name }} ({{ $assembly->requirements_count }} parts)</label>
                                @if(!empty($assemblyWarnings[$assembly->id]))
                                    <div class="small text-warning">Current impact: outdated parts {{ $assemblyWarnings[$assembly->id] }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted small">No approved unreleased assemblies available.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header">Approved Cutting Plans</div>
                    <div class="card-body">
                        @forelse($cuttingPlans as $plan)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="{{ $plan->id }}" id="plan_{{ $plan->id }}" name="cutting_plan_ids[]" @checked(in_array($plan->id, old('cutting_plan_ids', [])))>
                                <label class="form-check-label" for="plan_{{ $plan->id }}">{{ $plan->plan_number }} / {{ $plan->grade ?: '-' }} / {{ $plan->thickness_mm ?: '-' }} mm</label>
                                @if(!empty($cuttingPlanWarnings[$plan->id]))
                                    <div class="small text-warning">Current impact: outdated parts {{ $cuttingPlanWarnings[$plan->id] }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted small">No approved unreleased cutting plans available.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header">Approved Material Requirements</div>
                    <div class="card-body">
                        @forelse($materialRequirements as $row)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="{{ $row->id }}" id="mr_{{ $row->id }}" name="material_requirement_ids[]" @checked(in_array($row->id, old('material_requirement_ids', [])))>
                                <label class="form-check-label" for="mr_{{ $row->id }}">{{ $row->requirement_number }} ({{ $row->items_count }} rows)</label>
                                @if(!empty($materialRequirementWarnings[$row->id]))
                                    <div class="small text-warning">Current impact: stale part snapshot {{ $materialRequirementWarnings[$row->id] }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted small">No approved unreleased material requirements available.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Release Selected Design Data</button>
            <a href="{{ route('projects.production-v2.design-releases.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection
