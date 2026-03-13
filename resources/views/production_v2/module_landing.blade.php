@extends('layouts.erp')

@section('title', 'Production V2 Modules')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Modules</h1>
        <div class="small text-body-secondary">Open the correct department workspace. Design owns part list, BOM interpretation, nesting, and material planning. Production owns route planning, process planning, execution, and DPR.</div>
    </div>
@endsection

@section('content')
    @php
        $user = auth()->user();
        $canOpenDesign = $user?->can('production.plan.view') || $user?->can('production.plan.create') || $user?->can('production.plan.update');
        $canOpenProduction = $user?->can('production.plan.update') || $user?->can('production.dpr.view') || $user?->can('production.dpr.create') || $user?->can('production.qc.perform');
    @endphp
    <div class="row g-3">
        @if($canOpenDesign)
        <div class="col-12 col-xl-6">
            <div class="card h-100 border-primary-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-primary mb-2">Design Department</div>
                    <h2 class="h5 mb-2">Design V2 Module</h2>
                    <p class="text-body-secondary mb-3">Use this module for part list control, assembly consumption map, cutting plan / nesting, and material planning before shopfloor release.</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-light border">Part List</span>
                        <span class="badge text-bg-light border">Assembly BOM</span>
                        <span class="badge text-bg-light border">Cutting Plan / Nesting</span>
                        <span class="badge text-bg-light border">Material Planning</span>
                    </div>
                    <a href="{{ route('production-v2.design.index') }}" class="btn btn-primary">Open Design Module</a>
                </div>
            </div>
        </div>
        @endif

        @if($canOpenProduction)
        <div class="col-12 col-xl-6">
            <div class="card h-100 border-success-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-success mb-2">Production Department</div>
                    <h2 class="h5 mb-2">Production V2 Module</h2>
                    <p class="text-body-secondary mb-3">Use this module for route planning, process masters, cut execution, WIP control, fit-up, welding, inspection, rework, trial assembly, and supervisor/operator DPR activity.</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-light border">Route Planning</span>
                        <span class="badge text-bg-light border">Process Masters</span>
                        <span class="badge text-bg-light border">Cut Batch</span>
                        <span class="badge text-bg-light border">WIP</span>
                        <span class="badge text-bg-light border">Fit-up</span>
                        <span class="badge text-bg-light border">Welding / Inspection</span>
                    </div>
                    <a href="{{ route('production-v2.production.index') }}" class="btn btn-success">Open Production Module</a>
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection
