@extends('layouts.erp')

@section('title', 'Production V2 Part Definitions')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Part Definitions</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    @can('production.plan.create')
        <a href="{{ route('projects.production-v2.parts.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Add Part Definition
        </a>
    @endcan
@endsection

@section('content')
    @include('production_v2.partials.design_nav', ['project' => $project, 'active' => 'parts'])
    @include('production_v2.partials.mobile_list_styles')

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Total Parts</div>
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
                    <div class="small text-uppercase text-body-secondary mb-1">Cuttable</div>
                    <div class="display-6 mb-0">{{ number_format($summary['cuttable']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-8">
                    <label class="form-label mb-1" for="q">Search</label>
                    <input id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Part code, name, grade, drawing ref">
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
                            <th>Part Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Grade</th>
                            <th class="text-end">Required Qty</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($parts as $part)
                        <tr>
                            <td><strong>{{ $part->part_code }}</strong></td>
                            <td>
                                <div>{{ $part->part_name }}</div>
                                <div class="small text-body-secondary">{{ $part->drawing_ref ?: 'No drawing ref' }}</div>
                            </td>
                            <td><span class="badge text-bg-light border">{{ $part->part_type }}</span></td>
                            <td>{{ $part->material_grade ?: '-' }}</td>
                            <td class="text-end">{{ number_format((float) $part->required_qty, 3) }} {{ $part->uom?->code }}</td>
                            <td><span class="badge text-bg-light border">{{ ucfirst($part->status) }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.parts.show', ['project' => $project->id, 'part' => $part->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No V2 part definitions found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pv2-mobile-list p-3">
                @forelse($parts as $part)
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">{{ $part->part_code }}</div>
                                <div class="small text-body-secondary">{{ $part->part_name }}</div>
                            </div>
                            <span class="badge text-bg-light border">{{ ucfirst($part->status) }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Type</span>
                                <span>{{ $part->part_type }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Grade</span>
                                <span>{{ $part->material_grade ?: '-' }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Qty</span>
                                <span>{{ number_format((float) $part->required_qty, 3) }} {{ $part->uom?->code }}</span>
                            </div>
                        </div>
                        <div class="small text-body-secondary mt-3">{{ $part->drawing_ref ?: 'No drawing ref' }}</div>
                        <div class="mt-3">
                            <a href="{{ route('projects.production-v2.parts.show', ['project' => $project->id, 'part' => $part->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No V2 part definitions found.</div>
                @endforelse
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($parts->total() > 0)
                    Showing {{ $parts->firstItem() }} to {{ $parts->lastItem() }} of {{ $parts->total() }} part definitions
                @else
                    Showing 0 part definitions
                @endif
            </small>
        </div>
    </div>

    <div class="mt-3">
        {{ $parts->links() }}
    </div>
@endsection
