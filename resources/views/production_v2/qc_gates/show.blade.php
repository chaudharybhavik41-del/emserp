@extends('layouts.erp')

@section('title', 'Production V2 QC Gate')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">QC Gate #{{ $qcGate->id }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'qc_gates'])

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('projects.production-v2.qc-gates.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Date</div><div>{{ $qcGate->gate_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Process</div><div>{{ $qcGate->operationMaster?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Target</div><div>{{ $qcGate->assembly?->assembly_code ?: $qcGate->partDefinition?->part_code ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Result</div><div>{{ strtoupper($qcGate->result) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Gate Mode</div><div>{{ strtoupper(str_replace('_', ' ', $qcGate->gate_mode ?: '-')) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Gate Type</div><div>{{ strtoupper(str_replace('_', ' ', $qcGate->gate_type ?: '-')) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Checked By</div><div>{{ $qcGate->checkedBy?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Reference</div><div>{{ $qcGate->reference_no ?: '-' }}</div></div>
                <div class="col-12 col-md-4"><div class="small text-body-secondary">Inspector Agency</div><div>{{ $qcGate->inspector_agency ?: '-' }}</div></div>
                <div class="col-12 col-md-4"><div class="small text-body-secondary">Part Step</div><div>{{ $qcGate->partRouteStep?->operation_name ?: '-' }}</div></div>
                <div class="col-12 col-md-4"><div class="small text-body-secondary">Assembly Step</div><div>{{ $qcGate->assemblyRouteStep?->operation_name ?: '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $qcGate->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>
@endsection
