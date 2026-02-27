@extends('layouts.erp')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">Material Stock Pieces / Remnant Library</h1>
            <a href="{{ route('material-stock-pieces.create') }}" class="btn btn-sm btn-primary">
                Add Stock Piece
            </a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('material-stock-pieces.index') }}" class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select name="material_category" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach($materialCategories as $value => $label)
                                <option value="{{ $value }}" @selected(request('material_category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Grade</label>
                        <input type="text"
                               name="grade"
                               value="{{ request('grade') }}"
                               class="form-control form-control-sm"
                               placeholder="E250, E350, etc.">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Thickness (mm)</label>
                        <input type="number"
                               name="thickness_mm"
                               value="{{ request('thickness_mm') }}"
                               class="form-control form-control-sm">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Heat No.</label>
                        <input type="text"
                               name="heat_number"
                               value="{{ request('heat_number') }}"
                               class="form-control form-control-sm">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Plate No.</label>
                        <input type="text"
                               name="plate_number"
                               value="{{ request('plate_number') }}"
                               class="form-control form-control-sm">
                    </div>

                    <div class="col-12 text-end mt-2">
                        <button type="submit" class="btn btn-sm btn-primary">
                            Filter
                        </button>
                        <a href="{{ route('material-stock-pieces.index') }}" class="btn btn-sm btn-outline-secondary">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Category</th>
                            <th>Item</th>
                            <th>Grade</th>
                            <th>Thickness</th>
                            <th>Width</th>
                            <th>Length</th>
                            <th>Weight (kg)</th>
                            <th>Plate No.</th>
                            <th>Heat No.</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Origin</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($pieces as $piece)
                            <tr>
                                <td>{{ $piece->id }}</td>
                                <td>
                                    @if($piece->isPlate())
                                        Plate
                                    @elseif($piece->isSection())
                                        Section
                                    @else
                                        {{ $piece->material_category->value ?? $piece->material_category }}
                                    @endif
                                    @if($piece->isRemnant())
                                        <span class="badge bg-info ms-1">Remnant</span>
                                    @endif
                                </td>
                                <td>
                                    @if($piece->item)
                                        {{ $piece->item->code ?? $piece->item->id }}<br>
                                        <small class="text-muted">{{ $piece->item->name ?? '' }}</small>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $piece->item->grade ?? '-' }}</td>
                                <td>{{ $piece->thickness_mm ? $piece->thickness_mm . ' mm' : '-' }}</td>
                                <td>{{ $piece->width_mm ? $piece->width_mm . ' mm' : '-' }}</td>
                                <td>{{ $piece->length_mm ? $piece->length_mm . ' mm' : '-' }}</td>
                                <td>{{ $piece->weight_kg !== null ? number_format($piece->weight_kg, 3) : '-' }}</td>
                                <td>{{ $piece->plate_number ?? '-' }}</td>
                                <td>{{ $piece->heat_number ?? '-' }}</td>
                                <td>
                                    @php($status = $piece->status)
                                    @if($status)
                                        <span class="badge bg-{{ $status->value === 'available' ? 'success' : ($status->value === 'reserved' ? 'warning' : 'secondary') }}">
                                            {{ $status->label() }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $piece->location ?? '-' }}</td>
                                <td>
                                    @if($piece->originProject)
                                        <small>Proj: {{ $piece->originProject->code ?? $piece->originProject->name }}</small><br>
                                    @endif
                                    @if($piece->originBom)
                                        <small>BOM: {{ $piece->originBom->bom_number ?? $piece->originBom->id }}</small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('material-stock-pieces.show', $piece) }}"
                                       class="btn btn-sm btn-outline-secondary">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center text-muted py-4">
                                    No material stock pieces found.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-2 px-3">
                    {{ $pieces->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
