@extends('layouts.erp')

@section('title', 'Production V2 Operation Master')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $operationMaster->name }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'operation_masters'])

    <div class="d-flex justify-content-end gap-2 mb-3">
        <a href="{{ route('projects.production-v2.operation-masters.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        <a href="{{ route('projects.production-v2.operation-masters.edit', ['project' => $project->id, 'operationMaster' => $operationMaster->id]) }}" class="btn btn-sm btn-primary">Edit</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Applies To</div><div class="display-6 mb-0">{{ ucfirst($operationMaster->applies_to) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Entry</div><div class="display-6 mb-0">{{ ucfirst($operationMaster->entry_mode) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Routes</div><div class="display-6 mb-0">{{ number_format($operationMaster->route_template_steps_count) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">QC Gates Logged</div><div class="display-6 mb-0">{{ number_format($operationMaster->qc_gate_events_count) }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Code</div><div>{{ $operationMaster->code }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Status</div><div>{{ $operationMaster->is_active ? 'Active' : 'Inactive' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Requires Machine</div><div>{{ $operationMaster->requires_machine ? 'Yes' : 'No' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">QC Default</div><div>{{ $operationMaster->requires_qc ? 'Yes' : 'No' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Sort Order</div><div>{{ $operationMaster->sort_order }}</div></div>
                <div class="col-12 col-md-9"><div class="small text-body-secondary">Entry Route</div><div>{{ $operationMaster->entry_route ?: '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $operationMaster->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>
@endsection
