@extends('layouts.erp')

@section('title', 'Low Stock')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Low Stock (Min/Target)
            <span class="text-muted small">(Own material only)</span>
        </h1>
        <div>
            <a href="{{ route('store-reorder-levels.index') }}" class="btn btn-sm btn-outline-secondary">Reorder Levels</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Item</label>
                    <select name="item_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($items as $it)
                            <option value="{{ $it->id }}" @selected(($filters['item_id'] ?? '') == $it->id)>
                                {{ $it->code ? ($it->code.' - ') : '' }}{{ $it->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="NULL" @selected(($filters['project_id'] ?? '') === 'NULL')>GENERAL</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" @selected(($filters['project_id'] ?? '') == $p->id)>
                                {{ $p->code }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="only_low" name="only_low" @checked(($filters['only_low'] ?? '') == '1')>
                        <label class="form-check-label" for="only_low">Show only LOW</label>
                    </div>
                </div>

                <div class="col-md-5 text-end">
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                    <a href="{{ route('store-low-stock.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('store-low-stock.create-indent') }}">
        @csrf

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Create Purchase Indent from Low Stock</span>
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="bi bi-file-earmark-plus"></i> Create Draft Indent
                </button>
            </div>
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
                        <select name="department_id" class="form-select" required>
                            <option value="">Select</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Required By <span class="text-danger">*</span></label>
                        <input type="date" name="required_by_date" class="form-control" required value="{{ now()->addDays(7)->toDateString() }}">
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Remarks</label>
                        <input type="text" name="remarks" class="form-control" maxlength="2000" placeholder="Optional">
                    </div>
                </div>

                <div class="form-text mt-2">
                    * If you select multiple projects, the system will create one draft indent per project (GENERAL and each project separately).
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width:36px;">
                            <input type="checkbox" id="chk_all" onclick="toggleAll(this)">
                        </th>
                        <th>Item</th>
                        <th>Brand</th>
                        <th>Project</th>
                        <th class="text-end">Available</th>
                        <th class="text-end">Min</th>
                        <th class="text-end">Target</th>
                        <th class="text-end">Suggested</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $r)
                        @php
                            $lvl = $r['level'];
                            $isLow = $r['is_low'];
                        @endphp
                        <tr class="{{ $isLow ? 'table-warning' : '' }}">
                            <td>
                                <input type="checkbox" name="level_ids[]" value="{{ $lvl->id }}" @checked($isLow)>
                            </td>
                            <td>
                                {{ $lvl->item?->code ? ($lvl->item->code.' - ') : '' }}{{ $lvl->item?->name ?? ('Item #'.$lvl->item_id) }}
                                @if($lvl->item?->uom)
                                    <span class="text-muted small">({{ $lvl->item->uom->code ?? $lvl->item->uom->name }})</span>
                                @endif
                            </td>
                            <td>{{ $lvl->brand ?: 'ANY' }}</td>
                            <td>{{ $lvl->project?->code ?? 'GENERAL' }}</td>
                            <td class="text-end">{{ number_format((float) $r['available_qty'], 3) }}</td>
                            <td class="text-end">{{ number_format((float) $r['min_qty'], 3) }}</td>
                            <td class="text-end">{{ number_format((float) $r['target_qty'], 3) }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $r['suggested_qty'], 3) }}</td>
                            <td>
                                @if($isLow)
                                    <span class="badge bg-danger">LOW</span>
                                @else
                                    <span class="badge bg-success">OK</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-3">No reorder levels found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<script>
function toggleAll(chk) {
    document.querySelectorAll('input[name="level_ids[]"]').forEach(function (c) {
        c.checked = chk.checked;
    });
}
</script>
@endsection
