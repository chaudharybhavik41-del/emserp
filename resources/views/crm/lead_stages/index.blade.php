@extends('layouts.erp')

@section('title', 'CRM Lead Stages')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">CRM Lead Stages</h1>

    @can('crm.lead_stage.create')
        <a href="{{ route('crm.lead-stages.create') }}" class="btn btn-primary btn-sm">
            + Add Lead Stage
        </a>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('crm.lead-stages.index') }}" class="row g-2 align-items-end">
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
                <th style="width: 8%">Order</th>
                <th style="width: 8%">Won?</th>
                <th style="width: 8%">Lost?</th>
                <th style="width: 8%">Closed?</th>
                <th style="width: 8%">Active</th>
                <th style="width: 20%" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($stages as $stage)
                <tr>
                    <td>{{ $stage->code }}</td>
                    <td>{{ $stage->name }}</td>
                    <td>{{ $stage->sort_order }}</td>
                    <td>
                        @if($stage->is_won)
                            ✅
                        @endif
                    </td>
                    <td>
                        @if($stage->is_lost)
                            ✅
                        @endif
                    </td>
                    <td>
                        @if($stage->is_closed)
                            ✅
                        @endif
                    </td>
                    <td>
                        @if($stage->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('crm.lead_stage.update')
                            <a href="{{ route('crm.lead-stages.edit', $stage) }}"
                               class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>
                        @endcan

                        @can('crm.lead_stage.delete')
                            <form action="{{ route('crm.lead-stages.destroy', $stage) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this lead stage?');">
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
                    <td colspan="8" class="text-center text-muted py-3">
                        No lead stages found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($stages->hasPages())
        <div class="card-footer">
            {{ $stages->links() }}
        </div>
    @endif
</div>
@endsection
