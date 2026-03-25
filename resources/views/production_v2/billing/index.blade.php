@extends('layouts.erp')

@section('title', 'Production V2 Billing')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Billing</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.billing-rates.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">Rate Cards</a>
        <a href="{{ route('projects.production-v2.billing.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">Generate Bill</a>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'billing'])

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Bill</th>
                            <th>Date</th>
                            <th>Contractor</th>
                            <th>Period</th>
                            <th class="text-end">Lines</th>
                            <th class="text-end">Grand Total</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>{{ $row->bill_number }}</strong></td>
                            <td>{{ $row->bill_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $row->contractor?->name ?: '-' }}</td>
                            <td>{{ $row->period_from?->format('Y-m-d') ?: '-' }} to {{ $row->period_to?->format('Y-m-d') ?: '-' }}</td>
                            <td class="text-end">{{ number_format($row->lines_count) }}</td>
                            <td class="text-end">{{ number_format((float) $row->grand_total, 2) }}</td>
                            <td>{{ ucfirst($row->status) }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.billing.show', ['project' => $project->id, 'bill' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No Production V2 bills generated yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} bills
                @else
                    Showing 0 bills
                @endif
            </small>
            {{ $rows->links() }}
        </div>
    </div>
@endsection
