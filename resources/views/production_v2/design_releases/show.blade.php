@extends('layouts.erp')

@section('title', 'Production V2 Design Release')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $designRelease->release_number }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.design-releases.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Release Date</div><div>{{ $designRelease->release_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Released By</div><div>{{ $designRelease->releasedBy?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Parts</div><div>{{ number_format($designRelease->parts->count()) }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Assemblies</div><div>{{ number_format($designRelease->assemblies->count()) }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Cutting Plans</div><div>{{ number_format($designRelease->cuttingPlans->count()) }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $designRelease->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Released Part Definitions</div>
                <div class="card-body">
                    @forelse($designRelease->parts as $part)
                        <div>{{ $part->part_code }} - {{ $part->part_name }}</div>
                    @empty
                        <div class="text-muted small">No parts in this release.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Released Assemblies</div>
                <div class="card-body">
                    @forelse($designRelease->assemblies as $assembly)
                        <div>{{ $assembly->assembly_code }} - {{ $assembly->assembly_name }}</div>
                    @empty
                        <div class="text-muted small">No assemblies in this release.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Released Cutting Plans</div>
                <div class="card-body">
                    @forelse($designRelease->cuttingPlans as $plan)
                        <div>{{ $plan->plan_number }} / {{ $plan->grade ?: '-' }} / {{ $plan->thickness_mm ?: '-' }} mm</div>
                    @empty
                        <div class="text-muted small">No cutting plans in this release.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Released Material Requirements</div>
                <div class="card-body">
                    @forelse($designRelease->materialRequirements as $row)
                        <div>{{ $row->requirement_number }}</div>
                    @empty
                        <div class="text-muted small">No material requirements in this release.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
