@extends('layouts.erp')

@section('title', 'CRM Lead Sources')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">CRM Lead Sources</h1>

    @can('crm.lead_source.create')
        <a href="{{ route('crm.lead-sources.create') }}" class="btn btn-primary btn-sm">
            + Add Lead Source
        </a>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('crm.lead-sources.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="q">Search</label>
                <input type="text"
                       name="q"
                       id="q"
                       value="{{ request('q') }}"
                       class="form-control"
                       placeholder="Code / Name">
            </div>

            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">
                    Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th style="width: 10%">Code</th>
                <th>Name</th>
                <th style="width: 15%">Active</th>
                <th style="width: 20%" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($sources as $source)
                <tr>
                    <td>{{ $source->code }}</td>
                    <td>
                        {{ $source->name }}
                        @if($source->description)
                            <div class="text-muted small">{{ Str::limit($source->description, 80) }}</div>
                        @endif
                    </td>
                    <td>
                        @if($source->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('crm.lead_source.update')
                            <a href="{{ route('crm.lead-sources.edit', $source) }}"
                               class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>
                        @endcan

                        @can('crm.lead_source.delete')
                            <form action="{{ route('crm.lead-sources.destroy', $source) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this lead source?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger ms-1">
                                    Delete
                                </button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-3">
                        No lead sources found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($sources->hasPages())
        <div class="card-footer">
            {{ $sources->links() }}
        </div>
    @endif
</div>
@endsection
