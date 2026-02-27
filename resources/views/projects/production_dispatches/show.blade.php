@extends('layouts.erp')

@section('content')
@php
    $taskIndexRoute = \Illuminate\Support\Facades\Route::has('tasks.index') ? 'tasks.index' : null;
    $taskCreateRoute = \Illuminate\Support\Facades\Route::has('tasks.create') ? 'tasks.create' : null;
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-truck"></i> Dispatch: {{ $dispatch->dispatch_number }}</h2>
            <div class="text-muted small">Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('projects.production-dispatches.index', $project) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>

            @can('tasks.view')
                @if($taskIndexRoute)
                    <a href="{{ route($taskIndexRoute, ['project' => $project->id, 'q' => $dispatch->dispatch_number]) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-list-task"></i> Related Tasks
                    </a>
                @endif
            @endcan

            @can('tasks.create')
                @if($taskCreateRoute)
                    <a href="{{ route($taskCreateRoute, ['project' => $project->id, 'title' => 'Dispatch '. $dispatch->dispatch_number .' follow-up', 'description' => 'Linked from Dispatch '. $dispatch->dispatch_number]) }}" class="btn btn-outline-primary">
                        <i class="bi bi-plus-circle"></i> Add Task
                    </a>
                @endif
            @endcan

            @if($dispatch->isDraft())
                @can('production.dispatch.update')
                    <form method="POST" action="{{ route('projects.production-dispatches.finalize', [$project, $dispatch]) }}">
                        @csrf
                        <button type="submit" class="btn btn-success" onclick="return confirm('Finalize this dispatch? After finalize it will be locked.');">
                            <i class="bi bi-check2-circle"></i> Finalize
                        </button>
                    </form>
                    <form method="POST" action="{{ route('projects.production-dispatches.cancel', [$project, $dispatch]) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Cancel this dispatch? This will reopen balance for items.');">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="mb-2">
                        <div class="text-muted small">Status</div>
                        @php
                            $badge = match($dispatch->status) {
                                'finalized' => 'text-bg-success',
                                'cancelled' => 'text-bg-danger',
                                default => 'text-bg-secondary'
                            };
                        @endphp
                        <span class="badge {{ $badge }}">{{ ucfirst($dispatch->status) }}</span>
                    </div>

                    <div class="mb-2">
                        <div class="text-muted small">Dispatch Date</div>
                        <div class="fw-semibold">{{ $dispatch->dispatch_date?->format('Y-m-d') ?? '-' }}</div>
                    </div>

                    <div class="mb-2">
                        <div class="text-muted small">Client</div>
                        <div class="fw-semibold">{{ $dispatch->client?->name ?? '-' }}</div>
                    </div>

                    <div class="mb-2">
                        <div class="text-muted small">Production Plan</div>
                        <div class="fw-semibold">{{ $dispatch->plan?->plan_number ?? '-' }}</div>
                    </div>

                    <hr>

                    <div class="mb-2">
                        <div class="text-muted small">Vehicle No</div>
                        <div class="fw-semibold">{{ $dispatch->vehicle_number ?? '-' }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="text-muted small">LR No</div>
                        <div class="fw-semibold">{{ $dispatch->lr_number ?? '-' }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="text-muted small">Transporter</div>
                        <div class="fw-semibold">{{ $dispatch->transporter_name ?? '-' }}</div>
                    </div>

                    @if($dispatch->isFinalized())
                        <hr>
                        <div class="text-muted small">Finalized By</div>
                        <div class="fw-semibold">{{ $dispatch->finalizedBy?->name ?? '-' }}</div>
                        <div class="text-muted small">Finalized At</div>
                        <div class="fw-semibold">{{ $dispatch->finalized_at?->format('Y-m-d H:i') ?? '-' }}</div>
                    @endif

                    @if($dispatch->remarks)
                        <hr>
                        <div class="text-muted small">Remarks</div>
                        <div>{{ $dispatch->remarks }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-body table-responsive">
                    <h5 class="mb-3">Dispatched Components</h5>

                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Assembly Mark</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-end">Qty</th>
                                <th>UOM</th>
                                <th class="text-end">Weight (kg)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dispatch->lines as $ln)
                                @php
                                    $meta = $ln->source_meta ?? [];
                                    $mark = $ln->planItem?->assembly_mark ?? ($meta['assembly_mark'] ?? '-');
                                    $type = $ln->planItem?->assembly_type ?? ($meta['assembly_type'] ?? '-');
                                    $desc = $ln->planItem?->description ?? ($meta['description'] ?? '-');
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $mark }}</td>
                                    <td>{{ $type }}</td>
                                    <td>{{ $desc }}</td>
                                    <td class="text-end">{{ number_format((float)$ln->qty, 3) }}</td>
                                    <td>{{ $ln->uom?->symbol ?? '-' }}</td>
                                    <td class="text-end">{{ number_format((float)$ln->weight_kg, 3) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th colspan="3" class="text-end">Totals</th>
                                <th class="text-end">{{ number_format((float)$dispatch->total_qty, 3) }}</th>
                                <th></th>
                                <th class="text-end">{{ number_format((float)$dispatch->total_weight_kg, 3) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
