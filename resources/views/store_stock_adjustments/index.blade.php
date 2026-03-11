@extends('layouts.erp')

@section('title', 'Stock Adjustments / Openings')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Stock Adjustments / Openings</h1>
        @can('store.stock.adjustment.create')
            <a href="{{ route('store-stock-adjustments.create') }}" class="btn btn-sm btn-primary">
                New Stock Entry
            </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->has('general'))
        <div class="alert alert-danger">
            {{ $errors->first('general') }}
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body p-2">
            <form action="{{ route('store-stock-adjustments.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold mb-1">Filter by Project</label>
                    <select name="project_id" class="form-select form-select-sm project-filter shadow-sm">
                        <option value="">-- All Projects --</option>
                        <option value="none" @selected(request('project_id') === 'none')>None (General/Store)</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected((string)request('project_id') === (string)$project->id)>
                                {{ $project->code ?? 'N/A' }} - {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                    @if(request()->anyFilled(['project_id']))
                        <a href="{{ route('store-stock-adjustments.index') }}" class="btn btn-outline-secondary btn-sm">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                    <tr>
                        <th style="width: 120px;">Date</th>
                        <th style="width: 140px;">Ref. No.</th>
                        <th style="width: 140px;">Type</th>
                        <th>Project</th>
                        <th style="width: 160px;">Created By</th>
                        <th style="width: 120px;" class="text-end">Amount</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 80px;"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($adjustments as $adj)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($adj->adjustment_date)->format('d-m-Y') }}</td>
                            <td>
                                <a href="{{ route('store-stock-adjustments.show', $adj) }}">
                                    {{ $adj->reference_number ?? ('STAD-' . str_pad($adj->id, 4, '0', STR_PAD_LEFT)) }}
                                </a>
                            </td>
                            <td>
                                @php
                                    $type = $adj->adjustment_type ?? 'opening';
                                    $label = 'Opening';
                                    if ($type === 'increase') {
                                        $label = 'Increase';
                                    } elseif ($type === 'decrease') {
                                        $label = 'Decrease';
                                    }
                                @endphp
                                {{ $label }}
                            </td>
                            <td>
                                @if($adj->project)
                                    {{ $adj->project->code }} - {{ $adj->project->name }}
                                @else
                                    <span class="text-muted">General / Store</span>
                                @endif
                            </td>
                            <td>
                                @if($adj->createdBy)
                                    {{ $adj->createdBy->name }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-end">
                                {{ $adj->total_amount !== null ? number_format((float) $adj->total_amount, 2) : '-' }}
                            </td>
                            <td>
                                <span class="badge bg-success">{{ ucfirst($adj->status ?? 'posted') }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('store-stock-adjustments.show', $adj) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">
                                No stock adjustments found.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($adjustments instanceof \Illuminate\Contracts\Pagination\Paginator || $adjustments instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <div class="card-footer py-2">
                {{ $adjustments->links() }}
            </div>
        @endif
    </div>
@push('scripts')
<script>
$(document).ready(function() {
    $('.project-filter').select2({
        width: '100%',
        placeholder: '-- select --',
        allowClear: true,
        selectOnClose: true
    });

    // Auto-focus search field when opening any select2
    $(document).on('select2:open', () => {
        const searchField = document.querySelector('.select2-search__field');
        if (searchField) {
            searchField.focus();
        }
    });
});
</script>
@endpush
@endsection
