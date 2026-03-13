@extends('layouts.erp')

@section('title', 'Production V2 Assemblies')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Assemblies</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    @can('production.plan.create')
        <a href="{{ route('projects.production-v2.assemblies.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Add Assembly
        </a>
    @endcan
@endsection

@section('content')
    @include('production_v2.partials.design_nav', ['project' => $project, 'active' => 'assemblies'])
    @include('production_v2.partials.mobile_list_styles')

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Total Assemblies</div>
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
            <div class="card h-100 border-dark-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">With Parts</div>
                    <div class="display-6 mb-0">{{ number_format($summary['with_parts']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-8">
                    <label class="form-label mb-1" for="q">Search</label>
                    <input id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Assembly code, name, span, girder">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button class="btn btn-primary w-100">Apply</button>
                    <a href="{{ request()->url() }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Assembly Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Span / Girder</th>
                            <th class="text-end">Planned Qty</th>
                            <th class="text-end">Parts</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($assemblies as $assembly)
                        <tr>
                            <td><strong>{{ $assembly->assembly_code }}</strong></td>
                            <td>
                                <div>{{ $assembly->assembly_name }}</div>
                                <div class="small text-body-secondary">{{ $assembly->drawing_ref ?: 'No drawing ref' }}</div>
                            </td>
                            <td>{{ $assembly->assembly_type ?: '-' }}</td>
                            <td>{{ trim(($assembly->span_no ?: '-') . ' / ' . ($assembly->girder_no ?: '-')) }}</td>
                            <td class="text-end">{{ number_format((float) $assembly->planned_qty, 3) }}</td>
                            <td class="text-end">{{ number_format($assembly->requirements_count) }}</td>
                            <td><span class="badge text-bg-light border">{{ ucfirst($assembly->status) }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.assemblies.show', ['project' => $project->id, 'assembly' => $assembly->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No V2 assemblies found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pv2-mobile-list p-3">
                @forelse($assemblies as $assembly)
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">{{ $assembly->assembly_code }}</div>
                                <div class="small text-body-secondary">{{ $assembly->assembly_name }}</div>
                            </div>
                            <span class="badge text-bg-light border">{{ ucfirst($assembly->status) }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Type</span>
                                <span>{{ $assembly->assembly_type ?: '-' }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Span / Girder</span>
                                <span>{{ trim(($assembly->span_no ?: '-') . ' / ' . ($assembly->girder_no ?: '-')) }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Qty / Parts</span>
                                <span>{{ number_format((float) $assembly->planned_qty, 3) }} / {{ number_format($assembly->requirements_count) }}</span>
                            </div>
                        </div>
                        <div class="small text-body-secondary mt-3">{{ $assembly->drawing_ref ?: 'No drawing ref' }}</div>
                        <div class="mt-3">
                            <a href="{{ route('projects.production-v2.assemblies.show', ['project' => $project->id, 'assembly' => $assembly->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No V2 assemblies found.</div>
                @endforelse
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($assemblies->total() > 0)
                    Showing {{ $assemblies->firstItem() }} to {{ $assemblies->lastItem() }} of {{ $assemblies->total() }} assemblies
                @else
                    Showing 0 assemblies
                @endif
            </small>
        </div>
    </div>

    <div class="mt-3">
        {{ $assemblies->links() }}
    </div>
@endsection
