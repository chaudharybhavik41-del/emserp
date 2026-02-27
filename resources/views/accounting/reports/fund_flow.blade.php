@extends('layouts.erp')

@section('title', 'Fund Flow Statement')

@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">Fund Flow (Sources &amp; Applications of Funds)</h1>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">From date</label>
                    <input type="date"
                           name="from_date"
                           value="{{ request('from_date', optional($fromDate)->toDateString()) }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">To date</label>
                    <input type="date"
                           name="to_date"
                           value="{{ request('to_date', optional($toDate)->toDateString()) }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-sm mt-3 mt-md-0">
                        <i class="bi bi-filter"></i> View
                    </button>
                    <a href="{{ route('accounting.reports.fund-flow') }}"
                       class="btn btn-outline-secondary btn-sm mt-3 mt-md-0">
                        Reset
                    </a>
                </div>

                <div class="col-md-2 text-end small text-muted">
                    Company #{{ $companyId }}
                </div>

                <div class="col-md-6">
                    <label class="form-label form-label-sm">Quick search group</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="fundFlowSearch" class="form-control" placeholder="Filter sources/applications by group...">
                        <button type="button" class="btn btn-outline-secondary" id="fundFlowSearchClear">Clear</button>
                    </div>
                </div>
            </form>

            <div class="mt-2 small text-muted">
                Based on movements in Balance Sheet groups (nature: asset / liability / equity).
                Income and expense groups are excluded; difference between total sources and
                applications roughly corresponds to P&amp;L impact for the period.
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-success-subtle">
                <div class="card-body py-2">
                    <div class="small text-muted">Total Sources</div>
                    <div class="h5 mb-0 text-success-emphasis">{{ number_format($totalSources, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger-subtle">
                <div class="card-body py-2">
                    <div class="small text-muted">Total Applications</div>
                    <div class="h5 mb-0 text-danger-emphasis">{{ number_format($totalApps, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card {{ $difference >= 0 ? 'border-primary-subtle' : 'border-warning-subtle' }}">
                <div class="card-body py-2">
                    <div class="small text-muted">Difference (Sources - Applications)</div>
                    <div class="h5 mb-0 {{ $difference >= 0 ? 'text-primary' : 'text-warning-emphasis' }}">
                        {{ number_format($difference, 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="fundFlowNoMatch" class="alert alert-warning d-none py-2">
        No rows match your search text.
    </div>

    <div class="row">
        {{-- Sources of funds --}}
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header py-2">
                    <div class="fw-semibold small">Sources of Funds</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 65%;">Particulars</th>
                                    <th style="width: 35%;" class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sources as $row)
                                    <tr class="fund-flow-row" data-search-text="{{ strtolower($row['label'] ?? '') }}">
                                        <td class="small">
                                            <a href="{{ route('accounting.accounts.index', ['group_id' => $row['group']->id]) }}"
                                               class="text-decoration-none">
                                                {{ $row['label'] }}
                                            </a>
                                            @if(! empty($row['note']))
                                                <span class="text-muted">({{ $row['note'] }})</span>
                                            @endif
                                        </td>
                                        <td class="small text-end">
                                            {{ number_format($row['amount'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="small text-muted text-center py-2">
                                            No sources identified for this period.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="table-dark text-white fw-semibold fund-flow-total-row">
                                    <td class="small text-end">Total Sources</td>
                                    <td class="small text-end">{{ number_format($totalSources, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Applications of funds --}}
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header py-2">
                    <div class="fw-semibold small">Applications of Funds</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 65%;">Particulars</th>
                                    <th style="width: 35%;" class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($applications as $row)
                                    <tr class="fund-flow-row" data-search-text="{{ strtolower($row['label'] ?? '') }}">
                                        <td class="small">
                                            <a href="{{ route('accounting.accounts.index', ['group_id' => $row['group']->id]) }}"
                                               class="text-decoration-none">
                                                {{ $row['label'] }}
                                            </a>
                                            @if(! empty($row['note']))
                                                <span class="text-muted">({{ $row['note'] }})</span>
                                            @endif
                                        </td>
                                        <td class="small text-end">
                                            {{ number_format($row['amount'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="small text-muted text-center py-2">
                                            No applications identified for this period.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="table-dark text-white fw-semibold fund-flow-total-row">
                                    <td class="small text-end">Total Applications</td>
                                    <td class="small text-end">{{ number_format($totalApps, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Reconciliation --}}
    <div class="card">
        <div class="card-body py-2 d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                Period: {{ optional($fromDate)->toDateString() }} to {{ optional($toDate)->toDateString() }}
            </div>
            <div class="small fw-semibold">
                Difference (Sources - Applications):
                {{ number_format($difference, 2) }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('fundFlowSearch');
    const clearBtn = document.getElementById('fundFlowSearchClear');
    const noMatch = document.getElementById('fundFlowNoMatch');
    const rows = Array.from(document.querySelectorAll('.fund-flow-row'));
    const totalRows = Array.from(document.querySelectorAll('.fund-flow-total-row'));

    function applySearch() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;

        rows.forEach((row) => {
            const txt = row.dataset.searchText || '';
            const match = !query || txt.includes(query);
            row.classList.toggle('d-none', !match);
            if (match) visible += 1;
        });

        totalRows.forEach((row) => row.classList.toggle('d-none', !!query));
        if (noMatch) noMatch.classList.toggle('d-none', !(query && visible === 0));
    }

    if (searchInput) searchInput.addEventListener('input', applySearch);
    if (clearBtn && searchInput) {
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            applySearch();
            searchInput.focus();
        });
    }
});
</script>
@endpush
