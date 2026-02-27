@extends('layouts.erp')

@section('title', 'Salary Advances Dashboard')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Salary Advances Dashboard</h4>
        <a href="{{ route('hr.advances.salary-advances.create') }}" class="btn btn-primary btn-sm">New Advance</a>
    </div>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Applied</small><h4 class="mb-0">{{ $summary['applied'] }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Approved</small><h4 class="mb-0">{{ $summary['approved'] }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Recovering</small><h4 class="mb-0">{{ $summary['recovering'] }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Outstanding</small><h4 class="mb-0">₹{{ number_format($summary['outstanding'], 2) }}</h4></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Recent Advances</strong>
            <a href="{{ route('hr.advances.salary-advances.index') }}" class="btn btn-outline-primary btn-sm">Open Advance Register</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>No</th><th>Employee</th><th>Requested</th><th>Balance</th><th>Status</th><th class="text-end">View</th></tr></thead>
                    <tbody>
                        @forelse($recentAdvances as $advance)
                            <tr>
                                <td><code>{{ $advance->advance_number }}</code></td>
                                <td>{{ $advance->employee?->full_name }}</td>
                                <td>₹{{ number_format($advance->requested_amount, 2) }}</td>
                                <td>₹{{ number_format($advance->balance_amount, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $advance->status)) }}</span></td>
                                <td class="text-end"><a href="{{ route('hr.advances.salary-advances.show', $advance) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No records.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
