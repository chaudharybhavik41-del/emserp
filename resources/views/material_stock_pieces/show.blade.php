@extends('layouts.erp')

@section('content')
    <div class="container-fluid">
        <h1 class="mb-3">
            Stock Piece #{{ $piece->id }}
            @if($piece->isRemnant())
                <span class="badge bg-info ms-2">Remnant</span>
            @endif
        </h1>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        Basic Info
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Category</dt>
                            <dd class="col-sm-8">
                                @if($piece->isPlate())
                                    Plate
                                @elseif($piece->isSection())
                                    Section
                                @else
                                    {{ $piece->material_category->value ?? $piece->material_category }}
                                @endif
                            </dd>

                            <dt class="col-sm-4">Item</dt>
                            <dd class="col-sm-8">
                                @if($piece->item)
                                    {{ $piece->item->code ?? $piece->item->id }} - {{ $piece->item->name ?? '' }}<br>
                                    <small class="text-muted">Grade: {{ $piece->item->grade ?? '-' }}</small>
                                @else
                                    -
                                @endif
                            </dd>

                            <dt class="col-sm-4">Size</dt>
                            <dd class="col-sm-8">
                                @if($piece->isPlate())
                                    {{ $piece->thickness_mm ? $piece->thickness_mm . ' thk' : '' }}
                                    {{ $piece->width_mm ? ' x ' . $piece->width_mm : '' }}
                                    {{ $piece->length_mm ? ' x ' . $piece->length_mm : '' }} mm
                                @elseif($piece->isSection())
                                    {{ $piece->section_profile ?? '-' }}
                                    {{ $piece->length_mm ? '(' . $piece->length_mm . ' mm)' : '' }}
                                @else
                                    -
                                @endif
                            </dd>

                            <dt class="col-sm-4">Weight</dt>
                            <dd class="col-sm-8">
                                {{ $piece->weight_kg !== null ? number_format($piece->weight_kg, 3) . ' kg' : '-' }}
                            </dd>

                            <dt class="col-sm-4">Location</dt>
                            <dd class="col-sm-8">{{ $piece->location ?? '-' }}</dd>

                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">
                                @php($status = $piece->status)
                                @if($status)
                                    <span class="badge bg-{{ $status->value === 'available' ? 'success' : ($status->value === 'reserved' ? 'warning' : 'secondary') }}">
                                        {{ $status->label() }}
                                    </span>
                                @else
                                    -
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        Traceability
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Plate No.</dt>
                            <dd class="col-sm-8">{{ $piece->plate_number ?? '-' }}</dd>

                            <dt class="col-sm-4">Heat No.</dt>
                            <dd class="col-sm-8">{{ $piece->heat_number ?? '-' }}</dd>

                            <dt class="col-sm-4">MTC No.</dt>
                            <dd class="col-sm-8">{{ $piece->mtc_number ?? '-' }}</dd>

                            <dt class="col-sm-4">Origin Project</dt>
                            <dd class="col-sm-8">
                                @if($piece->originProject)
                                    {{ $piece->originProject->code ?? $piece->originProject->name }}
                                @else
                                    -
                                @endif
                            </dd>

                            <dt class="col-sm-4">Origin BOM</dt>
                            <dd class="col-sm-8">
                                @if($piece->originBom)
                                    {{ $piece->originBom->bom_number ?? ('BOM #' . $piece->originBom->id) }}
                                @else
                                    -
                                @endif
                            </dd>

                            <dt class="col-sm-4">Mother Piece</dt>
                            <dd class="col-sm-8">
                                @if($piece->mother)
                                    <a href="{{ route('material-stock-pieces.show', $piece->mother) }}">
                                        #{{ $piece->mother->id }} ({{ $piece->mother->plate_number ?? 'no plate no.' }})
                                    </a>
                                @else
                                    Original piece
                                @endif
                            </dd>

                            <dt class="col-sm-4">Reserved For</dt>
                            <dd class="col-sm-8">
                                @if($piece->reservedForProject)
                                    Project: {{ $piece->reservedForProject->code ?? $piece->reservedForProject->name }}<br>
                                @endif
                                @if($piece->reservedForBom)
                                    BOM: {{ $piece->reservedForBom->bom_number ?? $piece->reservedForBom->id }}
                                @endif
                                @if(! $piece->reservedForProject && ! $piece->reservedForBom)
                                    -
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        Remnants from this Piece
                    </div>
                    <div class="card-body p-0">
                        @if($piece->remnants->isEmpty())
                            <p class="text-muted p-3 mb-0">No remnants recorded for this piece.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Size</th>
                                        <th>Weight (kg)</th>
                                        <th>Plate No.</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($piece->remnants as $remnant)
                                        <tr>
                                            <td>{{ $remnant->id }}</td>
                                            <td>
                                                @if($remnant->isPlate())
                                                    {{ $remnant->thickness_mm }} thk x {{ $remnant->width_mm }} x {{ $remnant->length_mm }} mm
                                                @elseif($remnant->isSection())
                                                    {{ $remnant->section_profile }} ({{ $remnant->length_mm }} mm)
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $remnant->weight_kg !== null ? number_format($remnant->weight_kg, 3) : '-' }}</td>
                                            <td>{{ $remnant->plate_number ?? '-' }}</td>
                                            <td>
                                                @php($rStatus = $remnant->status)
                                                @if($rStatus)
                                                    <span class="badge bg-{{ $rStatus->value === 'available' ? 'success' : ($rStatus->value === 'reserved' ? 'warning' : 'secondary') }}">
                                                        {{ $rStatus->label() }}
                                                    </span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('material-stock-pieces.show', $remnant) }}"
                                                   class="btn btn-sm btn-outline-secondary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                @if($piece->remarks)
                    <div class="card mb-3">
                        <div class="card-header">
                            Remarks
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{{ $piece->remarks }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
