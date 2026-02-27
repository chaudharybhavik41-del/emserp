@extends('layouts.erp')

@section('title', 'Store Issues')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Store Issues</h1>
        @can('store.issue.create')
            <a href="{{ route('store-issues.create') }}" class="btn btn-sm btn-primary">
                New Store Issue
            </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 12%">Issue No</th>
                        <th style="width: 10%">Date</th>
                        <th style="width: 22%">Project</th>
                        <th style="width: 22%">Contractor / Person</th>
                        <th style="width: 12%">Requisition</th>
                        <th style="width: 10%">Status</th>
                        <th style="width: 8%"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($issues as $issue)
                        <tr>
                            <td>{{ $issue->issue_number }}</td>
                            <td>{{ optional($issue->issue_date)->format('d-m-Y') }}</td>
                            <td>
                                @if($issue->project)
                                    {{ $issue->project->code }} - {{ $issue->project->name }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($issue->contractor)
                                    {{ $issue->contractor->name }}
                                    @if($issue->contractor_person_name)
                                        ({{ $issue->contractor_person_name }})
                                    @endif
                                @elseif($issue->contractor_person_name)
                                    {{ $issue->contractor_person_name }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($issue->requisition)
                                    {{ $issue->requisition->requisition_number }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @php
                                    $storeStatus = $issue->status ?? 'draft';
                                    $accStatus = $issue->accounting_status ?? 'pending';
                                    if (!empty($issue->voucher_id)) {
                                        $accStatus = 'posted';
                                    }

                                    $storeBadge = match ($storeStatus) {
                                        'posted' => 'bg-success',
                                        'cancelled' => 'bg-danger',
                                        default => 'bg-secondary',
                                    };

                                    $accBadge = match ($accStatus) {
                                        'posted' => 'bg-success',
                                        'not_required' => 'bg-info',
                                        default => 'bg-secondary', // pending
                                    };

                                    $accLabel = match ($accStatus) {
                                        'posted' => 'Accounts: Posted',
                                        'not_required' => 'Accounts: N/A',
                                        default => 'Accounts: Pending',
                                    };
                                @endphp

                                <div class="d-flex flex-column gap-1">
                                    <span class="badge {{ $storeBadge }}">Store: {{ strtoupper($storeStatus) }}</span>
                                    <span class="badge {{ $accBadge }}">
                                        {{ $accLabel }}
                                        @if($accStatus === 'posted')
                                            @php $vNo = optional($issue->voucher)->voucher_no; @endphp
                                            @if($vNo)
                                                – {{ $vNo }}
                                            @endif
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('store-issues.show', $issue) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">
                                No store issues yet.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($issues->hasPages())
            <div class="card-footer">
                {{ $issues->links() }}
            </div>
        @endif
    </div>
@endsection
