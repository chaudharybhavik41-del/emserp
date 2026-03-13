@extends('layouts.erp')

@section('title', 'Production V2 Cut Batch')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">CB-{{ $cutBatch->id }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.cut-batches.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        <a href="{{ route('projects.production-v2.fitups.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">Create Fit-up</a>
    </div>
@endsection

@section('content')
    @php
        $cutPlanCategory = $cutBatch->cuttingPlan?->allocations
            ?->pluck('partDefinition.material_category')
            ?->filter()
            ?->map(fn ($value) => strtolower((string) $value))
            ?->first();
        $isSectionBatch = $cutPlanCategory === 'steel_section';
        $motherStockTitle = $isSectionBatch ? 'Mother Section' : 'Mother Stock';
        $sourceRefTitle = $isSectionBatch ? 'Section Ref' : 'Plate Ref';
        $motherStockLabel = $cutBatch->motherStock?->plate_number
            ?: ($cutBatch->motherStock?->section_profile ?: ($cutBatch->motherStock ? 'Stock #' . $cutBatch->motherStock->id : '-'));
    @endphp
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Cut Date</div><div>{{ $cutBatch->cut_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Cutting Plan</div><div>{{ $cutBatch->cuttingPlan?->plan_number ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">{{ $motherStockTitle }}</div><div>{{ $motherStockLabel }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Heat No</div><div>{{ $cutBatch->heat_number_snapshot ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Status</div><div>{{ $cutBatch->status }}</div></div>
                <div class="col-12 col-md-3">
                    <div class="small text-body-secondary">Daily DPR</div>
                    <div>
                        @if($cutBatch->dpr)
                            <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $cutBatch->dpr->id]) }}">DPR-{{ $cutBatch->dpr->id }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="small text-body-secondary">Store Issue</div>
                    <div>
                        @if($cutBatch->storeIssue)
                            <a href="{{ route('store-issues.show', ['store_issue' => $cutBatch->storeIssue->id]) }}">{{ $cutBatch->storeIssue->issue_number ?: ('ISS#' . $cutBatch->storeIssue->id) }}</a>
                        @elseif(in_array($cutBatch->motherStock?->material_category, ['steel_plate', 'steel_section'], true))
                            Not applicable for raw material
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $cutBatch->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Generated WIP</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Piece / Lot</th>
                            <th>Part</th>
                            <th class="text-end">Qty</th>
                            <th>{{ $sourceRefTitle }}</th>
                            <th>Heat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($cutBatch->wipItems as $row)
                        <tr>
                            <td>{{ $row->piece_no ?: ($row->lot_no ?: '-') }}</td>
                            <td>{{ $row->partDefinition?->part_code }}<div class="small text-body-secondary">{{ $row->partDefinition?->part_name }}</div></td>
                            <td class="text-end">{{ number_format((float) $row->qty, 3) }} {{ $row->uom?->code }}</td>
                            <td>{{ $row->plate_number ?: ($row->motherStock?->section_profile ?? '-') }}</td>
                            <td>{{ $row->heat_number ?: '-' }}</td>
                            <td>{{ $row->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No WIP generated.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">Remnants / Scrap</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ $isSectionBatch ? 'Profile / Width' : 'Width' }}</th>
                            <th>Length</th>
                            <th>Weight</th>
                            <th>Usable</th>
                            <th>Status</th>
                            <th>Remnant Stock</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($cutBatch->remnants as $row)
                        <tr>
                            <td>{{ $row->width_mm ?: '-' }}</td>
                            <td>{{ $row->length_mm ?: '-' }}</td>
                            <td>{{ $row->weight_kg ? number_format((float) $row->weight_kg, 3) : '-' }}</td>
                            <td>{{ $row->is_usable ? 'Yes' : 'No' }}</td>
                            <td>{{ $row->status }}</td>
                            <td>
                                @if($row->remnantStock)
                                    {{ $row->remnantStock->plate_number ?: ($row->remnantStock->section_profile ?: ('Stock #' . $row->remnantStock->id)) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $row->remarks ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No remnants recorded.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
