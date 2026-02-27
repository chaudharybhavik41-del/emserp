@extends('layouts.erp')

@section('title', 'Breakdown Details')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">Breakdown: {{ $breakdown->breakdown_number }}</h4>
            <div class="text-muted">
                Machine: <strong>{{ $breakdown->machine->name ?? '-' }}</strong>
                <span class="text-muted">({{ $breakdown->machine->code ?? '' }})</span>
            </div>
        </div>

        <div class="text-end">
            <a href="{{ route('maintenance.breakdowns.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>

    @php
        $status = $breakdown->status;
        $statusClass = match ($status) {
            'reported' => 'bg-secondary',
            'acknowledged' => 'bg-info',
            'in_progress' => 'bg-warning',
            'resolved' => 'bg-success',
            'deferred' => 'bg-dark',
            default => 'bg-primary',
        };

        $sev = $breakdown->severity;
        $sevClass = match ($sev) {
            'minor' => 'bg-info',
            'major' => 'bg-warning',
            'critical' => 'bg-danger',
            default => 'bg-secondary',
        };

        $teamIds = $breakdown->maintenance_team_assigned ?? [];
        if (!is_array($teamIds)) { $teamIds = []; }

        $teamIdsOld = old('maintenance_team_assigned', $teamIds);
        if (!is_array($teamIdsOld)) { $teamIdsOld = []; }

        $userMap = $users->keyBy('id');
    @endphp

    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Breakdown Details</span>
                    <div>
                        <span class="badge {{ $sevClass }} me-1">{{ ucfirst($sev) }}</span>
                        <span class="badge {{ $statusClass }}">{{ ucfirst(str_replace('_',' ', $status)) }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Reported At</dt>
                        <dd class="col-sm-8">{{ optional($breakdown->reported_at)->format('Y-m-d H:i') ?? '—' }}</dd>

                        <dt class="col-sm-4">Reported By</dt>
                        <dd class="col-sm-8">{{ $breakdown->reporter->name ?? ('User #' . $breakdown->reported_by) }}</dd>

                        <dt class="col-sm-4">Breakdown Type</dt>
                        <dd class="col-sm-8 text-capitalize">{{ str_replace('_',' ', $breakdown->breakdown_type) }}</dd>

                        <dt class="col-sm-4">Problem Description</dt>
                        <dd class="col-sm-8">{!! nl2br(e($breakdown->problem_description)) !!}</dd>

                        <dt class="col-sm-4">Immediate Action</dt>
                        <dd class="col-sm-8">
                            @if($breakdown->immediate_action_taken)
                                {!! nl2br(e($breakdown->immediate_action_taken)) !!}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Acknowledged</dt>
                        <dd class="col-sm-8">
                            @if($breakdown->acknowledged_at)
                                {{ optional($breakdown->acknowledged_at)->format('Y-m-d H:i') }}
                                <span class="text-muted">by</span>
                                {{ $breakdown->acknowledger->name ?? ('User #' . $breakdown->acknowledged_by) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Response Time</dt>
                        <dd class="col-sm-8">
                            @php $resp = $breakdown->getResponseTimeHours(); @endphp
                            {{ $resp !== null ? number_format($resp, 2) . ' hours' : '—' }}
                        </dd>

                        <dt class="col-sm-4">Maintenance Team</dt>
                        <dd class="col-sm-8">
                            @if(count($teamIds))
                                @foreach($teamIds as $uid)
                                    <span class="badge bg-secondary me-1">{{ $userMap->get($uid)->name ?? ('User #' . $uid) }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Repair Started</dt>
                        <dd class="col-sm-8">
                            @if($breakdown->repair_started_at)
                                {{ optional($breakdown->repair_started_at)->format('Y-m-d H:i') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Repair Completed</dt>
                        <dd class="col-sm-8">
                            @if($breakdown->repair_completed_at)
                                {{ optional($breakdown->repair_completed_at)->format('Y-m-d H:i') }}
                                @if($breakdown->resolved_by)
                                    <span class="text-muted">by</span>
                                    {{ $userMap->get($breakdown->resolved_by)->name ?? ('User #' . $breakdown->resolved_by) }}
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Repair Time</dt>
                        <dd class="col-sm-8">
                            @php $rep = $breakdown->getRepairTimeHours(); @endphp
                            {{ $rep !== null ? number_format($rep, 2) . ' hours' : '—' }}
                        </dd>

                        <dt class="col-sm-4">Root Cause</dt>
                        <dd class="col-sm-8">
                            @if($breakdown->root_cause)
                                {!! nl2br(e($breakdown->root_cause)) !!}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Corrective Action</dt>
                        <dd class="col-sm-8">
                            @if($breakdown->corrective_action)
                                {!! nl2br(e($breakdown->corrective_action)) !!}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Repair Notes</dt>
                        <dd class="col-sm-8">
                            @if($breakdown->repair_notes)
                                {!! nl2br(e($breakdown->repair_notes)) !!}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Estimated Cost</dt>
                        <dd class="col-sm-8">
                            @if(!is_null($breakdown->estimated_cost))
                                {{ number_format((float) $breakdown->estimated_cost, 2) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Production Loss (hrs)</dt>
                        <dd class="col-sm-8">
                            @if(!is_null($breakdown->production_loss_hours))
                                {{ number_format((float) $breakdown->production_loss_hours, 2) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Maintenance Log</dt>
                        <dd class="col-sm-8">
                            @if($breakdown->maintenanceLog)
                                <a href="{{ route('maintenance.logs.show', $breakdown->maintenanceLog) }}">
                                    {{ $breakdown->maintenanceLog->log_number ?? ('Log #' . $breakdown->maintenance_log_id) }}
                                </a>
                            @elseif($breakdown->maintenance_log_id)
                                <span class="text-muted">Log #{{ $breakdown->maintenance_log_id }} (not linked)</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Remarks</dt>
                        <dd class="col-sm-8">
                            @if($breakdown->remarks)
                                {!! nl2br(e($breakdown->remarks)) !!}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">Actions</div>
                <div class="card-body">
                    @if($breakdown->status === 'resolved')
                        <div class="alert alert-success mb-0">
                            This breakdown is resolved.
                        </div>
                    @else
                        @can('machinery.breakdown.acknowledge')
                            @if($breakdown->status === 'reported')
                                <form action="{{ route('maintenance.breakdowns.acknowledge', $breakdown) }}" method="POST" class="mb-3">
                                    @csrf
                                    <button class="btn btn-info w-100" onclick="return confirm('Acknowledge this breakdown?')">
                                        Acknowledge
                                    </button>
                                </form>
                            @endif
                        @endcan

                        @can('machinery.breakdown.acknowledge')
                            <form action="{{ route('maintenance.breakdowns.assign-team', $breakdown) }}" method="POST" class="mb-3">
                                @csrf
                                <label class="form-label">Assign Maintenance Team</label>
                                <select name="maintenance_team_assigned[]" class="form-select" multiple size="6" required>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" {{ in_array($u->id, $teamIdsOld) ? 'selected' : '' }}>
                                            {{ $u->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('maintenance_team_assigned') <div class="text-danger small">{{ $message }}</div> @enderror
                                @error('maintenance_team_assigned.*') <div class="text-danger small">{{ $message }}</div> @enderror
                                <div class="text-muted small mt-1">Hold Ctrl/⌘ to select multiple.</div>

                                <button class="btn btn-primary w-100 mt-2">Save Team</button>
                            </form>
                        @endcan

                        @can('machinery.breakdown.acknowledge')
                            @if(in_array($breakdown->status, ['reported','acknowledged'], true))
                                <form action="{{ route('maintenance.breakdowns.start-repair', $breakdown) }}" method="POST" class="mb-3">
                                    @csrf
                                    <button class="btn btn-warning w-100" onclick="return confirm('Start repair now?')">
                                        Start Repair
                                    </button>
                                </form>
                            @endif
                        @endcan

                        @can('machinery.breakdown.resolve')
                            @if($breakdown->status === 'in_progress')
                                <form action="{{ route('maintenance.breakdowns.resolve', $breakdown) }}" method="POST">
                                    @csrf

                                    <label class="form-label">Resolve Breakdown</label>

                                    <div class="mb-2">
                                        <label class="form-label small">Root Cause</label>
                                        <textarea name="root_cause" class="form-control" rows="2">{{ old('root_cause') }}</textarea>
                                        @error('root_cause') <div class="text-danger small">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small">Corrective Action</label>
                                        <textarea name="corrective_action" class="form-control" rows="2">{{ old('corrective_action') }}</textarea>
                                        @error('corrective_action') <div class="text-danger small">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small">Repair Notes</label>
                                        <textarea name="repair_notes" class="form-control" rows="3">{{ old('repair_notes') }}</textarea>
                                        @error('repair_notes') <div class="text-danger small">{{ $message }}</div> @enderror
                                    </div>

                                    <button class="btn btn-success w-100" onclick="return confirm('Mark this breakdown as resolved?')">
                                        Resolve
                                    </button>
                                </form>
                            @else
                                <div class="alert alert-info mb-0">
                                    To resolve, first start repair (status must be <strong>In Progress</strong>).
                                </div>
                            @endif
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
