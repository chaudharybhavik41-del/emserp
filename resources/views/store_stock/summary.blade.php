@extends('layouts.erp')

@section('title', 'Store Stock Summary')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Store Stock Summary</h1>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('store-stock-summary.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Item</label>
                    <select name="item_id" class="form-select form-select-sm">
                        <option value="">-- All Items --</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}"
                                {{ (isset($filters['item_id']) && $filters['item_id'] == $item->id) ? 'selected' : '' }}>
                                {{ $item->code }} - {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All Projects --</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}"
                                {{ (isset($filters['project_id']) && $filters['project_id'] == $project->id) ? 'selected' : '' }}>
                                {{ $project->code }} - {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label form-label-sm">Category</label>
                    <select name="material_category" class="form-select form-select-sm">
                        <option value="">-- All --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}"
                                {{ (isset($filters['material_category']) && $filters['material_category'] == $cat) ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $cat)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label form-label-sm">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- All --</option>
                        @foreach($statuses as $st)
                            <option value="{{ $st }}"
                                {{ (isset($filters['status']) && $filters['status'] == $st) ? 'selected' : '' }}>
                                {{ strtoupper($st) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label form-label-sm">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           value="{{ $filters['search'] ?? '' }}"
                           placeholder="Item / plate / heat / grade">
                </div>

                <div class="col-md-2">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" value="1" id="only_available"
                               name="only_available"
                            {{ !empty($filters['only_available']) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="only_available">
                            Only available &gt; 0
                        </label>
                    </div>
                </div>

                <div class="col-md-2 text-end">
                    <button type="submit" class="btn btn-sm btn-primary">
                        Filter
                    </button>
                    <a href="{{ route('store-stock-summary.index') }}" class="btn btn-sm btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 24%">Item</th>
                        <th style="width: 20%">Project</th>
                        <th style="width: 10%">Category</th>
                        <th style="width: 10%">Grade</th>
                        <th style="width: 9%">Total Pcs</th>
                        <th style="width: 9%">Avail Pcs</th>
                        <th style="width: 9%">Total Wt (kg)</th>
                        <th style="width: 9%">Avail Wt (kg)</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>
                                @if($row->item_code || $row->item_name)
                                    {{ $row->item_code ?? '' }}<br>
                                    <span class="small text-muted">{{ $row->item_name ?? '' }}</span>
                                @else
                                    Item #{{ $row->item_id }}
                                @endif
                            </td>
                            <td>
                                @if($row->project_code || $row->project_name)
                                    {{ $row->project_code ?? '' }}<br>
                                    <span class="small text-muted">{{ $row->project_name ?? '' }}</span>
                                @else
                                    <span class="text-muted small">No Project</span>
                                @endif
                            </td>
                            <td>
                                {{ ucfirst(str_replace('_', ' ', $row->material_category ?? '')) }}
                            </td>
                            <td>{{ $row->grade ?? '-' }}</td>
                            <td>{{ (int) $row->qty_pcs_total }}</td>
                            <td>{{ (int) $row->qty_pcs_available }}</td>
                            <td>{{ number_format($row->weight_kg_total, 3) }}</td>
                            <td>{{ number_format($row->weight_kg_available, 3) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">
                                No summary rows found for selected filters.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($rows->hasPages())
            <div class="card-footer pb-0">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
@endsection
