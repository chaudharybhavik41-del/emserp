@extends('layouts.erp')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">BOM Requirements - {{ $bom->bom_number }}</h4>
        <small class="text-muted">
            Project: {{ $project->code }} - {{ $project->name }}
        </small>
    </div>
    <div class="text-end">
        <a href="{{ route('projects.boms.index', $project) }}" class="btn btn-outline-secondary btn-sm">
            Back to BOM List
        </a>
        <a href="{{ route('projects.boms.show', [$project, $bom]) }}" class="btn btn-outline-primary btn-sm">
            View BOM Items
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row mb-1">
            <div class="col-md-3">
                <strong>Version:</strong>
                {{ $bom->version }}
            </div>
            <div class="col-md-3">
                <strong>Status:</strong>
                {{ $bom->status?->name ?? $bom->status?->value ?? '-' }}
            </div>
            <div class="col-md-3">
                <strong>Total BOM Weight:</strong>
                {{ number_format((float) ($bom->total_weight ?? 0), 3) }} kg
            </div>
            <div class="col-md-3">
                <strong>Finalized On:</strong>
                @if($bom->finalized_date)
                    {{ $bom->finalized_date->format('d-M-Y') }}
                @else
                    &mdash;
                @endif
            </div>
        </div>

        @if(!empty($bom->metadata['remarks']))
            <div class="row">
                <div class="col-md-12">
                    <strong>Remarks:</strong>
                    {{ $bom->metadata['remarks'] }}
                </div>
            </div>
        @endif
    </div>
</div>

{{-- 1. Raw material requirements (plates + sections) --}}
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Raw Material Requirements (Plates &amp; Sections)</h5>
        <small class="text-muted">
            Grouped by grade and thickness / section. These are the inputs to the material planning wizard.
        </small>
    </div>
    <div class="card-body p-0">
        @if(empty($rawRequirements))
            <p class="text-muted p-3 mb-0">
                No raw material leaf items (steel plates / sections) found in this BOM.
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th style="width: 10%;">Type</th>
                            <th style="width: 14%;">Grade</th>
                            <th style="width: 12%;">Thickness (mm)</th>
                            <th style="width: 18%;">Section</th>
                            <th style="width: 8%;">Lines</th>
                            <th style="width: 14%;">Total Qty</th>
                            <th style="width: 10%;">Total Length (m)</th>
                            <th style="width: 10%;">Total Weight (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rawRequirements as $row)
                            @php
                                $lengthM = $row['total_length_mm'] > 0
                                    ? $row['total_length_mm'] / 1000
                                    : null;
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    @if($row['category'] === 'plate')
                                        Plate
                                    @elseif($row['category'] === 'section')
                                        Section
                                    @else
                                        {{ ucfirst($row['category'] ?? '-') }}
                                    @endif
                                </td>
                                <td>{{ $row['grade'] ?? '-' }}</td>
                                <td>
                                    @if(!empty($row['thickness_mm']))
                                        {{ number_format($row['thickness_mm'], 1) }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td>{{ $row['section'] ?? '&mdash;' }}</td>
                                <td>{{ $row['lines'] }}</td>
                                <td>
                                    {{ number_format($row['total_qty'], 3) }}
                                    @if(!empty($row['uom']))
                                        <small class="text-muted">{{ $row['uom'] }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($lengthM !== null)
                                        {{ number_format($lengthM, 3) }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td>{{ number_format($row['total_weight'], 3) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- 2. Bought-out items --}}
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Bought-out Item Requirements</h5>
        <small class="text-muted">
            Grouped by item. This list is what will eventually go towards Purchase / Store.
        </small>
    </div>
    <div class="card-body p-0">
        @if(empty($boughtOutRequirements))
            <p class="text-muted p-3 mb-0">
                No bought-out leaf items found in this BOM.
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th style="width: 18%;">Item Code</th>
                            <th style="width: 26%;">Item Name / Description</th>
                            <th style="width: 12%;">Grade</th>
                            <th style="width: 8%;">Lines</th>
                            <th style="width: 14%;">Total Qty</th>
                            <th style="width: 10%;">Total Weight (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($boughtOutRequirements as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $row['item_code'] ?? '-' }}</td>
                                <td>
                                    {{ $row['item_name'] ?? $row['description'] ?? '-' }}
                                </td>
                                <td>{{ $row['grade'] ?? '-' }}</td>
                                <td>{{ $row['lines'] }}</td>
                                <td>
                                    {{ number_format($row['total_qty'], 3) }}
                                    @if(!empty($row['uom']))
                                        <small class="text-muted">{{ $row['uom'] }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($row['total_weight'] > 0)
                                        {{ number_format($row['total_weight'], 3) }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- 3. Consumables --}}
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Consumable Requirements</h5>
        <small class="text-muted">
            Grouped by item. This list will be used by store to check stock vs minimum stock.
        </small>
    </div>
    <div class="card-body p-0">
        @if(empty($consumableRequirements))
            <p class="text-muted p-3 mb-0">
                No consumable leaf items found in this BOM.
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th style="width: 18%;">Item Code</th>
                            <th style="width: 26%;">Item Name / Description</th>
                            <th style="width: 12%;">Grade</th>
                            <th style="width: 8%;">Lines</th>
                            <th style="width: 14%;">Total Qty</th>
                            <th style="width: 10%;">Total Weight (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($consumableRequirements as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $row['item_code'] ?? '-' }}</td>
                                <td>
                                    {{ $row['item_name'] ?? $row['description'] ?? '-' }}
                                </td>
                                <td>{{ $row['grade'] ?? '-' }}</td>
                                <td>{{ $row['lines'] }}</td>
                                <td>
                                    {{ number_format($row['total_qty'], 3) }}
                                    @if(!empty($row['uom']))
                                        <small class="text-muted">{{ $row['uom'] }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($row['total_weight'] > 0)
                                        {{ number_format($row['total_weight'], 3) }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
