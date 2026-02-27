@extends('layouts.erp')

@section('title', 'CRM - Quotation Breakup Templates')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h4 mb-0">Quotation Breakup Templates</h1>
            <div class="text-muted small">
                Templates are used to prefill the <b>Cost Breakup / Rate Analysis</b> modal while preparing quotations.
            </div>
        </div>

        <a href="{{ route('crm.quotation-breakup-templates.create') }}" class="btn btn-sm btn-primary">
            Add Template
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small mb-1">Search</label>
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Code / name / text" value="{{ $q ?? request('q') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="" @selected(($status ?? request('status')) === '')>All</option>
                        <option value="active" @selected(($status ?? request('status')) === 'active')>Active</option>
                        <option value="inactive" @selected(($status ?? request('status')) === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
                    <a href="{{ route('crm.quotation-breakup-templates.index') }}" class="btn btn-sm btn-outline-light">Reset</a>
                </div>
            </form>

            <hr>

            <div class="small text-muted">
                <b>Template format:</b> one component per line. Optional format: <code>Name|basis|rate</code>
                (basis = per_unit / lumpsum / percent).
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Default</th>
                    <th>Active</th>
                    <th>Sort</th>
                    <th class="text-end" style="width: 200px;">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($templates as $template)
                    <tr>
                        <td>{{ $template->code }}</td>
                        <td>{{ $template->name }}</td>
                        <td>
                            @if($template->is_default)
                                <span class="badge bg-success">Default</span>
                            @endif
                        </td>
                        <td>
                            @if($template->is_active)
                                <span class="badge bg-primary">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $template->sort_order }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('crm.quotation-breakup-templates.show', $template) }}"
                                   class="btn btn-sm btn-outline-secondary">View</a>

                                <a href="{{ route('crm.quotation-breakup-templates.edit', $template) }}"
                                   class="btn btn-sm btn-outline-primary">Edit</a>

                                <form action="{{ route('crm.quotation-breakup-templates.destroy', $template) }}" method="POST"
                                      onsubmit="return confirm('Deactivate this template?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Deactivate</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-3">No templates found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($templates, 'hasPages') && $templates->hasPages())
            <div class="card-footer py-2">
                {{ $templates->withQueryString()->links() }}
            </div>
        @endif
    </div>
@endsection
