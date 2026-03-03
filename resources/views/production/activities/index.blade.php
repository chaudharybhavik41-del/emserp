@extends('layouts.erp')

@section('title', 'Production Activities')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production Activities</h1>
        <div class="small text-body-secondary">Manage activity master used in planning, billing, machine, and QC workflows.</div>
    </div>
    @can('production.activity.create')
        <a href="{{ route('production.activities.create') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Add Activity
        </a>
    @endcan
@endsection

@section('content')
@php
    $activeInPage = $activities->getCollection()->where('is_active', true)->count();
    $machineInPage = $activities->getCollection()->where('requires_machine', true)->count();
    $qcInPage = $activities->getCollection()->where('requires_qc', true)->count();
@endphp

<div class="row g-3 mb-3">
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body py-3">
        <div class="small text-uppercase text-body-secondary mb-1">Total Results</div>
        <div class="h4 mb-0">{{ number_format($activities->total()) }}</div>
    </div></div></div>
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body py-3">
        <div class="small text-uppercase text-body-secondary mb-1">Active In Page</div>
        <div class="h4 mb-0">{{ number_format($activeInPage) }}</div>
    </div></div></div>
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body py-3">
        <div class="small text-uppercase text-body-secondary mb-1">Machine-Required In Page</div>
        <div class="h4 mb-0">{{ number_format($machineInPage) }}</div>
    </div></div></div>
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body py-3">
        <div class="small text-uppercase text-body-secondary mb-1">QC-Required In Page</div>
        <div class="h4 mb-0">{{ number_format($qcInPage) }}</div>
    </div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('production.activities.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label mb-1" for="q">Search</label>
                <input type="text" id="q" name="q" class="form-control form-control-sm" placeholder="Code or name" value="{{ $q }}">
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label mb-1" for="status">Status</label>
                <select id="status" name="status" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                    <option value="all" @selected($status === 'all')>All</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label mb-1" for="applies_to">Applies To</label>
                <select id="applies_to" name="applies_to" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                    <option value="">All</option>
                    <option value="part" @selected(($appliesTo ?? request('applies_to')) === 'part')>Part</option>
                    <option value="assembly" @selected(($appliesTo ?? request('applies_to')) === 'assembly')>Assembly</option>
                    <option value="both" @selected(($appliesTo ?? request('applies_to')) === 'both')>Both</option>
                </select>
            </div>

            <div class="col-6 col-md-1">
                <label class="form-label mb-1" for="requires_machine">Machine</label>
                <select id="requires_machine" name="requires_machine" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                    <option value="">All</option>
                    <option value="1" @selected(request('requires_machine') === '1')>Yes</option>
                    <option value="0" @selected(request('requires_machine') === '0')>No</option>
                </select>
            </div>

            <div class="col-6 col-md-1">
                <label class="form-label mb-1" for="requires_qc">QC</label>
                <select id="requires_qc" name="requires_qc" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                    <option value="">All</option>
                    <option value="1" @selected(request('requires_qc') === '1')>Yes</option>
                    <option value="0" @selected(request('requires_qc') === '0')>No</option>
                </select>
            </div>

            <div class="col-6 col-md-1">
                <label class="form-label mb-1" for="sort">Sort</label>
                <select id="sort" name="sort" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                    <option value="" @selected(request('sort', '') === '')>Sequence</option>
                    <option value="code" @selected(request('sort') === 'code')>Code</option>
                    <option value="name" @selected(request('sort') === 'name')>Name</option>
                    <option value="applies_to" @selected(request('sort') === 'applies_to')>Applies To</option>
                    <option value="calculation_method" @selected(request('sort') === 'calculation_method')>Billing</option>
                    <option value="is_active" @selected(request('sort') === 'is_active')>Status</option>
                    <option value="updated_at" @selected(request('sort') === 'updated_at')>Updated</option>
                    <option value="created_at" @selected(request('sort') === 'created_at')>Created</option>
                </select>
            </div>

            <div class="col-6 col-md-1">
                <label class="form-label mb-1" for="dir">Dir</label>
                <select id="dir" name="dir" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                    <option value="asc" @selected(request('dir', 'asc') === 'asc')>Asc</option>
                    <option value="desc" @selected(request('dir') === 'desc')>Desc</option>
                </select>
            </div>

            <div class="col-6 col-md-1">
                <label class="form-label mb-1" for="per_page">Rows</label>
                <select id="per_page" name="per_page" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                    @foreach([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}" {{ (int) request('per_page', 25) === $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-funnel me-1"></i>Apply</button>
                <a href="{{ route('production.activities.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width: 7%">Seq</th>
                    <th style="width: 10%">Code</th>
                    <th style="width: 18%">Name</th>
                    <th style="width: 10%">Applies To</th>
                    <th style="width: 14%">Billing</th>
                    <th style="width: 17%">Flags</th>
                    <th style="width: 8%">Status</th>
                    <th style="width: 16%" class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($activities as $activity)
                    <tr>
                        <td>{{ $activity->default_sequence }}</td>
                        <td><span class="fw-semibold">{{ $activity->code }}</span></td>
                        <td>{{ $activity->name }}</td>
                        <td class="text-capitalize">{{ str_replace('_', ' ', (string) $activity->applies_to) }}</td>
                        <td>
                            <div class="small text-body-secondary">{{ $activity->calculation_method }}</div>
                            <div>{{ $activity->billingUom?->code ?? '-' }}</div>
                        </td>
                        <td>
                            @if($activity->is_fitupp)
                                <span class="badge text-bg-info">Fitup</span>
                            @endif
                            @if($activity->requires_machine)
                                <span class="badge text-bg-secondary">Machine</span>
                            @endif
                            @if($activity->requires_qc)
                                <span class="badge text-bg-warning text-dark">QC</span>
                            @endif
                        </td>
                        <td>
                            @if($activity->is_active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-dark">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @can('production.activity.update')
                                <a href="{{ route('production.activities.edit', $activity) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            @endcan
                            @can('production.activity.delete')
                                <form action="{{ route('production.activities.destroy', $activity) }}" method="POST" class="d-inline" onsubmit="return confirm('Disable this activity?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" {{ $activity->is_active ? '' : 'disabled' }} title="Disable">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No activities found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <small class="text-body-secondary">
            @if($activities->total() > 0)
                Showing {{ $activities->firstItem() }} to {{ $activities->lastItem() }} of {{ $activities->total() }} activities
            @else
                Showing 0 activities
            @endif
        </small>
        @if($activities->hasPages())
            <div>{{ $activities->links() }}</div>
        @endif
    </div>
</div>
@endsection
