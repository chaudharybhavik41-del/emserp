@extends('layouts.erp')

@section('title', 'Production V2 Operation Event')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $operationEvent->operationMaster?->name ?: 'Operation Event' }} - OP-{{ $operationEvent->id }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.operation-events.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        @if($operationEvent->dpr)
            <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $operationEvent->dpr->id]) }}" class="btn btn-sm btn-outline-primary">Open DPR</a>
        @endif
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'operations'])
    @include('production_v2.partials.mobile_list_styles')

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Operation</div>
                    <div class="display-6 mb-0">{{ $operationEvent->operationMaster?->name ?: '-' }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Target</div>
                    <div class="display-6 mb-0">{{ $operationEvent->partDefinition?->part_code ?: $operationEvent->assembly?->assembly_code ?: '-' }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Status</div>
                    <div class="display-6 mb-0">{{ ucfirst($operationEvent->status) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Operation</div><div>{{ $operationEvent->operationMaster?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Date</div><div>{{ $operationEvent->operation_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Target</div><div>{{ $operationEvent->partDefinition?->part_code ?: $operationEvent->assembly?->assembly_code ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Qty</div><div>{{ number_format((float) $operationEvent->qty, 3) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Worker</div><div>{{ $operationEvent->worker?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Machine</div><div>{{ $operationEvent->machine?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Contractor</div><div>{{ $operationEvent->contractor?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Status</div><div>{{ $operationEvent->status }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Result</div><div>{{ $operationEvent->result ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Reference No</div><div>{{ $operationEvent->reference_no ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">WIP Piece</div><div>{{ $operationEvent->wipItem?->piece_no ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">DPR</div><div>{{ $operationEvent->dpr?->id ? ('DPR-' . $operationEvent->dpr->id) : '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $operationEvent->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>
@endsection
