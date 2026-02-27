@extends('layouts.erp')

@section('title', 'Store Stock Register')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Store Stock Register</h1>
</div>

<form method="GET" id="filterForm" class="card mb-3">
    <div class="card-body row g-2 align-items-end">

        {{-- Item --}}
        <div class="col-md-3">
            <label class="form-label">Item</label>
            <select name="item_id" class="form-select form-select-sm auto-submit">
                <option value="">All Items</option>
                @foreach($items as $item)
                    <option value="{{ $item->id }}" 
                        {{ $selectedItemId == $item->id ? 'selected' : '' }}>
                        {{ $item->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Project --}}
        <div class="col-md-3">
            <label class="form-label">Project</label>
            <select name="project_id" class="form-select form-select-sm auto-submit">
                <option value="">All Projects</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" 
                        {{ $selectedProjectId == $project->id ? 'selected' : '' }}>
                        {{ $project->code }} - {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- From Date --}}
        <div class="col-md-2">
            <label class="form-label">From Date</label>
            <input type="date" name="from_date" 
                value="{{ $fromDate }}" 
                class="form-control form-control-sm auto-submit">
        </div>

        {{-- To Date --}}
        <div class="col-md-2">
            <label class="form-label">To Date</label>
            <input type="date" name="to_date" 
                value="{{ $toDate }}" 
                class="form-control form-control-sm auto-submit">
        </div>

        {{-- Include RAW --}}
        <div class="col-md-2">
            <div class="form-check mt-4">
                <input class="form-check-input auto-submit" 
                       type="checkbox" 
                       value="1" 
                       name="include_raw"
                       {{ !empty($includeRaw) ? 'checked' : '' }}>
                <label class="form-check-label">
                    Include RAW
                </label>
            </div>
        </div>

    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm  mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Ref No</th>
                    <th>Item</th>
                    <th>Project</th>
                    <th class="text-end">In (pcs)</th>
                    <th class="text-end">Out (pcs)</th>
                    <th class="text-end">In (kg)</th>
                    <th class="text-end">Out (kg)</th>
                    <th class="text-end">Balance (kg)</th>
                </tr>
                </thead>
                <tbody>
                @forelse($movements as $row)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($row->txn_date)->format('d-m-Y') }}</td>
                        <td>{{ ucfirst($row->txn_type) }}</td>
                        <td>{{ $row->reference_number }}</td>
                        <td>{{ optional($items->firstWhere('id', $row->item_id))->name }}</td>
                        <td>{{ optional($projects->firstWhere('id', $row->project_id))->code }}</td>
                        <td class="text-end">{{ number_format($row->qty_in_pcs ?? 0) }}</td>
                        <td class="text-end">{{ number_format($row->qty_out_pcs ?? 0) }}</td>
                        <td class="text-end">{{ number_format($row->weight_in_kg ?? 0, 3) }}</td>
                        <td class="text-end">{{ number_format($row->weight_out_kg ?? 0, 3) }}</td>
                        <td class="text-end">
                            {{ isset($row->balance_kg) ? number_format($row->balance_kg, 3) : '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted">
                            No movements found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection


@push('scripts')
<script>
    document.querySelectorAll('.auto-submit').forEach(function (el) {

        // Select change par
        el.addEventListener('change', function () {
            document.getElementById('filterForm').submit();
        });

        // Date input ma enter press thay to
        el.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('filterForm').submit();
            }
        });

    });
</script>
@endpush