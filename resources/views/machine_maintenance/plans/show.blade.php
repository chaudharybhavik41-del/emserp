@extends('layouts.erp')

@section('title', 'Maintenance Plan Details')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Plan: {{ $maintenance_plan->plan_name }}</h4>
        <div>
            <a href="{{ route('maintenance.plans.index') }}" class="btn btn-secondary">Back</a>

            @can('machinery.maintenance_plan.update')
                <a href="{{ route('maintenance.plans.edit', $maintenance_plan) }}" class="btn btn-warning">Edit</a>

                <form action="{{ route('maintenance.plans.toggle', $maintenance_plan) }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-outline-secondary"
                            onclick="return confirm('Toggle plan status?')">
                        {{ $maintenance_plan->is_active ? 'Disable' : 'Enable' }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Plan Code</dt>
                <dd class="col-sm-9">{{ $maintenance_plan->plan_code }}</dd>

                <dt class="col-sm-3">Machine</dt>
                <dd class="col-sm-9">{{ $maintenance_plan->machine->name ?? '-' }}</dd>

                <dt class="col-sm-3">Maintenance Type</dt>
                <dd class="col-sm-9">{{ ucfirst($maintenance_plan->maintenance_type) }}</dd>

                <dt class="col-sm-3">Frequency</dt>
                <dd class="col-sm-9">
                    @if($maintenance_plan->frequency_type === 'operating_hours')
                        Every {{ $maintenance_plan->frequency_value }} operating hours
                    @else
                        Every {{ $maintenance_plan->frequency_value }} {{ str_replace('_', ' ', $maintenance_plan->frequency_type) }}
                    @endif
                </dd>

                <dt class="col-sm-3">Last Executed</dt>
                <dd class="col-sm-9">{{ optional($maintenance_plan->last_executed_date)->format('Y-m-d') ?? '-' }}</dd>

                <dt class="col-sm-3">Next Scheduled</dt>
                <dd class="col-sm-9">{{ optional($maintenance_plan->next_scheduled_date)->format('Y-m-d') ?? '-' }}</dd>

                <dt class="col-sm-3">Alert Days Before</dt>
                <dd class="col-sm-9">{{ $maintenance_plan->alert_days_before }}</dd>

                <dt class="col-sm-3">Estimated Duration (hours)</dt>
                <dd class="col-sm-9">{{ $maintenance_plan->estimated_duration_hours ?? '-' }}</dd>

                <dt class="col-sm-3">Requires Shutdown</dt>
                <dd class="col-sm-9">
                    @if($maintenance_plan->requires_shutdown)
                        <span class="badge bg-danger">Yes</span>
                    @else
                        <span class="badge bg-success">No</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if($maintenance_plan->is_active)
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-secondary">Inactive</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Alert Users</dt>
                <dd class="col-sm-9">
                    @php($uids = $maintenance_plan->alert_user_ids ?? [])
                    @if(empty($uids))
                        <em>None</em>
                    @else
                        @foreach($uids as $uid)
                            @php($u = \App\Models\User::find($uid))
                            <span class="badge bg-info text-dark">{{ $u->name ?? ('User#' . $uid) }}</span>
                        @endforeach
                    @endif
                </dd>

                <dt class="col-sm-3">Checklist</dt>
                <dd class="col-sm-9">
                    @php($items = $maintenance_plan->checklist_items ?? [])
                    @if(empty($items))
                        <em>None</em>
                    @else
                        <ul class="mb-0">
                            @foreach($items as $i)
                                <li>{{ $i }}</li>
                            @endforeach
                        </ul>
                    @endif
                </dd>

                <dt class="col-sm-3">Remarks</dt>
                <dd class="col-sm-9">{{ $maintenance_plan->remarks ?? '-' }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
