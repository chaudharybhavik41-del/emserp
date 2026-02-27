@extends('layouts.erp')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">BOM Templates</h4>
    @can('project.bom_template.create')
        <a href="{{ route('bom-templates.create') }}" class="btn btn-primary btn-sm">
            New Template
        </a>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('bom-templates.index') }}" class="row g-2">
            <div class="col-md-4">
                <input type="text"
                       name="q"
                       value="{{ request('q') }}"
                       class="form-control"
                       placeholder="Search code or name">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">-- Status --</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input type="text"
                       name="structure_type"
                       value="{{ request('structure_type') }}"
                       class="form-control"
                       placeholder="Structure type (GIRDER, POLE, ...)">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-secondary">
                    Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Template Code</th>
                        <th>Name</th>
                        <th>Structure Type</th>
                        <th>Status</th>
                        <th>Total Weight (kg)</th>
                        <th>Created By</th>
                        <th>Created At</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $template)
                        <tr>
                            <td>{{ $template->template_code }}</td>
                            <td>{{ $template->name }}</td>
                            <td>{{ $template->structure_type ?? '-' }}</td>
                            <td>{{ ucfirst($template->status) }}</td>
                            <td>{{ number_format($template->total_weight, 3) }}</td>
                            <td>{{ $template->creator?->name ?? '-' }}</td>
                            <td>{{ $template->created_at?->format('d-m-Y') }}</td>
                            <td>
                                @can('project.bom_template.view')
                                    <a href="{{ route('bom-templates.show', $template) }}"
                                       class="btn btn-sm btn-outline-primary mb-1">
                                        View
                                    </a>
                                @endcan
                                @can('project.bom_template.update')
                                    <a href="{{ route('bom-templates.edit', $template) }}"
                                       class="btn btn-sm btn-outline-secondary mb-1">
                                        Edit
                                    </a>
                                @endcan
                                @can('project.bom_template.delete')
                                    <form action="{{ route('bom-templates.destroy', $template) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('Delete this template?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger mb-1">
                                            Delete
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-3">
                                No templates found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-2">
            {{ $templates->links() }}
        </div>
    </div>
</div>
@endsection
