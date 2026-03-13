@extends('layouts.erp')

@section('title', 'Production V2 Trial Assembly')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Trial Assembly</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.trial-assemblies.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Create Trial Assembly
    </a>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'trial'])

    @php
        $pageRows = $rows->getCollection();
        $passedCount = $pageRows->where('status', 'passed')->count();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Current Page</div><div class="display-6 mb-0">{{ number_format($pageRows->count()) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-success mb-1">Passed Trials</div><div class="display-6 mb-0">{{ number_format($passedCount) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Quick Flow</div><div class="small text-body-secondary">Grouped assembly checks now stay visible and compact on mobile without losing measurement context.</div></div></div></div>
    </div>

    @include('production_v2.partials.mobile_list_styles')

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Trial</th>
                            <th>Date</th>
                            <th>Assembly Group</th>
                            <th>Checked By</th>
                            <th>Inspector</th>
                            <th class="text-end">Measurements</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>TA-{{ $row->id }}</strong></td>
                            <td>{{ $row->trial_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $row->assembly_group_ref }}</td>
                            <td>{{ $row->checkedBy?->name ?: '-' }}</td>
                            <td>{{ $row->inspector?->name ?: '-' }}</td>
                            <td class="text-end">{{ number_format($row->measurements_count) }}</td>
                            <td>{{ $row->status }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.trial-assemblies.show', ['project' => $project->id, 'trialAssembly' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No trial assemblies found.</td>
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
                                <div class="pv2-mobile-card__title">TA-{{ $row->id }}</div>
                                <div class="small text-body-secondary">{{ $row->assembly_group_ref }}</div>
                            </div>
                            <span class="badge {{ $row->status === 'passed' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst(str_replace('_', ' ', $row->status)) }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Date</span><span>{{ $row->trial_date?->format('Y-m-d') ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Checked By</span><span>{{ $row->checkedBy?->name ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Measurements</span><span>{{ number_format($row->measurements_count) }}</span></div>
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <a href="{{ route('projects.production-v2.trial-assemblies.show', ['project' => $project->id, 'trialAssembly' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open Trial</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No trial assemblies found.</div>
                @endforelse
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} trial assemblies
                @else
                    Showing 0 trial assemblies
                @endif
            </small>
            {{ $rows->links() }}
        </div>
    </div>
@endsection
