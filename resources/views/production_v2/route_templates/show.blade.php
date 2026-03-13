@extends('layouts.erp')

@section('title', 'Production V2 Route Template')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $routeTemplate->template_code }} - {{ $routeTemplate->template_name }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        @can('production.plan.update')
        <a href="{{ route('projects.production-v2.route-templates.edit', ['project' => $project->id, 'routeTemplate' => $routeTemplate->id]) }}" class="btn btn-sm btn-primary">Edit</a>
        @endcan
        <a href="{{ route('projects.production-v2.route-templates.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'route_templates'])
    @include('production_v2.partials.mobile_list_styles')

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Applies To</div>
                    <div class="display-6 mb-0">{{ ucfirst($routeTemplate->applies_to) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Status</div>
                    <div class="display-6 mb-0">{{ ucfirst($routeTemplate->status) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Step Count</div>
                    <div class="display-6 mb-0">{{ $routeTemplate->steps->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Applies To</div><div>{{ ucfirst($routeTemplate->applies_to) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Status</div><div>{{ $routeTemplate->status }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $routeTemplate->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Template Steps</div>
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Seq</th>
                            <th>Operation</th>
                            <th>Entry Mode</th>
                            <th>Machine</th>
                            <th>QC Gate</th>
                            <th>Gate Type</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($routeTemplate->steps as $step)
                        <tr>
                            <td>{{ $step->sequence_no }}</td>
                            <td>{{ $step->operationMaster?->name }}</td>
                            <td>{{ ucfirst($step->operationMaster?->entry_mode ?? '-') }}</td>
                            <td>{{ $step->operationMaster?->requires_machine ? 'Yes' : 'No' }}</td>
                            <td>{{ $step->qc_gate_required ? strtoupper(str_replace('_', ' ', $step->qc_gate_mode ?: '-')) : 'No' }}</td>
                            <td>{{ $step->qc_gate_required ? strtoupper(str_replace('_', ' ', $step->qc_gate_type ?: '-')) : '-' }}</td>
                            <td>{{ $step->remarks ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No route steps defined.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pv2-mobile-list p-3">
                @forelse($routeTemplate->steps as $step)
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">Step {{ $step->sequence_no }}</div>
                                <div class="small text-body-secondary">{{ $step->operationMaster?->name }}</div>
                            </div>
                            <span class="badge text-bg-light border">{{ ucfirst($step->operationMaster?->entry_mode ?? '-') }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Machine</span>
                                <span>{{ $step->operationMaster?->requires_machine ? 'Yes' : 'No' }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">QC Gate</span>
                                <span>{{ $step->qc_gate_required ? strtoupper(str_replace('_', ' ', $step->qc_gate_mode ?: '-')) : 'No' }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Gate Type</span>
                                <span>{{ $step->qc_gate_required ? strtoupper(str_replace('_', ' ', $step->qc_gate_type ?: '-')) : '-' }}</span>
                            </div>
                        </div>
                        <div class="small text-body-secondary mt-3">{{ $step->remarks ?: 'No step remarks' }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No route steps defined.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
