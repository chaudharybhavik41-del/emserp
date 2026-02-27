@extends('layouts.erp')

@section('title', 'TDS Sections')

@section('content')
<div class="container-fluid">
    @php
        $sectionRows = method_exists($sections, 'getCollection') ? $sections->getCollection() : collect($sections);
        $activeCount = $sectionRows->where('is_active', true)->count();
    @endphp
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">TDS Sections</h1>
        @can('accounting.accounts.update')
            <a href="{{ route('accounting.tds-sections.create') }}" class="btn btn-primary btn-sm">
                Add TDS Section
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
                    <div class="small text-muted">Rows on this page</div>
                    <div class="h6 mb-0">{{ $sections->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted">Active on this page</div>
                    <div class="h6 mb-0">{{ $activeCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted">Company ID</div>
                    <div class="h6 mb-0">{{ $companyId }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="194C / Rent / Professional">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Filter</button>
                    <a href="{{ route('accounting.tds-sections.index') }}" class="btn btn-link btn-sm">Reset</a>
                </div>
                <div class="col-md-4 text-end">
                    <div class="small text-muted">Company ID: {{ $companyId }}</div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <label class="form-label form-label-sm">Quick search (visible rows)</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="tdsSecQuickSearch" class="form-control" placeholder="Code/name/description/status...">
                <button type="button" id="tdsSecQuickSearchClear" class="btn btn-outline-secondary">Clear</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 12%;">Code</th>
                            <th>Name</th>
                            <th style="width: 14%;" class="text-end">Default %</th>
                            <th style="width: 10%;">Active</th>
                            <th style="width: 18%;">Description</th>
                            <th style="width: 14%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="tdsSecNoMatchRow" class="d-none">
                            <td colspan="6" class="text-center text-muted py-3">No sections match the search text.</td>
                        </tr>
                        @forelse($sections as $section)
                            @php
                                $searchText = strtolower(trim(
                                    ($section->code ?? '') . ' ' .
                                    ($section->name ?? '') . ' ' .
                                    ($section->description ?? '') . ' ' .
                                    ($section->is_active ? 'active enabled' : 'inactive disabled')
                                ));
                            @endphp
                            <tr class="tds-sec-row" data-row-text="{{ $searchText }}">
                                <td class="fw-semibold">{{ $section->code }}</td>
                                <td>{{ $section->name }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $section->default_rate, 4), '0'), '.') }}</td>
                                <td>
                                    @if($section->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Disabled</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $section->description ?: '—' }}</td>
                                <td>
                                    @can('accounting.accounts.update')
                                        <a href="{{ route('accounting.tds-sections.edit', $section) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form action="{{ route('accounting.tds-sections.destroy', $section) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Delete this TDS section?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @else
                                        <span class="text-muted small">No access</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">No TDS sections found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($sections->hasPages())
            <div class="card-footer">
                {{ $sections->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('tdsSecQuickSearch');
    const clearBtn = document.getElementById('tdsSecQuickSearchClear');
    const rows = Array.from(document.querySelectorAll('.tds-sec-row'));
    const noMatch = document.getElementById('tdsSecNoMatchRow');
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
