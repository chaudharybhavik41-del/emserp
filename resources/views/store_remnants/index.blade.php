@extends('layouts.erp')

@section('title', 'Remnant Library')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Remnant Library</h1>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('store-remnants.index') }}" class="row g-2 align-items-end">
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
                    <label class="form-label form-label-sm">Grade</label>
                    <input type="text" name="grade" class="form-control form-control-sm"
                           value="{{ $filters['grade'] ?? '' }}"
                           placeholder="e.g. E250BR">
                </div>

                <div class="col-md-2">
                    <label class="form-label form-label-sm">Thickness (mm)</label>
                    <select name="thickness_mm" class="form-select form-select-sm">
                        <option value="">-- Any --</option>
                        @foreach($thicknesses as $thk)
                            <option value="{{ $thk }}"
                                {{ (isset($filters['thickness_mm']) && $filters['thickness_mm'] == $thk) ? 'selected' : '' }}>
                                {{ $thk }}
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
                    <label class="form-label form-label-sm">Client Material</label>
                    <select name="is_client_material" class="form-select form-select-sm">
                        <option value="">-- Both --</option>
                        <option value="1" {{ (isset($filters['is_client_material']) && $filters['is_client_material'] === '1') ? 'selected' : '' }}>Client</option>
                        <option value="0" {{ (isset($filters['is_client_material']) && $filters['is_client_material'] === '0') ? 'selected' : '' }}>Own</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           value="{{ $filters['search'] ?? '' }}"
                           placeholder="Item / plate / heat / location / grade">
                </div>

                <div class="col-md-2">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" value="1" id="include_zero_available"
                               name="include_zero_available"
                            {{ !empty($filters['include_zero_available']) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="include_zero_available">
                            Include 0 available
                        </label>
                    </div>
                </div>

                <div class="col-md-3 text-end">
                    <button type="submit" class="btn btn-sm btn-primary">
                        Filter
                    </button>
                    <a href="{{ route('store-remnants.index') }}" class="btn btn-sm btn-outline-secondary">
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
                        <th style="width: 18%">Item</th>
                        <th style="width: 12%">Category</th>
                        <th style="width: 16%">Size / Section</th>
                        <th style="width: 10%">Grade</th>
                        <th style="width: 12%">Plate / Heat</th>
                        <th style="width: 12%">Project</th>
                        <th style="width: 8%">Avail Pcs</th>
                        <th style="width: 8%">Avail Wt (kg)</th>
                        <th style="width: 10%">Location</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($remnants as $stock)
                        <tr>
                            <td>
                                @if($stock->item)
                                    {{ $stock->item->code }}<br>
                                    <span class="small text-muted">{{ $stock->item->name }}</span>
                                @else
                                    Item #{{ $stock->item_id }}
                                @endif
                                <br>
                                @if($stock->is_client_material)
                                    <span class="badge bg-warning text-dark mt-1">Client</span>
                                @else
                                    <span class="badge bg-info text-dark mt-1">Own</span>
                                @endif
                            </td>
                            <td>
                                {{ ucfirst(str_replace('_', ' ', $stock->material_category ?? '')) }}
                                <br>
                                <span class="badge bg-light text-muted border small">
                                    {{ strtoupper($stock->status ?? '') }}
                                </span>
                            </td>
                            <td>
                                @if($stock->material_category === 'steel_plate')
                                    T{{ $stock->thickness_mm }} x W{{ $stock->width_mm }} x L{{ $stock->length_mm }}
                                @elseif($stock->material_category === 'steel_section')
                                    {{ $stock->section_profile }}
                                    @if($stock->length_mm)
                                        <br><span class="small text-muted">{{ $stock->length_mm }} mm</span>
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $stock->grade ?? '-' }}</td>
                            <td>
                                @if($stock->plate_number)
                                    Plate: {{ $stock->plate_number }}<br>
                                @endif
                                @if($stock->heat_number)
                                    <span class="small text-muted">Heat: {{ $stock->heat_number }}</span><br>
                                @endif
                                @if($stock->mtc_number)
                                    <span class="small text-muted">MTC: {{ $stock->mtc_number }}</span>
                                @endif
                            </td>
                            <td>
                                @if($stock->project)
                                    {{ $stock->project->code }}<br>
                                    <span class="small text-muted">{{ $stock->project->name }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ (int)$stock->qty_pcs_available }}</td>
                            <td>
                                @if(!is_null($stock->weight_kg_available))
                                    {{ number_format($stock->weight_kg_available, 3) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $stock->location ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-3">
                                No remnants found for selected filters.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($remnants->hasPages())
            <div class="card-footer pb-0">
                {{ $remnants->links() }}
            </div>
        @endif
    </div>
@endsection
