@extends('layouts.erp')

@section('title', 'Subcontractor Work Orders')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Subcontractor Work Orders</h1>
            <div class="text-muted small">Maintain subcontractor commercial terms for RA billing and advance tagging.</div>
        </div>
        <a href="{{ route('accounting.subcontractor-work-orders.create') }}" class="btn btn-primary btn-sm">Create Work Order</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Subcontractor</label>
                    <select name="subcontractor_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($subcontractors as $subcontractor)
                            <option value="{{ $subcontractor->id }}" @selected((string) request('subcontractor_id') === (string) $subcontractor->id)>
                                {{ $subcontractor->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(['draft' => 'Draft', 'active' => 'Active', 'closed' => 'Closed', 'cancelled' => 'Cancelled'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="{{ route('accounting.subcontractor-work-orders.index') }}" class="btn btn-link btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Work Order</th>
                            <th>Date</th>
                            <th>Subcontractor</th>
                            <th>Project</th>
                            <th class="text-end">Terms</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($workOrders as $workOrder)
                            <tr>
                                <td>{{ $workOrder->work_order_number }}</td>
                                <td>{{ $workOrder->work_order_date?->format('d-m-Y') ?? '-' }}</td>
                                <td>{{ $workOrder->subcontractor?->name ?? '-' }}</td>
                                <td>{{ $workOrder->project?->name ?? '-' }}</td>
                                <td class="text-end">
                                    Pay {{ $workOrder->payment_terms_days ?? '-' }}d
                                    | Ret {{ number_format((float) ($workOrder->retention_percent ?? 0), 2) }}%
                                    | Sec {{ number_format((float) ($workOrder->security_deposit_percent ?? 0), 2) }}%
                                </td>
                                <td><span class="badge text-bg-secondary">{{ ucfirst((string) $workOrder->status) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('accounting.subcontractor-work-orders.show', $workOrder) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                    <a href="{{ route('accounting.subcontractor-work-orders.edit', $workOrder) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">No subcontractor work orders found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $workOrders->links() }}
    </div>
</div>
@endsection
