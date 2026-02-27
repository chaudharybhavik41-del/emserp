@extends('layouts.erp')

@section('title', 'Quotations')

@section('content')
@php
    $currencySymbol = config('crm.currency_symbol', '₹');
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Quotations</h1>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('crm.quotations.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="code" class="form-label">Quotation Code</label>
                <input type="text"
                       id="code"
                       name="code"
                       value="{{ request('code') }}"
                       class="form-control"
                       placeholder="QTN-2025-0001">
            </div>

            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    @foreach(['draft','sent','accepted','rejected','superseded'] as $status)
                        <option value="{{ $status }}"
                            {{ request('status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label for="party_id" class="form-label">Client</label>
                <select id="party_id" name="party_id" class="form-select">
                    <option value="">All clients</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}"
                            {{ (string) $client->id === request('party_id') ? 'selected' : '' }}>
                            {{ $client->code }} - {{ $client->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-outline-primary">
                    Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-sm mb-0 table-hover align-middle">
            <thead class="table-light">
            <tr>
                <th style="width: 16%">Code / Rev</th>
                <th>Project / Lead</th>
                <th style="width: 18%">Client</th>
                <th style="width: 10%">Status</th>
                <th style="width: 12%" class="text-end">Total ({{ $currencySymbol }})</th>
                <th style="width: 18%" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($quotations as $q)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $q->code }}</div>
                        <div class="text-muted small">Rev {{ $q->revision_no }}</div>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $q->project_name }}</div>
                        @if($q->lead)
                            <div class="text-muted small">
                                Lead: {{ $q->lead->code ?? ('#'.$q->lead->id) }} – {{ $q->lead->title }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($q->party)
                            <div class="fw-semibold">{{ $q->party->name }}</div>
                            <div class="text-muted small">{{ $q->party->code }}</div>
                        @else
                            <span class="text-muted small">N/A</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $status = $q->status;
                            $badgeClass = match ($status) {
                                'draft'      => 'text-bg-secondary',
                                'sent'       => 'text-bg-info',
                                'accepted'   => 'text-bg-success',
                                'rejected'   => 'text-bg-danger',
                                'superseded' => 'text-bg-warning',
                                default      => 'text-bg-light',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">
                            {{ ucfirst($status) }}
                        </span>
                    </td>
                    <td class="text-end">
                        {{ $currencySymbol }} {{ number_format($q->grand_total, 2) }}
                    </td>
                    <td class="text-end">
                        @can('crm.quotation.view')
                            <a href="{{ route('crm.quotations.show', $q) }}"
                               class="btn btn-sm btn-outline-secondary">
                                View
                            </a>
                        @endcan

                        @can('crm.quotation.update')
                            <a href="{{ route('crm.quotations.edit', $q) }}"
                               class="btn btn-sm btn-outline-primary ms-1">
                                Edit
                            </a>
                        @endcan

                        @can('crm.quotation.delete')
                            <form action="{{ route('crm.quotations.destroy', $q) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this quotation?');">
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
                    <td colspan="6" class="text-center text-muted py-3">
                        No quotations found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($quotations->hasPages())
        <div class="card-footer">
            {{ $quotations->links() }}
        </div>
    @endif
</div>
@endsection


