@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-search"></i> Traceability Search</h2>
            <div class="text-muted small">Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}</div>
        </div>
        <a href="{{ route('projects.production-dashboard.index', $project) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('projects.production-traceability.index', $project) }}" class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Plate No / Heat No / MTC No / Piece No / Assembly Mark">
                    <div class="text-muted small mt-1">
                        Tip: try plate number, heat number, MTC number, piece number, or assembly mark.
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search"></i> Search
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('projects.production-traceability.index', $project) }}">
                        Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if($q !== '')
        <div class="row g-3 mb-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="bi bi-box"></i> Stock Matches</span>
                        <span class="badge text-bg-secondary">{{ $stockMatches->count() }}</span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Plate</th>
                                <th>Heat</th>
                                <th class="text-end">Open</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($stockMatches as $s)
                                <tr>
                                    <td>#{{ $s->id }}</td>
                                    <td>{{ $s->plate_number ?? '—' }}</td>
                                    <td>{{ $s->heat_number ?? '—' }}</td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="{{ route('projects.production-traceability.index', $project) }}?q={{ urlencode($q) }}&stock_id={{ $s->id }}">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No matches</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="bi bi-scissors"></i> Piece Matches</span>
                        <span class="badge text-bg-secondary">{{ $pieceMatches->count() }}</span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Piece No</th>
                                <th>Status</th>
                                <th class="text-end">Open</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($pieceMatches as $p)
                                <tr>
                                    <td>#{{ $p->id }}</td>
                                    <td class="fw-semibold">{{ $p->piece_number }}</td>
                                    <td><span class="badge text-bg-{{ $p->status === 'available' ? 'success' : ($p->status === 'consumed' ? 'secondary' : 'dark') }}">{{ ucfirst($p->status) }}</span></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="{{ route('projects.production-traceability.index', $project) }}?q={{ urlencode($q) }}&piece_id={{ $p->id }}">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No matches</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="bi bi-diagram-3"></i> Assembly Matches</span>
                        <span class="badge text-bg-secondary">{{ $assemblyMatches->count() }}</span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Mark</th>
                                <th>Type</th>
                                <th class="text-end">Open</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($assemblyMatches as $a)
                                <tr>
                                    <td>#{{ $a->id }}</td>
                                    <td class="fw-semibold">{{ $a->assembly_mark }}</td>
                                    <td>{{ $a->assembly_type ?? '—' }}</td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="{{ route('projects.production-traceability.index', $project) }}?q={{ urlencode($q) }}&assembly_id={{ $a->id }}">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No matches</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @php
        $hasDetail = $detail['stock'] || $detail['assembly'] || $detail['piece'];
        $dprShowRoute = (\Illuminate\Support\Facades\Route::has('projects.production-dprs.show')) ? 'projects.production-dprs.show' : null;
    @endphp

    @if($hasDetail)
        <div class="card mb-3">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="fw-semibold">
                        <i class="bi bi-upc-scan"></i> Traceability Result
                    </div>
                    <div class="text-muted small">
                        Mode: <span class="fw-semibold">{{ strtoupper($detail['mode'] ?? '') }}</span>
                    </div>
                </div>
            </div>
            <div class="card-body">

                {{-- STOCK SUMMARY --}}
                @if($detail['stock'])
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-semibold mb-2">Stock Item #{{ $detail['stock']->id }}</div>
                                <div class="small text-muted">Item</div>
                                <div>{{ $detail['stock']->item?->code }} — {{ $detail['stock']->item?->name }}</div>
                                <div class="mt-2 small text-muted">Identifiers</div>
                                <div>Plate: <span class="fw-semibold">{{ $detail['stock']->plate_number ?? '—' }}</span></div>
                                <div>Heat: <span class="fw-semibold">{{ $detail['stock']->heat_number ?? '—' }}</span></div>
                                <div>MTC: <span class="fw-semibold">{{ $detail['stock']->mtc_number ?? '—' }}</span></div>
                                <div class="mt-2 small text-muted">Status</div>
                                <div><span class="badge text-bg-secondary">{{ ucfirst($detail['stock']->status ?? 'n/a') }}</span></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-semibold mb-2">Geometry / Qty</div>
                                <div>T: {{ $detail['stock']->thickness_mm ?? '—' }} | W: {{ $detail['stock']->width_mm ?? '—' }} | L: {{ $detail['stock']->length_mm ?? '—' }}</div>
                                <div class="mt-2">Qty pcs: {{ $detail['stock']->qty_pcs_total ?? '—' }} (avail {{ $detail['stock']->qty_pcs_available ?? '—' }})</div>
                                <div>Weight kg: {{ $detail['stock']->weight_kg_total ?? '—' }} (avail {{ $detail['stock']->weight_kg_available ?? '—' }})</div>
                                <div class="mt-2 small text-muted">Source</div>
                                <div>{{ $detail['stock']->source_type ?? '—' }} — {{ $detail['stock']->source_reference ?? '—' }}</div>
                                @if((bool)$detail['stock']->is_client_material)
                                    <div class="mt-2"><span class="badge text-bg-info">Client Material</span></div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <hr class="my-4">

                {{-- PIECES --}}
                <h5 class="mb-2"><i class="bi bi-scissors"></i> Pieces</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Piece No</th>
                            <th>Tag</th>
                            <th>Plate/Heat/MTC</th>
                            <th class="text-end">Wt (kg)</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($detail['pieces'] as $p)
                            <tr>
                                <td class="fw-semibold">{{ $p->piece_number ?? $p->piece_number }}</td>
                                <td>{{ $p->piece_tag ?? '—' }}</td>
                                <td class="small text-muted">
                                    P: {{ $p->plate_number ?? '—' }} | H: {{ $p->heat_number ?? '—' }} | M: {{ $p->mtc_number ?? '—' }}
                                </td>
                                <td class="text-end">{{ $p->weight_kg ?? '—' }}</td>
                                <td><span class="badge text-bg-{{ ($p->status ?? '') === 'available' ? 'success' : (($p->status ?? '') === 'consumed' ? 'secondary' : 'dark') }}">{{ ucfirst($p->status ?? 'n/a') }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No pieces found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- REMNANTS --}}
                <h5 class="mb-2"><i class="bi bi-box-seam"></i> Remnants</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Usable</th>
                            <th>Status</th>
                            <th>Size</th>
                            <th class="text-end">Wt (kg)</th>
                            <th>DPR</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($detail['remnants'] as $r)
                            <tr>
                                <td>#{{ $r->id }}</td>
                                <td>
                                    @if((int)$r->is_usable === 1)
                                        <span class="badge text-bg-success">Yes</span>
                                    @else
                                        <span class="badge text-bg-secondary">No</span>
                                    @endif
                                </td>
                                <td><span class="badge text-bg-{{ $r->status === 'available' ? 'primary' : ($r->status === 'scrap' ? 'dark' : 'secondary') }}">{{ ucfirst($r->status) }}</span></td>
                                <td class="small text-muted">T: {{ $r->thickness_mm ?? '-' }} | W: {{ $r->width_mm ?? '-' }} | L: {{ $r->length_mm ?? '-' }}</td>
                                <td class="text-end">{{ $r->weight_kg ?? '—' }}</td>
                                <td>
                                    @if($r->dpr_id && $dprShowRoute)
                                        <a href="{{ route($dprShowRoute, [$project, $r->dpr_id]) }}" class="btn btn-sm btn-outline-primary">DPR #{{ $r->dpr_id }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No remnants found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- ASSEMBLIES --}}
                <h5 class="mb-2"><i class="bi bi-diagram-3"></i> Assemblies</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Mark</th>
                            <th>Type</th>
                            <th class="text-end">Wt (kg)</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($detail['assemblies'] as $a)
                            <tr>
                                <td class="fw-semibold">{{ $a->assembly_mark ?? $a->assembly_mark }}</td>
                                <td>{{ $a->assembly_type ?? '—' }}</td>
                                <td class="text-end">{{ $a->weight_kg ?? '—' }}</td>
                                <td><span class="badge text-bg-{{ ($a->status ?? '') === 'completed' ? 'success' : 'secondary' }}">{{ ucfirst($a->status ?? 'n/a') }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No assemblies found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- TIMELINE --}}
                <h5 class="mb-2"><i class="bi bi-clock-history"></i> DPR Timeline</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Activity</th>
                            <th>Event</th>
                            <th>DPR</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($detail['dpr_timeline'] as $e)
                            <tr>
                                <td>{{ $e->dpr_date }}</td>
                                <td>{{ $e->activity_name ?? '—' }}</td>
                                <td class="small text-muted">{{ str_replace('_',' ', ucfirst($e->event_type ?? 'event')) }} — {{ $e->ref_code ?? '' }}</td>
                                <td>
                                    @if(($e->dpr_id ?? null) && $dprShowRoute)
                                        <a href="{{ route($dprShowRoute, [$project, $e->dpr_id]) }}" class="btn btn-sm btn-outline-primary">DPR #{{ $e->dpr_id }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No DPR events found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    @endif
</div>
@endsection
