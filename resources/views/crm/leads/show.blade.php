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
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                Notes
            </div>
            <div class="card-body">
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
