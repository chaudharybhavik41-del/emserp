@extends('layouts.erp')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="mb-1">Plate Purchase List</h1>
                <div class="text-muted">
                    Project: {{ $project->code ?? $project->name }} |
                    BOM: {{ $bom->bom_number ?? ('BOM #' . $bom->id) }}
                </div>
                <div class="text-muted small">
                    This list is driven by Cutting Plans and planned plates
                    (MaterialStockPieces with source_type = planned).
                </div>
            </div>
            <div class="d-print-none">
                <a href="{{ route('projects.boms.show', [$project, $bom]) }}"
                   class="btn btn-outline-secondary">
                    Back to BOM
                </a>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    Print
                </button>
            </div>
        </div>

        @if($groupedPlates->isEmpty())
            <div class="alert alert-info">
                No planned plates found for this BOM yet.
                Create plates in the <strong>Cutting Plan / Nesting</strong> screen to see them here.
            </div>
        @else
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            Summary
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-6">Total planned plates</dt>
                                <dd class="col-6">{{ $totalPlates }}</dd>

                                <dt class="col-6">Total planned weight</dt>
                                <dd class="col-6">{{ number_format($totalWeightPlanned, 3) }} kg</dd>
                            </dl>
                            <p class="small text-muted mt-2 mb-0">
                                This includes only <strong>planned</strong> plates reserved for this project &amp; BOM.
                                Remnant plates reused from stock are not part of purchase quantity.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    Plates grouped by Grade / Thickness / Size
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th style="width: 14%">Grade</th>
                                <th style="width: 10%">Item</th>
                                <th style="width: 12%">Thickness</th>
                                <th style="width: 20%">Size (mm)</th>
                                <th style="width: 10%">Qty</th>
                                <th style="width: 16%">Total Weight (kg)</th>
                                <th style="width: 18%">Notes</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($groupedPlates as $group)
                                <tr>
                                    <td>
                                        {{ $group['grade'] ?? '-' }}
                                    </td>
                                    <td>
                                        @if($group['item_code'])
                                            {{ $group['item_code'] }}<br>
                                            <small class="text-muted">{{ $group['item_name'] }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $group['thickness_mm'] }} mm</td>
                                    <td>
                                        {{ $group['width_mm'] }} x {{ $group['length_mm'] }} mm
                                    </td>
                                    <td>{{ $group['count'] }}</td>
                                    <td>{{ number_format($group['total_weight_kg'], 3) }}</td>
                                    <td>
                                        <small class="text-muted">
                                            Planned from Cutting Plan / Material Planning.
                                        </small>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="4" class="text-end">
                                    Total
                                </td>
                                <td>{{ $totalPlates }}</td>
                                <td>{{ number_format($totalWeightPlanned, 3) }}</td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
