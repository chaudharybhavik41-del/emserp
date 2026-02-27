@extends('layouts.erp')

@section('title', 'Account Groups')

@section('content')
<div class="container-fluid">
    @php
        $primaryCount = collect($flatGroups)->where('is_primary', true)->count();
    @endphp
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Account Groups</h1>
            <div class="small text-muted">Company ID: {{ $companyId }}</div>
        </div>
        @can('accounting.accounts.update')
            <a href="{{ route('accounting.account-groups.create') }}" class="btn btn-primary btn-sm">
                Add Group
            </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted">Total groups</div>
                    <div class="h6 mb-0">{{ count($flatGroups) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted">Primary groups</div>
                    <div class="h6 mb-0">{{ $primaryCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted">Search scope</div>
                    <div class="h6 mb-0">Code / Name / Nature</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-4">
                    <input type="text"
                           name="q"
                           value="{{ $q }}"
                           class="form-control form-control-sm"
                           placeholder="Search by code or name">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Filter</button>
                    <a href="{{ route('accounting.account-groups.index') }}" class="btn btn-link btn-sm">Reset</a>
                </div>
            </form>

            <div class="mb-3">
                <label class="form-label form-label-sm">Quick search (visible rows)</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="agQuickSearch" class="form-control" placeholder="Type to filter current results...">
                    <button type="button" id="agQuickSearchClear" class="btn btn-outline-secondary">Clear</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Code</th>
                            <th>Name</th>
                            <th style="width: 110px;">Nature</th>
                            <th>Parent</th>
                            <th class="text-center" style="width: 90px;">Primary</th>
                            <th class="text-end" style="width: 90px;">Sort</th>
                            <th class="text-end" style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="agNoMatchRow" class="d-none">
                            <td colspan="7" class="text-center text-muted py-4">No groups match the search text.</td>
                        </tr>
                        @forelse($flatGroups as $g)
                            @php
                                $searchText = strtolower(trim(
                                    ($g->code ?? '') . ' ' .
                                    ($g->name ?? '') . ' ' .
                                    ($g->indent_name ?? '') . ' ' .
                                    ($g->nature ?? '') . ' ' .
                                    ($g->parent?->name ?? '')
                                ));
                            @endphp
                            <tr class="ag-row" data-row-text="{{ $searchText }}">
                                <td class="fw-semibold">{{ $g->code }}</td>
                                <td>{{ $g->indent_name ?? $g->name }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ ucfirst($g->nature) }}</span>
                                </td>
                                <td class="text-muted">{{ $g->parent?->name ?? '—' }}</td>
                                <td class="text-center">
                                    @if($g->is_primary)
                                        <span class="badge bg-secondary">Yes</span>
                                    @else
                                        <span class="text-muted">No</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ $g->sort_order }}</td>
                                <td class="text-end">
                                    @can('accounting.accounts.update')
                                        <a href="{{ route('accounting.account-groups.edit', $g->id) }}" class="btn btn-outline-primary btn-sm">
                                            Edit
                                        </a>

                                        @if(! $g->is_primary)
                                            <form action="{{ route('accounting.account-groups.destroy', $g->id) }}"
                                                  method="POST"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Delete this group? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No groups found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="small text-muted mt-2">
                Tip: To create a sub-group, click <strong>Add Group</strong> and select a <strong>Parent Group</strong>.
                Nature will be inherited automatically from the parent.
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('agQuickSearch');
    const clearBtn = document.getElementById('agQuickSearchClear');
    const rows = Array.from(document.querySelectorAll('.ag-row'));
    const noMatch = document.getElementById('agNoMatchRow');
    if (!input || !rows.length) return;

    const applyFilter = function () {
        const needle = (input.value || '').trim().toLowerCase();
        let visible = 0;
        rows.forEach((row) => {
            const hay = (row.dataset.rowText || row.textContent || '').toLowerCase();
            const show = needle === '' || hay.includes(needle);
            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });
        if (noMatch) noMatch.classList.toggle('d-none', needle === '' || visible > 0);
    };

    input.addEventListener('input', applyFilter);
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            applyFilter();
        });
    }
    applyFilter();
});
</script>
@endpush
