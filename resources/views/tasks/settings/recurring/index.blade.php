@extends('layouts.erp')

@section('title', 'Recurring Tasks')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0"><i class="bi bi-arrow-repeat me-2"></i>Recurring Schedules</h1>
            <small class="text-muted">Generate recurring operational tasks automatically.</small>
        </div>
        <a href="{{ route('task-settings.recurring.create') }}" class="btn btn-primary">New Schedule</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>List</th>
                        <th>Interval</th>
                        <th>Next Run</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $schedule)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $schedule->name }}</div>
                            <div class="small text-muted">{{ $schedule->title_template }}</div>
                        </td>
                        <td>{{ $schedule->taskList->name }}</td>
                        <td>Every {{ $schedule->interval_value }} {{ $schedule->interval_unit }}</td>
                        <td>{{ $schedule->next_run_at?->format('d M Y H:i') ?? '-' }}</td>
                        <td><span class="badge {{ $schedule->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $schedule->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            <form action="{{ route('task-settings.recurring.run-now', $schedule) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success">Run Now</button>
                            </form>
                            <a href="{{ route('task-settings.recurring.edit', $schedule) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No recurring schedules yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
