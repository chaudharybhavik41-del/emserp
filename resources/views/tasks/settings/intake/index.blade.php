@extends('layouts.erp')

@section('title', 'Task Intake Forms')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0"><i class="bi bi-ui-checks-grid me-2"></i>Task Intake Forms</h1>
            <small class="text-muted">Structured request intake that creates tasks directly.</small>
        </div>
        <a href="{{ route('task-settings.intake-forms.create') }}" class="btn btn-primary">New Intake Form</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>List</th>
                        <th>Access</th>
                        <th>Link</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($forms as $form)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $form->name }}</div>
                            <div class="small text-muted">{{ $form->description }}</div>
                        </td>
                        <td>{{ $form->taskList->name }}</td>
                        <td>{{ $form->requires_auth ? 'Authenticated' : 'Open' }}</td>
                        <td><a href="{{ route('tasks.intake.show', $form->slug) }}" target="_blank">{{ route('tasks.intake.show', $form->slug) }}</a></td>
                        <td class="text-end">
                            <a href="{{ route('task-settings.intake-forms.edit', $form) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('task-settings.intake-forms.destroy', $form) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No intake forms yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
