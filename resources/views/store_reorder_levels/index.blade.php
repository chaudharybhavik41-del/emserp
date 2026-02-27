@extends('layouts.erp')

@section('title', 'Store Reorder Levels')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Store Reorder Levels (Min/Target)</h1>
        @can('store.stock_item.update')
            <a href="{{ route('store-reorder-levels.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle"></i> Add Reorder Level
            </a>
        @endcan
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
                    <label class="form-label small">Brand</label>
                    <input type="text" name="brand" value="{{ $filters['brand'] ?? '' }}" class="form-control form-control-sm" placeholder="Any">
                    <div class="form-text">Leave blank for all.</div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Active</label>
                    <select name="is_active" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                        <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                    </select>
                </div>

                <div class="col-md-3 text-end">
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                    <a href="{{ route('store-reorder-levels.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <a href="{{ route('store-low-stock.index') }}" class="btn btn-sm btn-success">
                        <i class="bi bi-exclamation-triangle"></i> Low Stock
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Brand</th>
                    <th>Project</th>
                    <th class="text-end">Min Qty</th>
                    <th class="text-end">Target Qty</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($levels as $lvl)
                    <tr>
                        <td>{{ $lvl->id }}</td>
                        <td>
                            {{ $lvl->item?->code ? ($lvl->item->code.' - ') : '' }}{{ $lvl->item?->name ?? ('Item #'.$lvl->item_id) }}
                            @if($lvl->item?->uom)
                                <span class="text-muted small">({{ $lvl->item->uom->code ?? $lvl->item->uom->name }})</span>
                            @endif
                        </td>
                        <td>{{ $lvl->brand ?: 'ANY' }}</td>
                        <td>{{ $lvl->project?->code ?? 'GENERAL' }}</td>
                        <td class="text-end">{{ number_format((float) $lvl->min_qty, 3) }}</td>
                        <td class="text-end">{{ number_format((float) $lvl->target_qty, 3) }}</td>
                        <td>
                            @if($lvl->is_active)
                                <span class="badge bg-success">ACTIVE</span>
                            @else
                                <span class="badge bg-secondary">INACTIVE</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @can('store.stock_item.update')
                                <a href="{{ route('store-reorder-levels.edit', $lvl) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="{{ route('store-reorder-levels.destroy', $lvl) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this reorder level?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">No reorder levels found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($levels->hasPages())
            <div class="card-footer pb-0">
                {{ $levels->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
