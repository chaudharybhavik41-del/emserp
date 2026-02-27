@extends('layouts.erp')

@section('title', 'Maintenance Plans')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Maintenance Plans</h4>
        @can('machinery.maintenance_plan.create')
            <a href="{{ route('maintenance.plans.create') }}" class="btn btn-primary">+ New Plan</a>
        @endcan
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Plan Name</th>
                    <th>Machine</th>
                    <th>Type</th>
                    <th>Frequency</th>
                    <th>Next Scheduled</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                    <tr>
                        <td>{{ $plan->plan_code }}</td>
                        <td>{{ $plan->plan_name }}</td>
                        <td>{{ $plan->machine->name ?? '-' }}</td>
                        <td>{{ ucfirst($plan->maintenance_type) }}</td>
                        <td>
                            @if($plan->frequency_type === 'operating_hours')
                                Every {{ $plan->frequency_value }} operating hours
                            @else
                                Every {{ $plan->frequency_value }} {{ str_replace('_', ' ', $plan->frequency_type) }}
                            @endif
                        </td>
                        <td>{{ optional($plan->next_scheduled_date)->format('Y-m-d') ?? '-' }}</td>
                        <td>
                            @if($plan->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('maintenance.plans.show', $plan) }}" class="btn btn-sm btn-info">View</a>

                            @can('machinery.maintenance_plan.update')
                                <a href="{{ route('maintenance.plans.edit', $plan) }}" class="btn btn-sm btn-warning">Edit</a>

                                <form action="{{ route('maintenance.plans.toggle', $plan) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary"
                                            onclick="return confirm('Toggle plan status?')">
                                        {{ $plan->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                            @endcan

                            @can('machinery.maintenance_plan.delete')
                                <form action="{{ route('maintenance.plans.destroy', $plan) }}"
                                      method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this plan?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">No maintenance plans found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $plans->links() }}
</div>
@endsection
