@extends('layouts.erp')

@section('title', 'Chart of Accounts')

@section('content')
@php
    $visibleNodes = collect($tree ?? [])->filter(function ($node) use ($hasFilters) {
        return !($hasFilters ?? false) || !empty($node['has_content']);
    });
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Chart of Accounts</h1>
            <div class="small text-muted">Showing {{ $accountsCount }} ledger(s)</div>
        </div>

        <div class="d-flex gap-2">
            @can('accounting.accounts.create')
                <a href="{{ route('accounting.accounts.create') }}" class="btn btn-primary btn-sm">
                    Add Account
                </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2 mb-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2 mb-3">{{ session('error') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label form-label-sm">Search</label>
                    <input type="text"
                           name="q"
                           value="{{ $q }}"
                           class="form-control form-control-sm"
                           placeholder="Search by name or code">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Group</label>
                    <select name="group_id" class="form-select form-select-sm">
                        <option value="">All Groups</option>
                        @foreach($flatGroups as $g)
                            <option value="{{ $g->id }}" @selected((int)$groupId === (int)$g->id)>
                                {{ $g->indent_name ?? $g->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label form-label-sm">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        @foreach($ledgerTypes as $key => $label)
                            @if($key !== 'party')
                                <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label form-label-sm">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="" @selected($status === '')>All</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="col-md-1 d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm w-100" type="submit">Go</button>
                </div>

                <div class="col-12 mt-2">
                    <a href="{{ route('accounting.accounts.index') }}" class="btn btn-link btn-sm px-0">Reset filters</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small text-muted">
                Expand a group to see sub-groups and ledgers.
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="coaExpandAll">Expand all</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="coaCollapseAll">Collapse all</button>
            </div>
        </div>

        <div class="card-body">
            @if($visibleNodes->isEmpty())
                <div class="border rounded-3 p-4 text-center bg-body-tertiary">
                    <div class="fw-semibold mb-1">No ledgers found</div>
                    <div class="small text-muted">Try clearing filters or creating a new account.</div>
                    <a href="{{ route('accounting.accounts.index') }}" class="btn btn-link btn-sm mt-2">Reset filters</a>
                </div>
            @else
                @foreach($visibleNodes as $node)
                    @include('accounting.accounts._group_tree', ['node' => $node, 'depth' => 0, 'ledgerTypes' => $ledgerTypes])
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* Fix collapse visibility for Tailwind CSS conflict */
.collapse.show {
    visibility: visible !important;
}

.coa-group {
    border-left: 2px solid var(--bs-border-color-translucent);
}

.coa-group-header {
    border-radius: .5rem;
}

.coa-toggle {
    color: var(--bs-body-color);
}

.coa-toggle:focus {
    box-shadow: none;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const hasActiveFilters = @json((bool)($hasFilters ?? false));

    // Expand / Collapse all buttons
    const expandBtn   = document.getElementById('coaExpandAll');
    const collapseBtn = document.getElementById('coaCollapseAll');
    const allSections = () => Array.from(document.querySelectorAll('.coa-collapse'));

    function setExpandedIcons() {
        document.querySelectorAll('.coa-toggle').forEach(btn => {
            const target = btn.getAttribute('data-bs-target');
            const icon   = btn.querySelector('.coa-icon');
            if (!target || !icon) return;
            const section = document.querySelector(target);
            const expanded = section && section.classList.contains('show');
            icon.className = expanded ? 'coa-icon bi bi-dash-square-fill' : 'coa-icon bi bi-plus-square';
        });
    }

    if (expandBtn) {
        expandBtn.addEventListener('click', () => {
            allSections().forEach(section => {
                const c = bootstrap.Collapse.getOrCreateInstance(section, { toggle: false });
                c.show();
            });
            setTimeout(setExpandedIcons, 50);
        });
    }
    if (collapseBtn) {
        collapseBtn.addEventListener('click', () => {
            allSections().forEach(section => {
                const c = bootstrap.Collapse.getOrCreateInstance(section, { toggle: false });
                c.hide();
            });
            setTimeout(setExpandedIcons, 50);
        });
    }

    // Sync +/- icons with toggle events
    document.querySelectorAll('.coa-collapse').forEach(section => {
        section.addEventListener('shown.bs.collapse', setExpandedIcons);
        section.addEventListener('hidden.bs.collapse', setExpandedIcons);
    });

    if (hasActiveFilters) {
        allSections().forEach(section => {
            const c = bootstrap.Collapse.getOrCreateInstance(section, { toggle: false });
            c.show();
        });
    }

    setExpandedIcons();
});
</script>
@endpush
