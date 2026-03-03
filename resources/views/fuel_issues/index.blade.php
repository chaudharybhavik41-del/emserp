@extends('layouts.erp')

@section('title', 'Fuel Issues')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Fuel Issues</h1>
        @can('store.issue.create')
            <a href="{{ route('fuel-issues.create') }}" class="btn btn-sm btn-primary">New Fuel Issue</a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Issue No</th>
                    <th>Date</th>
                    <th>Machine</th>
                    <th>Project</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($issues as $issue)
                    @php
                        $accStatus = $issue->accounting_status ?? 'pending';
                        if (!empty($issue->voucher_id)) {
                            $accStatus = 'posted';
                        }
                    @endphp
                    <tr>
                        <td>{{ $issue->issue_number ?: ('FUEL-' . $issue->id) }}</td>
                        <td>{{ optional($issue->issue_date)->format('d-m-Y') }}</td>
                        <td>
                            @if($issue->machine)
                                {{ $issue->machine->code ? ($issue->machine->code . ' - ') : '' }}{{ $issue->machine->name }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($issue->project)
                                {{ $issue->project->code }} - {{ $issue->project->name }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-end">{{ number_format((float) $issue->qty, 3) }}</td>
                        <td class="text-end">{{ number_format((float) $issue->amount, 2) }}</td>
                        <td>
                            @if($accStatus === 'posted')
                                <span class="badge bg-success">Accounts: Posted</span>
                            @elseif($accStatus === 'not_required')
                                <span class="badge bg-info">Accounts: N/A</span>
                            @else
                                <span class="badge bg-secondary">Accounts: Pending</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('fuel-issues.show', $issue) }}" class="btn btn-sm btn-outline-secondary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">No fuel issues found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($issues->hasPages())
            <div class="card-footer">
                {{ $issues->links() }}
            </div>
        @endif
    </div>
@endsection

