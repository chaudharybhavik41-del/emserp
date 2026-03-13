@extends('layouts.erp')

@section('title', 'Production V2 Fit-ups')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Fit-ups</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.fitups.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Create Fit-up
    </a>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'fitups'])

    @php
        $pageRows = $rows->getCollection();
        $approvedCount = $pageRows->where('status', 'approved')->count();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Current Page</div><div class="display-6 mb-0">{{ number_format($pageRows->count()) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Approved Fit-ups</div><div class="display-6 mb-0">{{ number_format($approvedCount) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Next Step</div><div class="small text-body-secondary">Use the weld action directly from a fit-up row to keep the assembly chain moving.</div></div></div></div>
    </div>

    @include('production_v2.partials.mobile_list_styles')

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fit-up</th>
                            <th>Date</th>
                            <th>Assembly</th>
                            <th>Supervisor</th>
                            <th class="text-end">Consumptions</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>FU-{{ $row->id }}</strong></td>
                            <td>{{ $row->fitup_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $row->assembly?->assembly_code ?: '-' }}</td>
                            <td>{{ $row->supervisor?->name ?: '-' }}</td>
                            <td class="text-end">{{ number_format($row->consumptions_count) }}</td>
                            <td>{{ $row->status }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="{{ route('projects.production-v2.fitups.show', ['project' => $project->id, 'fitup' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    <a href="{{ route('projects.production-v2.welding-events.create', ['project' => $project->id, 'assembly_id' => $row->assembly_id]) }}" class="btn btn-sm btn-primary">Weld</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No fit-ups found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3 pv2-mobile-list">
                @forelse($rows as $row)
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">FU-{{ $row->id }}</div>
                                <div class="small text-body-secondary">{{ $row->assembly?->assembly_code ?: '-' }}</div>
                            </div>
                            <span class="badge {{ $row->status === 'approved' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($row->status) }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Date</span><span>{{ $row->fitup_date?->format('Y-m-d') ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Supervisor</span><span>{{ $row->supervisor?->name ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Consumptions</span><span>{{ number_format($row->consumptions_count) }}</span></div>
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <a href="{{ route('projects.production-v2.fitups.show', ['project' => $project->id, 'fitup' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open Fit-up</a>
                            <a href="{{ route('projects.production-v2.welding-events.create', ['project' => $project->id, 'assembly_id' => $row->assembly_id]) }}" class="btn btn-sm btn-primary">Create Welding</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No fit-ups found.</div>
                @endforelse
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} fit-ups
                @else
                    Showing 0 fit-ups
                @endif
            </small>
            {{ $rows->links() }}
        </div>
    </div>
@endsection
