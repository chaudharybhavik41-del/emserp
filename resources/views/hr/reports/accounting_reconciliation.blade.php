@extends('layouts.erp')

@section('title', 'HR Accounting Reconciliation')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h4 class="mb-1">HR Accounting Reconciliation</h4>
            <div class="text-muted small">Read-only diagnostics for HR records that should already be linked to accounting.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.reports.index') }}">Back to HR Reports</a>
            @if(Route::has('accounting.reports.health-check'))
                <a class="btn btn-outline-primary btn-sm" href="{{ route('accounting.reports.health-check') }}">Accounting Health Check</a>
            @endif
        </div>
    </div>

    <div class="alert alert-info small">
        This page does not change any HR or accounting data. Use it to find missing employee ledgers, payroll posting gaps, and unlinked loan or salary advance disbursements before month close.
    </div>

    <div class="row g-3 mb-3">
        @foreach($cards as $card)
            @php
                $toneClass = ($card['tone'] ?? 'success') === 'warning'
                    ? 'border-warning text-warning'
                    : 'border-success text-success';
            @endphp
            <div class="col-md-6 col-xl">
                <div class="card h-100 {{ $toneClass }}">
                    <div class="card-body py-3">
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        <div class="h4 mb-0">{{ $card['count'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @foreach($tables as $table)
        <div class="card mb-3">
            <div class="card-header py-2">
                <div class="fw-semibold">{{ $table['title'] }}</div>
                <div class="text-muted small">{{ $table['description'] }}</div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 28%">Record</th>
                            <th style="width: 12%">Severity</th>
                            <th>Issue</th>
                            <th style="width: 14%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($table['rows'] as $row)
                            @php
                                $status = $row['status'] ?? 'warning';
                                $badgeClass = match ($status) {
                                    'critical' => 'bg-danger-subtle text-danger-emphasis',
                                    default => 'bg-warning-subtle text-warning-emphasis',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row['label'] }}</div>
                                    <div class="text-muted small">{{ $row['meta'] }}</div>
                                </td>
                                <td class="small">
                                    <span class="badge {{ $badgeClass }}">{{ ucfirst($status) }}</span>
                                </td>
                                <td class="small">{{ $row['issue'] }}</td>
                                <td class="small">
                                    <a class="btn btn-outline-primary btn-sm" href="{{ $row['action_url'] }}">{{ $row['action_label'] }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">{{ $table['empty'] }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
