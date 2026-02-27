@extends('layouts.erp')

@section('title', 'Parties')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Parties</h1>

    @can('core.party.create')
        <a href="{{ route('parties.create') }}" class="btn btn-primary btn-sm">
            + Add Party
        </a>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('parties.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="q" class="form-label">Search</label>
                <input type="text"
                       id="q"
                       name="q"
                       value="{{ request('q') }}"
                       class="form-control"
                       placeholder="Code / name / GSTIN">
            </div>
            <div class="col-md-6">
                <label class="form-label d-block">Type</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input"
                           type="checkbox"
                           id="is_supplier"
                           name="is_supplier"
                           value="1"
                           {{ request('is_supplier') ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_supplier">Supplier</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input"
                           type="checkbox"
                           id="is_contractor"
                           name="is_contractor"
                           value="1"
                           {{ request('is_contractor') ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_contractor">Contractor</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input"
                           type="checkbox"
                           id="is_client"
                           name="is_client"
                           value="1"
                           {{ request('is_client') ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_client">Client</label>
                </div>
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
                <th style="width: 18%">GSTIN</th>
                <th style="width: 18%">Primary Contact</th>
                <th style="width: 12%">Type</th>
                <th style="width: 8%">Active</th>
                <th style="width: 15%" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($parties as $party)
                <tr>
                    <td>{{ $party->code }}</td>
                    <td>
                        {{ $party->name }}
                        @if($party->legal_name && $party->legal_name !== $party->name)
                            <div class="text-muted small">{{ $party->legal_name }}</div>
                        @endif
                    </td>
                    <td>
                        {{ $party->gstin }}
                        @if(($party->branches_count ?? 0) > 0)
                            <span class="badge text-bg-light ms-1">+{{ $party->branches_count }}</span>
                        @endif
                    </td>
                    <td>
                        @if($party->primary_phone || $party->primary_email)
                            <div>{{ $party->primary_phone }}</div>
                            <div class="text-muted small">{{ $party->primary_email }}</div>
                        @else
                            <span class="text-muted small">No primary contact</span>
                        @endif
                    </td>
                    <td>
                        @if($party->is_supplier)
                            <span class="badge text-bg-info">Supplier</span>
                        @endif
                        @if($party->is_contractor)
                            <span class="badge text-bg-warning">Contractor</span>
                        @endif
                        @if($party->is_client)
                            <span class="badge text-bg-success">Client</span>
                        @endif
                    </td>
                    <td>
                        @if($party->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('core.party.view')
                            <a href="{{ route('parties.show', $party) }}"
                               class="btn btn-sm btn-outline-secondary">
                                View
                            </a>
                        @endcan

                        @can('core.party.update')
                            <a href="{{ route('parties.edit', $party) }}"
                               class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>
                        @endcan

                        @can('core.party.delete')
                            <form action="{{ route('parties.destroy', $party) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this party?');">
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
                    <td colspan="7" class="text-center text-muted py-3">
                        No parties found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($parties->hasPages())
        <div class="card-footer">
            {{ $parties->links() }}
        </div>
    @endif
</div>
@endsection



