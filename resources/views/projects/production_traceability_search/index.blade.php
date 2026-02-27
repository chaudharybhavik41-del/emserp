@extends('layouts.erp')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-0"><i class="bi bi-search"></i> Traceability Search</h2>
                <div class="text-muted small">
                    Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}
                </div>
            </div>
        </div>

        @include('partials.flash')

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('projects.production-traceability.index', $project) }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-8">
                        <label class="form-label mb-1">Search</label>
                        <input
                            type="text"
                            name="q"
                            value="{{ $q }}"
                            class="form-control"
                            placeholder="Plate No / Heat No / MTC No / Piece No / Assembly Mark"
                            autofocus
                        >
                        <div class="form-text small">
                            Search starts from <strong>raw material</strong> (stock), goes to <strong>pieces</strong>, then <strong>assemblies</strong> and finally shows <strong>DPR activity history</strong>.
                        </div>
                    </div>
                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button class="btn btn-primary"><i class="bi bi-search me-1"></i> Search</button>
                        <a class="btn btn-outline-secondary" href="{{ route('projects.production-traceability.index', $project) }}">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if(($q ?? '') === '')
            <div class="alert alert-info">
                Enter a <strong>Plate / Heat / MTC</strong> number (or Piece / Assembly reference) to trace the full lineage.
            </div>
        @else
            @if(($stockItems->count() + $pieces->count() + $assemblies->count() + $dprLines->count()) === 0)
                <div class="alert alert-warning">
                    No records found for <strong>{{ $q }}</strong>.
                </div>
            @endif
        @endif

        {{-- STOCK ITEMS --}}
        @if($stockItems->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <i class="bi bi-box-seam me-1"></i> Matching Stock Items ({{ $stockItems->count() }})
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Plate</th>
                            <th>Heat</th>
                            <th>MTC</th>
                            <th class="text-end">Qty</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($stockItems as $si)
                            <tr>
                                <td class="fw-semibold">{{ $si->id }}</td>
                                <td>
                                    @if($si->item)
                                        <div class="fw-semibold">{{ $si->item->code }}</div>
                                        <div class="text-muted small">{{ $si->item->name }}</div>
                                    @else
                                        <span class="text-muted">Item #{{ $si->item_id }}</span>
                                    @endif
                                </td>
                                <td>{{ $si->plate_number ?? '-' }}</td>
                                <td>{{ $si->heat_number ?? '-' }}</td>
                                <td>{{ $si->mtc_number ?? '-' }}</td>
                                <td class="text-end">
                                    {{ number_format((float)($si->qty ?? 0), 3) }}
                                    {{ $si->item?->uom?->symbol ?? '' }}
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ $si->status ?? '-' }}</span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- PIECES --}}
        @if($pieces->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <i class="bi bi-scissors me-1"></i> Matching Pieces ({{ $pieces->count() }})
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Piece No</th>
                            <th>Tag</th>
                            <th>Plate</th>
                            <th>Heat</th>
                            <th>MTC</th>
                            <th>Mother Stock</th>
                            <th class="text-end">Weight (kg)</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($pieces as $p)
                            <tr>
                                <td class="fw-semibold">{{ $p->piece_number }}</td>
                                <td>{{ $p->piece_tag ?? '-' }}</td>
                                <td>{{ $p->plate_number ?? '-' }}</td>
                                <td>{{ $p->heat_number ?? '-' }}</td>
                                <td>{{ $p->mtc_number ?? '-' }}</td>
                                <td>
                                    @if($p->motherStockItem)
                                        <span class="fw-semibold">#{{ $p->motherStockItem->id }}</span>
                                        <span class="text-muted small">{{ $p->motherStockItem?->item?->code ?? '' }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    {{ $p->weight !== null ? number_format((float)$p->weight, 3) : '-' }}
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ $p->status ?? '-' }}</span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ASSEMBLIES --}}
        @if($assemblies->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <i class="bi bi-diagram-3 me-1"></i> Assemblies ({{ $assemblies->count() }})
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Assembly Mark</th>
                            <th>Tag</th>
                            <th>Status</th>
                            <th class="text-end">Components</th>
                            <th>Pieces (sample)</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($assemblies as $a)
                            @php
                                $compCount = $a->components?->count() ?? 0;
                                $pieceNos = $a->components
                                    ? $a->components->pluck('piece.piece_number')->filter()->unique()->values()
                                    : collect();
                                $pieceSample = $pieceNos->take(6);
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $a->assembly_mark }}</td>
                                <td>{{ $a->assembly_tag ?? '-' }}</td>
                                <td><span class="badge bg-secondary">{{ $a->status ?? '-' }}</span></td>
                                <td class="text-end">{{ $compCount }}</td>
                                <td>
                                    @foreach($pieceSample as $pn)
                                        <span class="badge bg-light text-dark border">{{ $pn }}</span>
                                    @endforeach
                                    @if($pieceNos->count() > $pieceSample->count())
                                        <span class="text-muted small">+{{ $pieceNos->count() - $pieceSample->count() }} more</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- DPR LINES / HISTORY --}}
        @if($dprLines->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <i class="bi bi-journal-text me-1"></i> DPR / Activity History ({{ $dprLines->count() }})
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>DPR</th>
                            <th>Activity</th>
                            <th>Assembly</th>
                            <th class="text-center">Completed</th>
                            <th class="text-center">Trace Done</th>
                            <th>Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($dprLines as $line)
                            @php
                                $dpr = $line->dpr;
                                $actName = $dpr?->activity?->name ?? $line->planItemActivity?->activity?->name ?? '-';
                                $assId = $line->production_assembly_id;
                                $assMark = $assId ? ($assemblyMarks[$assId] ?? ('#'.$assId)) : '-';
                            @endphp
                            <tr>
                                <td>{{ $dpr?->dpr_date?->format('d M Y') ?? '-' }}</td>
                                <td>
                                    @if($dpr)
                                        <a href="{{ route('projects.production-dprs.show', [$project, $dpr]) }}">
                                            {{ $dpr->dpr_no ?? ('#'.$dpr->id) }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $actName }}</td>
                                <td>{{ $assMark }}</td>
                                <td class="text-center">
                                    @if($line->is_completed)
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($line->traceability_done)
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @endif
                                </td>
                                <td>{{ $line->remarks ?? '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
@endsection