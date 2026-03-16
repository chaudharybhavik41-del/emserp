@extends('layouts.erp')

@section('title', 'Task Automations')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0"><i class="bi bi-magic me-2"></i>Task Automations</h1>
            <small class="text-muted">Rule-based actions for created, updated, commented, and overdue tasks.</small>
        </div>
        <a href="{{ route('task-settings.automations.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Rule
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Trigger</th>
                        <th>Scope</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $rule->name }}</div>
                            <div class="small text-muted">{{ collect($rule->actions ?? [])->keys()->implode(', ') ?: 'No actions' }}</div>
                        </td>
                        <td>{{ str_replace('_', ' ', ucfirst($rule->trigger_event)) }}</td>
                        <td>{{ $rule->taskList?->name ?? 'All task lists' }}</td>
                        <td>
                            <span class="badge {{ $rule->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('task-settings.automations.edit', $rule) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('task-settings.automations.destroy', $rule) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No automation rules yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
