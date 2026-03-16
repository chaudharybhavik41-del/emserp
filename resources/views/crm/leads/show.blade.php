@extends('layouts.erp')

@section('title', 'Lead ' . $lead->code)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">{{ $lead->code }} - {{ $lead->title }}</h1>
        <div class="text-muted small">
            Owner: {{ $lead->owner?->name }} |
            Stage: {{ $lead->stage?->name ?? 'N/A' }} |
            Status: {{ ucfirst($lead->status) }}
        </div>
    </div>

    <div>
        @can('crm.lead.update')
            @if($lead->status === 'open')
                <a href="{{ route('crm.leads.edit', $lead) }}"
                   class="btn btn-sm btn-outline-primary">
                    Edit Lead
                </a>
            @endif
        @endcan
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                Lead Details
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Client</dt>
                    <dd class="col-sm-8">
                        {{ $lead->party?->name ?? '-' }}
                    </dd>

                    <dt class="col-sm-4">Contact</dt>
                    <dd class="col-sm-8">
                        {{ $lead->contact_name ?? '-' }}
                        @if($lead->contact_phone)
                            <div class="small text-muted">{{ $lead->contact_phone }}</div>
                        @endif
                        @if($lead->contact_email)
                            <div class="small text-muted">{{ $lead->contact_email }}</div>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Source</dt>
                    <dd class="col-sm-8">{{ $lead->source?->name ?? '-' }}</dd>

                    <dt class="col-sm-4">Lead Date</dt>
                    <dd class="col-sm-8">
                        {{ optional($lead->lead_date)->format('d-m-Y') ?? '-' }}
                    </dd>

                    <dt class="col-sm-4">Expected Close</dt>
                    <dd class="col-sm-8">
                        {{ optional($lead->expected_close_date)->format('d-m-Y') ?? '-' }}
                    </dd>

                    <dt class="col-sm-4">Expected Value</dt>
                    <dd class="col-sm-8">
                        {{ $lead->expected_value ? number_format($lead->expected_value, 2) : '-' }}
                        @if($lead->probability !== null)
                            <span class="small text-muted">({{ $lead->probability }} %)</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Lead Score</dt>
                    <dd class="col-sm-8">
                        @php
                            $scoreClass = match ($lead->lead_temperature) {
                                'hot' => 'text-bg-danger',
                                'warm' => 'text-bg-warning',
                                'won' => 'text-bg-success',
                                'lost' => 'text-bg-dark',
                                default => 'text-bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $scoreClass }}">{{ $lead->lead_score }}/100</span>
                        <span class="small text-muted ms-1 text-uppercase">{{ $lead->lead_temperature }}</span>
                    </dd>

                    <dt class="col-sm-4">Weighted Value</dt>
                    <dd class="col-sm-8">
                        {{ $lead->weighted_value > 0 ? number_format($lead->weighted_value, 2) : '-' }}
                    </dd>

                    <dt class="col-sm-4">Next Follow-up</dt>
                    <dd class="col-sm-8">
                        {{ $lead->next_follow_up_at ? $lead->next_follow_up_at->format('d-m-Y H:i') : '-' }}
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                Notes & Qualification
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    @foreach($lead->lead_score_breakdown as $label => $score)
                        <div class="col-6">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small text-uppercase">{{ str_replace('_', ' ', $label) }}</div>
                                <div class="fw-semibold">{{ $score }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($lead->notes)
                    <p class="mb-0" style="white-space: pre-wrap;">{{ $lead->notes }}</p>
                @else
                    <p class="text-muted mb-0">No notes added.</p>
                @endif

                @if($lead->status === 'lost' && $lead->lost_reason)
                    <hr>
                    <p class="mb-0">
                        <strong>Lost Reason:</strong><br>
                        <span style="white-space: pre-wrap;">{{ $lead->lost_reason }}</span>
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>


<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            Attachments
        </div>
        <div class="small text-muted">
            {{ $lead->attachments->count() }} file(s)
        </div>
    </div>

    <div class="card-body">
        @can('crm.lead.update')
            <form method="POST"
                  action="{{ route('crm.leads.attachments.store', $lead) }}"
                  enctype="multipart/form-data"
                  class="mb-3">
                @csrf

                <div class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label mb-1">Upload Files</label>
                        <input type="file"
                               name="files[]"
                               multiple
                               class="form-control @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror">
                        @error('files')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @error('files.*')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Max 20MB per file. You can select multiple files.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label mb-1">Category</label>
                        <input type="text"
                               name="category"
                               maxlength="50"
                               class="form-control"
                               value="{{ old('category', 'crm_lead') }}"
                               placeholder="drawing / boq / spec">
                    </div>

                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            Upload
                        </button>
                    </div>
                </div>
            </form>
        @endcan

        @php
            $formatBytes = function ($bytes) {
                $bytes = (int) ($bytes ?? 0);

                if ($bytes >= 1073741824) {
                    return number_format($bytes / 1073741824, 2) . ' GB';
                }

                if ($bytes >= 1048576) {
                    return number_format($bytes / 1048576, 2) . ' MB';
                }

                if ($bytes >= 1024) {
                    return number_format($bytes / 1024, 1) . ' KB';
                }

                return $bytes . ' B';
            };
        @endphp

        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>File</th>
                    <th style="width: 12%">Category</th>
                    <th style="width: 18%">Type</th>
                    <th style="width: 10%">Size</th>
                    <th style="width: 14%">Uploaded By</th>
                    <th style="width: 16%">Uploaded At</th>
                    <th style="width: 16%" class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($lead->attachments as $attachment)
                    <tr>
                        <td>{{ $attachment->original_name }}</td>
                        <td class="small text-muted">{{ $attachment->category ?? '-' }}</td>
                        <td class="small text-muted">{{ $attachment->mime_type ?? '-' }}</td>
                        <td class="small text-muted">{{ $formatBytes($attachment->size) }}</td>
                        <td class="small">{{ $attachment->uploader?->name ?? '-' }}</td>
                        <td class="small">{{ optional($attachment->created_at)->format('d-m-Y H:i') }}</td>
                        <td class="text-end">
                            @can('crm.lead.view')
                                <a href="{{ route('crm.leads.attachments.download', [$lead, $attachment]) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    Download
                                </a>
                            @endcan

                            @can('crm.lead.update')
                                <form action="{{ route('crm.leads.attachments.destroy', [$lead, $attachment]) }}"
                                      method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this attachment?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">
                                        Delete
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">
                            No attachments uploaded.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            Follow-ups & Activities
        </div>
        <div class="small text-muted">
            Open: {{ $pendingActivities->count() }} |
            Completed: {{ $completedActivities->count() }} |
            Overdue: {{ $overdueActivitiesCount }}
        </div>
    </div>

    <div class="card-body">
        @can('crm.lead.update')
            <form method="POST" action="{{ route('crm.leads.activities.store', $lead) }}" class="mb-4">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label mb-1">Type</label>
                        <select name="type" class="form-select @error('type', 'activity') is-invalid @enderror">
                            @foreach(['call' => 'Call', 'meeting' => 'Meeting', 'email' => 'Email', 'note' => 'Note', 'task' => 'Task'] as $value => $label)
                                <option value="{{ $value }}" {{ old('type') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('type', 'activity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label mb-1">Subject</label>
                        <input type="text"
                               name="subject"
                               value="{{ old('subject') }}"
                               class="form-control @error('subject', 'activity') is-invalid @enderror"
                               placeholder="Client follow-up / site meeting / quotation review">
                        @error('subject', 'activity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label mb-1">Due At</label>
                        <input type="datetime-local"
                               name="due_at"
                               value="{{ old('due_at') }}"
                               class="form-control @error('due_at', 'activity') is-invalid @enderror">
                        <div class="form-text">Leave blank to apply the default SLA for this activity type.</div>
                        @error('due_at', 'activity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            Add Activity
                        </button>
                    </div>

                    <div class="col-12">
                        <label class="form-label mb-1">Description</label>
                        <textarea name="description"
                                  rows="2"
                                  class="form-control @error('description', 'activity') is-invalid @enderror"
                                  placeholder="Capture next step, notes from the client, or internal action items.">{{ old('description') }}</textarea>
                        @error('description', 'activity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </form>
        @endcan

        @if($activities->isEmpty())
            <div class="text-muted small">No follow-ups or CRM activities logged yet.</div>
        @else
            <div class="list-group list-group-flush">
                @foreach($activities as $activity)
                    @php
                        $isDone = $activity->done_at !== null;
                        $isOverdue = !$isDone && $activity->due_at && $activity->due_at->isPast();
                        $badgeClass = match ($activity->type) {
                            'call' => 'text-bg-primary',
                            'meeting' => 'text-bg-info',
                            'email' => 'text-bg-secondary',
                            'note' => 'text-bg-dark',
                            'task' => 'text-bg-warning',
                            default => 'text-bg-light',
                        };
                    @endphp
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge {{ $badgeClass }}">{{ strtoupper($activity->type ?? 'note') }}</span>
                                    @if($isDone)
                                        <span class="badge text-bg-success">Completed</span>
                                    @elseif($isOverdue)
                                        <span class="badge text-bg-danger">Overdue</span>
                                    @else
                                        <span class="badge text-bg-secondary">Open</span>
                                    @endif
                                    @php
                                        $slaBadgeClass = match ($activity->sla_status) {
                                            'breached', 'completed_late' => 'text-bg-danger',
                                            'due_soon' => 'text-bg-warning',
                                            'completed_on_time' => 'text-bg-success',
                                            default => 'text-bg-info',
                                        };
                                    @endphp
                                    <span class="badge {{ $slaBadgeClass }}">SLA {{ strtoupper(str_replace('_', ' ', $activity->sla_status)) }}</span>
                                    @if($activity->last_reminded_at && ! $isDone)
                                        <span class="badge text-bg-light">Reminded {{ $activity->last_reminded_at->diffForHumans() }}</span>
                                    @endif
                                    @if($activity->last_escalated_at && ! $isDone)
                                        <span class="badge text-bg-dark">Escalated {{ $activity->last_escalated_at->diffForHumans() }}</span>
                                    @endif
                                    <span class="fw-semibold">{{ $activity->subject ?: 'Untitled activity' }}</span>
                                </div>
                                <div class="small text-muted mt-1">
                                    By {{ $activity->user?->name ?? 'System' }}
                                    @if($activity->due_at)
                                        | Due {{ $activity->due_at->format('d-m-Y H:i') }}
                                    @endif
                                    @if($activity->sla_due_at)
                                        | SLA {{ $activity->sla_due_at->format('d-m-Y H:i') }}
                                    @endif
                                    @if($activity->done_at)
                                        | Done {{ $activity->done_at->format('d-m-Y H:i') }}
                                    @endif
                                </div>
                                @if($activity->description)
                                    <div class="mt-2 small" style="white-space: pre-wrap;">{{ $activity->description }}</div>
                                @endif
                            </div>

                            @can('crm.lead.update')
                                <div class="d-flex gap-2">
                                    @unless($isDone)
                                        <form method="POST" action="{{ route('crm.leads.activities.complete', [$lead, $activity]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                Mark Complete
                                            </button>
                                        </form>
                                    @endunless

                                    <form method="POST"
                                          action="{{ route('crm.leads.activities.destroy', [$lead, $activity]) }}"
                                          onsubmit="return confirm('Delete this activity?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>


<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            Quotations
        </div>

        <div class="d-flex align-items-center gap-2">
            @can('crm.quotation.create')
                @if($lead->status === 'open')
                    <a href="{{ route('crm.leads.quotations.create', $lead) }}"
                       class="btn btn-sm btn-primary">
                        + New Quotation
                    </a>
                @endif
            @endcan

            @can('crm.lead.update')
                @if($lead->status === 'open')
                    {{-- Mark WON --}}
                    <form action="{{ route('crm.leads.mark-won', $lead) }}"
                          method="POST"
                          class="d-inline">
                        @csrf
                        <button class="btn btn-success btn-sm">
                            Mark WON
                        </button>
                    </form>

                    {{-- Mark LOST (shows collapse for reason) --}}
                    <button class="btn btn-outline-danger btn-sm"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#lead-lost-reason">
                        Mark LOST
                    </button>
                @endif
            @endcan
        </div>
    </div>

    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th style="width: 10%">Code</th>
                <th style="width: 10%">Revision</th>
                <th>Project</th>
                <th style="width: 12%">Status</th>
                <th style="width: 15%">Total</th>
                <th style="width: 18%">Dates</th>
                <th style="width: 15%" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($lead->quotations as $quotation)
                <tr>
                    <td>{{ $quotation->code }}</td>
                    <td>{{ $quotation->revision_no }}</td>
                    <td>{{ $quotation->project_name }}</td>
                    <td>{{ ucfirst($quotation->status) }}</td>
                    <td>{{ number_format($quotation->grand_total, 2) }}</td>
                    <td class="small">
                        @if($quotation->sent_at)
                            Sent: {{ $quotation->sent_at->format('d-m-Y') }}<br>
                        @endif
                        @if($quotation->accepted_at)
                            Accepted: {{ $quotation->accepted_at->format('d-m-Y') }}
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('crm.quotations.show', $quotation) }}"
                           class="btn btn-sm btn-outline-secondary">
                            View
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-3">
                        No quotations yet.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>

        {{-- LOST reason collapse (only when lead is open & user can update) --}}
        @can('crm.lead.update')
            @if($lead->status === 'open')
                <div id="lead-lost-reason" class="collapse mt-2">
                    <div class="card border-0 border-top">
                        <div class="card-body">
                            <form action="{{ route('crm.leads.mark-lost', $lead) }}" method="POST">
                                @csrf
                                <div class="mb-2">
                                    <label for="lost_reason" class="form-label">
                                        Lost Reason <span class="text-danger">*</span>
                                    </label>
                                    <textarea id="lost_reason"
                                              name="lost_reason"
                                              rows="2"
                                              required
                                              class="form-control @error('lost_reason') is-invalid @enderror">{{ old('lost_reason') }}</textarea>
                                    @error('lost_reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Optional: choose specific "Lost" stage --}}
                                @if($lostStages->count() > 1)
                                    <div class="mb-2">
                                        <label for="lost_stage_id" class="form-label">Lost Stage</label>
                                        <select name="lead_stage_id" id="lost_stage_id" class="form-select">
                                            <option value="">Default lost stage</option>
                                            @foreach($lostStages as $stage)
                                                <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <button type="submit" class="btn btn-danger btn-sm">
                                    Confirm LOST
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endcan
    </div>
</div>
@endsection
