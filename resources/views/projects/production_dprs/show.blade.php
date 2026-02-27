
@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-journal-text"></i> DPR #{{ $dpr->id }}</h2>
            <div class="text-muted small">
                Date: {{ $dpr->dpr_date->format('Y-m-d') }} |
                Plan: {{ $dpr->plan?->plan_number }} |
                Activity: {{ $dpr->activity?->name }}
            </div>
        </div>
        <a href="{{ route('projects.production-dprs.index', $project) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        @php
            $isCutting = str_contains(strtoupper($dpr->activity?->code ?? ''), 'CUT')
                || str_contains(strtoupper($dpr->activity?->name ?? ''), 'CUT');
            $isFitup = (bool) ($dpr->activity?->is_fitupp ?? false);
        @endphp

        @if($isCutting || $isFitup)
            <a class="btn btn-outline-primary"
               href="{{ route('projects.production-dprs.traceability.edit', [$project, $dpr]) }}">
                <i class="bi bi-upc-scan"></i> Traceability Capture
            </a>
        @endif

        @if($dpr->status === 'draft')
            @can('production.dpr.submit')
                <form method="POST" action="{{ route('projects.production-dprs.submit', [$project, $dpr]) }}"
                      onsubmit="return confirm('Submit DPR?');">
                    @csrf
                    <button class="btn btn-primary"><i class="bi bi-send"></i> Submit</button>
                </form>
            @endcan
        @endif

        @if($dpr->status === 'submitted')
            @can('production.dpr.approve')
                <form method="POST" action="{{ route('projects.production-dprs.approve', [$project, $dpr]) }}"
                      onsubmit="return confirm('Approve DPR?');">
                    @csrf
                    <button class="btn btn-success"><i class="bi bi-check2-circle"></i> Approve</button>
                </form>
            @endcan
        @endif

        <span class="ms-auto">
            @if($dpr->status === 'approved')
                <span class="badge text-bg-success">Approved</span>
            @elseif($dpr->status === 'submitted')
                <span class="badge text-bg-primary">Submitted</span>
            @else
                <span class="badge text-bg-secondary">Draft</span>
            @endif
        </span>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3"><div class="text-muted small">Shift</div><div class="fw-semibold">{{ $dpr->shift ?? '—' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Contractor</div><div class="fw-semibold">{{ $dpr->contractor?->name ?? '—' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Worker</div><div class="fw-semibold">{{ $dpr->worker?->name ?? '—' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Machine ID</div><div class="fw-semibold">{{ $dpr->machine_id ?? '—' }}</div></div>
            </div>
            <hr>
            <div class="text-muted small">Geo</div>
            <div class="fw-semibold">
                @if($dpr->geo_latitude && $dpr->geo_longitude)
                    {{ $dpr->geo_latitude }}, {{ $dpr->geo_longitude }} (±{{ $dpr->geo_accuracy_m }}m)
                @else
                    —
                @endif
            
            <div class="text-muted small mt-1">
                Status: <span class="badge bg-light text-dark">{{ $dpr->geo_status ?? '—' }}</span>
                @if(!empty($dpr->geo_override_reason))
                    <div class="small text-muted mt-1">Override: {{ $dpr->geo_override_reason }}</div>
                @endif
            </div>
</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Assembly</th>
                        <th>Qty</th>
                        <th>Cut (m)</th>
                        <th>Weld (m)</th>
                        <th>Area (m²)</th>
                        <th>QC</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalCutM = 0;
                        $totalWeldM = 0;
                        $totalAreaM2 = 0;
                    @endphp
                    @foreach($dpr->lines as $ln)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $ln->planItem?->item_code }}</div>
                                <div class="text-muted small">{{ $ln->planItemActivity?->activity?->name }}</div>
                            </td>
                            <td>{{ $ln->planItem?->assembly_mark ?? '—' }}</td>
                            <td>{{ $ln->qty }} {{ $ln->qtyUom?->code ?? '' }}</td>
                            @php
                                $pi = $ln->planItem;
                                $qty = (float) ($ln->qty ?? 0);
                                $cut = ($pi && $pi->unit_cut_length_m !== null) ? $qty * (float) $pi->unit_cut_length_m : null;
                                $weld = ($pi && $pi->unit_weld_length_m !== null) ? $qty * (float) $pi->unit_weld_length_m : null;
                                $area = ($pi && $pi->unit_area_m2 !== null) ? $qty * (float) $pi->unit_area_m2 : null;

                                $totalCutM += $cut ?? 0;
                                $totalWeldM += $weld ?? 0;
                                $totalAreaM2 += $area ?? 0;
                            @endphp
                            <td>{{ $cut !== null ? number_format($cut, 3) : '—' }}</td>
                            <td>{{ $weld !== null ? number_format($weld, 3) : '—' }}</td>
                            <td>{{ $area !== null ? number_format($area, 3) : '—' }}</td>
                            <td>
                                @php $pia = $ln->planItemActivity; @endphp
                                @if($pia && $pia->qc_status === 'pending')
                                    <span class="badge text-bg-warning">QC Pending</span>
                                @elseif($pia && $pia->qc_status === 'passed')
                                    <span class="badge text-bg-success">QC Passed</span>
                                @elseif($pia && $pia->qc_status === 'failed')
                                    <span class="badge text-bg-danger">QC Failed</span>
                                @else
                                    <span class="badge text-bg-secondary">N/A</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $ln->remarks ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="3" class="text-end fw-semibold">Total</td>
                        <td class="fw-semibold">{{ number_format($totalCutM, 3) }}</td>
                        <td class="fw-semibold">{{ number_format($totalWeldM, 3) }}</td>
                        <td class="fw-semibold">{{ number_format($totalAreaM2, 3) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
