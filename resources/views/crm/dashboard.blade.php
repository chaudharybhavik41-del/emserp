@extends('layouts.erp')

@section('title', 'CRM Dashboard')

@section('page_header')
    <div>
        <h1 class="h5 mb-0">CRM Dashboard</h1>
        <small class="text-muted">Pipeline, follow-ups, ageing, owner workload, and quotation conversion.</small>
    </div>

    <div class="d-flex gap-2">
        @if(\Illuminate\Support\Facades\Route::has('crm.leads.index'))
            <a href="{{ route('crm.leads.index') }}" class="btn btn-outline-secondary btn-sm">
                Leads
            </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('crm.quotations.index'))
            <a href="{{ route('crm.quotations.index') }}" class="btn btn-outline-secondary btn-sm">
                Quotations
            </a>
        @endif
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('crm.dashboard') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Owner</label>
                    <select name="owner_id" class="form-select">
                        <option value="">All owners</option>
                        @foreach($owners as $owner)
                            <option value="{{ $owner->id }}" {{ (int) $ownerFilter === (int) $owner->id ? 'selected' : '' }}>
                                {{ $owner->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Lead Stage</label>
                    <select name="lead_stage_id" class="form-select">
                        <option value="">All stages</option>
                        @foreach($stages as $stage)
                            <option value="{{ $stage->id }}" {{ (int) $stageFilter === (int) $stage->id ? 'selected' : '' }}>
                                {{ $stage->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        Apply Filters
                    </button>
                    <a href="{{ route('crm.dashboard') }}" class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Open Leads</div>
                    <div class="h4 mb-0">{{ $kpis['open_leads'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Won Leads</div>
                    <div class="h4 mb-0 text-success">{{ $kpis['won_leads'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Lost Leads</div>
                    <div class="h4 mb-0 text-danger">{{ $kpis['lost_leads'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Overdue Follow-ups</div>
                    <div class="h4 mb-0 text-danger">{{ $kpis['overdue_followups'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Due This Week</div>
                    <div class="h4 mb-0 text-warning">{{ $kpis['due_this_week'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Accepted Quotes</div>
                    <div class="h4 mb-0 text-primary">{{ $kpis['accepted_quotations'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Avg Lead Score</div>
                    <div class="h4 mb-0">{{ number_format($kpis['avg_open_lead_score'], 1) }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Hot Leads</div>
                    <div class="h4 mb-0 text-danger">{{ $kpis['hot_leads'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="card-title text-uppercase small text-muted mb-0">Pipeline By Stage</h6>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Stage</th>
                                <th class="text-end">Open Leads</th>
                                <th class="text-end">Expected Value</th>
                                <th class="text-end">Avg Age (Days)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pipeline as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['stage_name'] }}</td>
                                    <td class="text-end">{{ $row['lead_count'] }}</td>
                                    <td class="text-end">₹ {{ number_format($row['expected_value'], 2) }}</td>
                                    <td class="text-end">{{ number_format($row['avg_age_days'], 1) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No open leads in the selected scope.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="card-title text-uppercase small text-muted mb-0">Quotation Conversion</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">Draft</div>
                                <div class="fw-semibold">{{ $quotationSummary['draft'] }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">Sent</div>
                                <div class="fw-semibold">{{ $quotationSummary['sent'] }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">Accepted</div>
                                <div class="fw-semibold text-success">{{ $quotationSummary['accepted'] }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">Rejected</div>
                                <div class="fw-semibold text-danger">{{ $quotationSummary['rejected'] }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded p-3 mb-2">
                        <div class="text-muted small">Accepted Value</div>
                        <div class="h5 mb-0">₹ {{ number_format($quotationSummary['accepted_value'], 2) }}</div>
                    </div>

                    <div class="border rounded p-3">
                        <div class="text-muted small">Closed-Quote Conversion Rate</div>
                        <div class="h5 mb-0">
                            {{ $quotationSummary['conversion_rate'] !== null ? number_format($quotationSummary['conversion_rate'], 1) . '%' : '—' }}
                        </div>
                        <div class="small text-muted">Accepted / (accepted + rejected + superseded)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="card-title text-uppercase small text-muted mb-0">Lead Ageing</h6>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Bucket</th>
                                <th class="text-end">Open Leads</th>
                                <th class="text-end">Expected Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ageing as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['label'] }}</td>
                                    <td class="text-end">{{ $row['lead_count'] }}</td>
                                    <td class="text-end">₹ {{ number_format($row['expected_value'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="card-title text-uppercase small text-muted mb-0">Owner Workload</h6>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Owner</th>
                                <th class="text-end">Open Leads</th>
                                <th class="text-end">Overdue Follow-ups</th>
                                <th class="text-end">Accepted Quotes</th>
                                <th class="text-end">Avg Lead Score</th>
                                <th class="text-end">Lead Win Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ownerWorkload as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['owner_name'] }}</td>
                                    <td class="text-end">{{ $row['open_leads'] }}</td>
                                    <td class="text-end">{{ $row['overdue_followups'] }}</td>
                                    <td class="text-end">{{ $row['accepted_quotations'] }}</td>
                                    <td class="text-end">{{ number_format($row['avg_lead_score'], 1) }}</td>
                                    <td class="text-end">
                                        {{ $row['conversion_rate'] !== null ? number_format($row['conversion_rate'], 1) . '%' : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">No owner workload data available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="card-title text-uppercase small text-muted mb-0">Overdue Follow-ups</h6>
                    <span class="small text-danger">{{ $overdueActivities->count() }} shown</span>
                </div>
                <div class="card-body">
                    @forelse($overdueActivities as $activity)
                        <div class="border rounded p-3 mb-2">
                            <div class="fw-semibold">{{ $activity->subject ?: 'Untitled activity' }}</div>
                            <div class="small text-muted">
                                {{ strtoupper($activity->type ?? 'note') }}
                                | Lead:
                                <a href="{{ route('crm.leads.show', $activity->lead) }}">{{ $activity->lead?->code }}</a>
                                | Due {{ $activity->due_at?->format('d-m-Y H:i') }}
                            </div>
                            @if($activity->description)
                                <div class="small mt-2" style="white-space: pre-wrap;">{{ $activity->description }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted small">No overdue follow-ups in the selected scope.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="card-title text-uppercase small text-muted mb-0">Upcoming Follow-ups</h6>
                    <span class="small text-warning">{{ $upcomingActivities->count() }} shown</span>
                </div>
                <div class="card-body">
                    @forelse($upcomingActivities as $activity)
                        <div class="border rounded p-3 mb-2">
                            <div class="fw-semibold">{{ $activity->subject ?: 'Untitled activity' }}</div>
                            <div class="small text-muted">
                                {{ strtoupper($activity->type ?? 'note') }}
                                | Lead:
                                <a href="{{ route('crm.leads.show', $activity->lead) }}">{{ $activity->lead?->code }}</a>
                                | Due {{ $activity->due_at?->format('d-m-Y H:i') }}
                            </div>
                            @if($activity->description)
                                <div class="small mt-2" style="white-space: pre-wrap;">{{ $activity->description }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted small">No follow-ups due in the next 7 days.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
