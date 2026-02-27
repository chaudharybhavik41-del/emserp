@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-clipboard2-data"></i> Plan: {{ $plan->plan_number }}</h2>
            <div class="text-muted small">
                BOM: <span class="fw-semibold">{{ $plan->bom?->bom_number ?? '—' }}</span> |
                Status:
                @if($plan->status === 'approved')
                    <span class="badge text-bg-success">Approved</span>
                @else
                    <span class="badge text-bg-secondary">Draft</span>
                @endif
            </div>
        </div>

        <a href="{{ route('projects.production-plans.index', $project) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    @if($plan->remarks)
        <div class="alert alert-light border">
            <div class="fw-semibold">Remarks</div>
            <div class="text-muted">{{ $plan->remarks }}</div>
        </div>
    @endif

    <div class="d-flex gap-2 mb-3">
        @can('production.plan.approve')
            @if($plan->status === 'draft')
                <form method="POST" action="{{ route('projects.production-plans.approve', [$project, $plan]) }}"
                      onsubmit="return confirm('Approve this plan? After approval, routes cannot be edited.');">
                    @csrf
                    <button class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Approve Plan
                    </button>
                </form>
            @endif
        @endcan
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Seq</th>
                        <th>Type</th>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>Assembly</th>
                        <th>Qty</th>
                        <th>Weight (kg)</th>
                        <th>Route</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $it)
                        <tr>
                            <td>{{ $it->sequence_no }}</td>
                            <td class="text-capitalize">{{ $it->item_type }}</td>
                            <td class="fw-semibold">{{ $it->item_code }}</td>
                            <td class="text-muted small">{{ $it->description }}</td>
                            <td>{{ $it->assembly_mark ?? '—' }}</td>
                            <td>{{ $it->planned_qty }} {{ $it->uom?->code ?? '' }}</td>
                            <td>{{ $it->planned_weight_kg ?? '—' }}</td>
                            <td class="small">
                                @php
                                    $enabledCount = $it->activities->where('is_enabled', true)->count();
                                    $totalCount = $it->activities->count();
                                @endphp
                                {{ $enabledCount }} / {{ $totalCount }} enabled
                            </td>
                            <td class="text-end">
                                @can('production.plan.update')
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="{{ route('projects.production-plans.route.edit', [$project, $plan, $it]) }}">
                                        Route
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-3">
                {{ $items->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
