@extends('layouts.erp')

@section('title', 'Accounting Health Check')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Accounting Health Check</h1>
            <div class="text-muted small">Readiness checks for posting config, master links, and core accounting dependencies.</div>
        </div>
        <div class="small text-muted">Company #{{ $companyId }}</div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-success h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">OK</div>
                    <div class="h4 mb-0 text-success">{{ $summary['ok'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Warnings</div>
                    <div class="h4 mb-0 text-warning">{{ $summary['warning'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Critical</div>
                    <div class="h4 mb-0 text-danger">{{ $summary['critical'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info small">
        This page does not change any accounting data. Use it to fix missing ledger codes, missing party ledgers, and missing model/config dependencies before users hit posting-time exceptions.
    </div>

    @foreach($sections as $section)
        <div class="card mb-3">
            <div class="card-header py-2">
                <div class="fw-semibold">{{ $section['title'] }}</div>
                @if(!empty($section['description']))
                    <div class="text-muted small">{{ $section['description'] }}</div>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 22%">Check</th>
                            <th style="width: 10%">Status</th>
                            <th style="width: 28%">Configured / Detail</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                            @php
                                $status = $row['status'] ?? 'warning';
                                $badgeClass = match ($status) {
                                    'ok' => 'bg-success-subtle text-success-emphasis',
                                    'critical' => 'bg-danger-subtle text-danger-emphasis',
                                    default => 'bg-warning-subtle text-warning-emphasis',
                                };
                                $statusText = ucfirst($status);
                            @endphp
                            <tr>
                                <td class="small fw-semibold">{{ $row['label'] }}</td>
                                <td class="small">
                                    <span class="badge {{ $badgeClass }}">{{ $statusText }}</span>
                                </td>
                                <td class="small text-muted">{{ $row['detail'] }}</td>
                                <td class="small">{{ $row['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
