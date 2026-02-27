@extends('layouts.erp')

@section('title', 'Maintenance Log')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">Maintenance Log: {{ $log->log_number }}</h4>
            <div class="text-muted">
                Machine: <strong>{{ $log->machine->name ?? '-' }}</strong>
                <span class="text-muted">({{ $log->machine->code ?? '' }})</span>
            </div>
        </div>

        <div class="text-end">
            <a href="{{ route('maintenance.logs.index') }}" class="btn btn-secondary">Back</a>

            @can('machinery.maintenance_log.update')
                <a href="{{ route('maintenance.logs.edit', $log) }}" class="btn btn-warning">Edit</a>

                @if($log->status !== 'completed')
                    <form action="{{ route('maintenance.logs.complete', $log) }}" method="POST" class="d-inline">
                        @csrf
                        <button class="btn btn-success" onclick="return confirm('Mark this log as completed?')">
                            Mark Completed
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">Log Details</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Plan</dt>
                        <dd class="col-sm-8">
                            @if($log->plan)
                                {{ $log->plan->plan_name }} <span class="text-muted">({{ $log->plan->plan_code }})</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Type</dt>
                        <dd class="col-sm-8 text-capitalize">{{ $log->maintenance_type }}</dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-primary">{{ ucfirst(str_replace('_',' ', $log->status)) }}</span>
                        </dd>

                        <dt class="col-sm-4">Scheduled Date</dt>
                        <dd class="col-sm-8">{{ optional($log->scheduled_date)->format('Y-m-d') ?? '—' }}</dd>

                        <dt class="col-sm-4">Started At</dt>
                        <dd class="col-sm-8">{{ optional($log->started_at)->format('Y-m-d H:i') ?? '—' }}</dd>

                        <dt class="col-sm-4">Completed At</dt>
                        <dd class="col-sm-8">{{ optional($log->completed_at)->format('Y-m-d H:i') ?? '—' }}</dd>

                        <dt class="col-sm-4">Downtime (hours)</dt>
                        <dd class="col-sm-8">{{ $log->downtime_hours ?? 0 }}</dd>

                        <dt class="col-sm-4">Meter Reading</dt>
                        <dd class="col-sm-8">
                            {{ $log->meter_reading_before ?? '—' }}
                            <span class="text-muted">→</span>
                            {{ $log->meter_reading_after ?? '—' }}
                        </dd>

                        <dt class="col-sm-4">Work Description</dt>
                        <dd class="col-sm-8">{{ $log->work_description }}</dd>

                        <dt class="col-sm-4">Work Performed</dt>
                        <dd class="col-sm-8">{{ $log->work_performed ?: '—' }}</dd>

                        <dt class="col-sm-4">Findings</dt>
                        <dd class="col-sm-8">{{ $log->findings ?: '—' }}</dd>

                        <dt class="col-sm-4">Recommendations</dt>
                        <dd class="col-sm-8">{{ $log->recommendations ?: '—' }}</dd>

                        <dt class="col-sm-4">Remarks</dt>
                        <dd class="col-sm-8">{{ $log->remarks ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">Cost Summary</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td>Labor Cost</td>
                            <td class="text-end">{{ number_format((float)($log->labor_cost ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td>Parts Cost</td>
                            <td class="text-end">{{ number_format((float)($log->parts_cost ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td>External Service Cost</td>
                            <td class="text-end">{{ number_format((float)($log->external_service_cost ?? 0), 2) }}</td>
                        </tr>
                        <tr class="table-light">
                            <th>Total</th>
                            <th class="text-end">{{ number_format((float)($log->total_cost ?? 0), 2) }}</th>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Import Spares from Store Issue</div>
                <div class="card-body">
                    <form action="{{ route('maintenance.logs.add-spare', $log) }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Store Issue <span class="text-danger">*</span></label>
                            <select name="store_issue_id" class="form-select" required>
                                <option value="">Select Store Issue</option>
                                @foreach($storeIssues as $si)
                                    <option value="{{ $si->id }}">
                                        {{ $si->issue_number ?? ('#' . $si->id) }} | {{ optional($si->issue_date)->format('Y-m-d') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('store_issue_id') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text mb-2">
                            This will import all issue lines into spare consumptions. If already imported, the lines for this Store Issue will be replaced.
                        </div>
                        <button class="btn btn-primary">Import</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Spare Consumptions</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 16%;">Store Issue</th>
                            <th>Item</th>
                            <th style="width: 12%;">Qty</th>
                            <th style="width: 12%;">Unit Cost</th>
                            <th style="width: 12%;">Total</th>
                            <th style="width: 20%;">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($log->spares as $spare)
                            <tr>
                                <td>
                                    @if($spare->storeIssue)
                                        {{ $spare->storeIssue->issue_number ?? ('#' . $spare->storeIssue->id) }}
                                        <div class="text-muted small">{{ optional($spare->storeIssue->issue_date)->format('Y-m-d') }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $spare->item->name ?? $spare->item->item_name ?? ('Item #' . $spare->item_id) }}
                                </td>
                                <td class="text-end">
                                    {{ number_format((float)($spare->qty_consumed ?? 0), 3) }}
                                    <span class="text-muted">{{ $spare->uom->name ?? '' }}</span>
                                </td>
                                <td class="text-end">{{ number_format((float)($spare->unit_cost ?? 0), 2) }}</td>
                                <td class="text-end">{{ number_format((float)($spare->total_cost ?? 0), 2) }}</td>
                                <td>{{ $spare->remarks ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No spare consumptions yet. Import a Store Issue above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
