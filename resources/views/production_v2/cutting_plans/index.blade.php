@extends('layouts.erp')

@section('title', 'Production V2 Cutting Plans')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Cutting Plans</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    @can('production.plan.create')
        <a href="{{ route('projects.production-v2.cutting-plans.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Create Cutting Plan
        </a>
    @endcan
@endsection

@section('content')
    @include('production_v2.partials.design_nav', ['project' => $project, 'active' => 'cutting_plans'])
    @include('production_v2.partials.mobile_list_styles')

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Total Plans</div>
                    <div class="display-6 mb-0">{{ number_format($summary['total']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-success-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-success mb-1">Released</div>
                    <div class="display-6 mb-0">{{ number_format($summary['released']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-primary-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-primary mb-1">Mixed Source</div>
                    <div class="display-6 mb-0">{{ number_format($summary['mixed']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-dark-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Allocations</div>
                    <div class="display-6 mb-0">{{ number_format($summary['allocations']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Plan No</th>
                            <th>Date</th>
                            <th>Grade</th>
                            <th>Thickness</th>
                            <th>Source</th>
                            <th class="text-end">Allocations</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($plans as $plan)
                        <tr>
                            <td><strong>{{ $plan->plan_number }}</strong></td>
                            <td>{{ $plan->plan_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $plan->grade ?: '-' }}</td>
                            <td>{{ $plan->thickness_mm ?: '-' }}</td>
                            <td><span class="badge text-bg-light border">{{ str_replace('_', ' ', $plan->source_mode) }}</span></td>
                            <td class="text-end">{{ number_format($plan->allocations_count) }}</td>
                            <td><span class="badge text-bg-light border">{{ ucfirst($plan->status) }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.cutting-plans.show', ['project' => $project->id, 'cuttingPlan' => $plan->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No V2 cutting plans found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pv2-mobile-list p-3">
                @forelse($plans as $plan)
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">{{ $plan->plan_number }}</div>
                                <div class="small text-body-secondary">{{ $plan->plan_date?->format('Y-m-d') ?: 'No plan date' }}</div>
                            </div>
                            <span class="badge text-bg-light border">{{ ucfirst($plan->status) }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Grade</span>
                                <span>{{ $plan->grade ?: '-' }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Thickness</span>
                                <span>{{ $plan->thickness_mm ?: '-' }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Source</span>
                                <span>{{ str_replace('_', ' ', $plan->source_mode) }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Allocations</span>
                                <span>{{ number_format($plan->allocations_count) }}</span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('projects.production-v2.cutting-plans.show', ['project' => $project->id, 'cuttingPlan' => $plan->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No V2 cutting plans found.</div>
                @endforelse
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($plans->total() > 0)
                    Showing {{ $plans->firstItem() }} to {{ $plans->lastItem() }} of {{ $plans->total() }} cutting plans
                @else
                    Showing 0 cutting plans
                @endif
            </small>
        </div>
    </div>

    <div class="mt-3">
        {{ $plans->links() }}
    </div>
@endsection
