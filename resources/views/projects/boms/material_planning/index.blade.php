@extends('layouts.erp')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="mb-1">Material Planning Wizard</h1>
                <div class="text-muted">
                    Project: {{ $project->code ?? $project->name }} |
                    BOM: {{ $bom->bom_number ?? ('BOM #' . $bom->id) }}
                </div>
            </div>
            <div>
                <a href="{{ route('projects.boms.show', [$project, $bom]) }}" class="btn btn-outline-secondary">
                    Back to BOM
                </a>
            </div>
        </div>

        <div class="alert alert-info">
            <strong>How to use this page:</strong>
            <ul class="mb-0">
                <li>Each row below is a grouped raw material requirement (plate or section) from this BOM.</li>
                <li>
                    For <strong>plates</strong>:
                    use <strong>Cutting Plan / Nesting</strong> as the main working screen to define
                    new plates to purchase and reuse remnant plates.
                </li>
                <li>
                    For <strong>sections</strong>:
                    use <strong>Plan / Allocate</strong> to reserve stock and plan new lengths.
                </li>
                <li>
                    This summary shows required vs reserved weight; it reads planned plates &amp; reservations
                    from the same stock library used by Cutting Plans.
                </li>
            </ul>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 16%">Category</th>
                            <th style="width: 12%">Grade</th>
                            <th style="width: 12%">Thickness / Section</th>
                            <th style="width: 8%">Lines</th>
                            <th style="width: 12%">Total Qty</th>
                            <th style="width: 16%">Total Weight (kg)</th>
                            <th style="width: 16%">Reserved (kg)</th>
                            <th style="width: 16%">Remaining (kg)</th>
                            <th style="width: 12%"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($rawRequirements as $group)
                            @php
                                $totalWeight    = (float) ($group['total_weight'] ?? 0);
                                $reservedWeight = (float) ($group['reserved_weight_kg'] ?? 0);
                                $remaining      = max(0, $totalWeight - $reservedWeight);
                            @endphp
                            <tr>
                                <td>
                                    @if($group['category'] === 'plate')
                                        Plate
                                    @elseif($group['category'] === 'section')
                                        Section
                                    @else
                                        {{ ucfirst($group['category'] ?? '-') }}
                                    @endif
                                </td>
                                <td>{{ $group['grade'] ?? '-' }}</td>
                                <td>
                                    @if($group['category'] === 'plate')
                                        {{ $group['thickness_mm'] !== null ? $group['thickness_mm'] . ' mm' : '-' }}
                                    @elseif($group['category'] === 'section')
                                        {{ $group['section'] ?? '-' }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $group['lines'] }}</td>
                                <td>
                                    {{ number_format($group['total_qty'] ?? 0, 3) }}
                                    @if(! empty($group['uom']))
                                        <small class="text-muted">{{ $group['uom'] }}</small>
                                    @endif
                                </td>
                                <td>{{ number_format($totalWeight, 3) }}</td>
                                <td>
                                    {{ number_format($reservedWeight, 3) }}
                                    @if(($group['reserved_pieces_count'] ?? 0) > 0)
                                        <small class="text-muted d-block">
                                            from {{ $group['reserved_pieces_count'] }} piece(s)
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    @if($reservedWeight > $totalWeight)
                                        <span class="text-danger">
                                            {{ number_format($remaining, 3) }} (over-allocated)
                                        </span>
                                    @else
                                        {{ number_format($remaining, 3) }}
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-column gap-1 align-items-end">
                                        @if($group['category'] === 'plate' && $group['thickness_mm'])
                                            <a href="{{ route('projects.boms.cutting-plans.create', [
                                                $project,
                                                $bom,
                                                'grade'        => $group['grade'],
                                                'thickness_mm' => $group['thickness_mm'],
                                            ]) }}" class="btn btn-sm btn-primary">
                                                Cutting Plan / Nesting
                                            </a>
                                            <a href="{{ route('projects.boms.material-planning.select-stock', [
                                                $project,
                                                $bom,
                                                'group_category'  => $group['category'],
                                                'grade'           => $group['grade'],
                                                'thickness_mm'    => $group['thickness_mm'],
                                                'section_profile' => $group['section'],
                                            ]) }}" class="btn btn-sm btn-outline-secondary">
                                                Stock overview
                                            </a>
                                        @else
                                            <a href="{{ route('projects.boms.material-planning.select-stock', [
                                                $project,
                                                $bom,
                                                'group_category'  => $group['category'],
                                                'grade'           => $group['grade'],
                                                'thickness_mm'    => $group['thickness_mm'],
                                                'section_profile' => $group['section'],
                                            ]) }}" class="btn btn-sm btn-primary">
                                                Plan / Allocate
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    No raw material requirements found for this BOM.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
