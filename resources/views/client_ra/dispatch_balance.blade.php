@extends('layouts.erp')

@section('title', 'Client Dispatch Billing Balance')

@section('content')
<div class="container-fluid">
    @include('partials.alerts')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Client Dispatch Billing Balance</h1>
            <div class="small text-muted">Finalized V2 dispatch lines vs billed RA qty</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('accounting.client-ra.index') }}" class="btn btn-outline-secondary btn-sm">Client Billing</a>
            <a href="{{ route('accounting.client-ra.create') }}" class="btn btn-primary btn-sm">+ New Client Bill</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Client</label>
                    <select name="client_id" class="form-select form-select-sm">
                        <option value="">-- All --</option>
                        @foreach($clients as $p)
                            <option value="{{ $p->id }}" @selected(request('client_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All --</option>
                        @foreach($projects as $proj)
                            <option value="{{ $proj->id }}" @selected(request('project_id') == $proj->id)>{{ $proj->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Billing Status</label>
                    <select name="billing_status" class="form-select form-select-sm">
                        <option value="">-- All --</option>
                        <option value="unbilled" @selected(request('billing_status') === 'unbilled')>Unbilled</option>
                        <option value="partial" @selected(request('billing_status') === 'partial')>Partially Billed</option>
                        <option value="fully_billed" @selected(request('billing_status') === 'fully_billed')>Fully Billed</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
                </div>

                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
                </div>

                <div class="col-12 mt-2">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
                    <a href="{{ route('accounting.client-ra.dispatch-balance') }}" class="btn btn-link btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 12%">Dispatch</th>
                        <th style="width: 10%">Date</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th style="width: 14%">Assembly</th>
                        <th>Client Billing Parts</th>
                        <th class="text-end" style="width: 8%">Qty</th>
                        <th class="text-end" style="width: 8%">Billed</th>
                        <th class="text-end" style="width: 8%">Balance</th>
                        <th style="width: 10%">Status</th>
                        <th class="text-end" style="width: 12%">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row->dispatch_number }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->dispatch_date)->format('d-m-Y') }}</td>
                            <td>{{ $row->client_name ?: '-' }}</td>
                            <td>{{ $row->project_name }}</td>
                            <td>
                                <div class="fw-semibold">{{ $row->assembly_code_snapshot ?: '-' }}</div>
                                <div class="small text-muted">{{ $row->assembly_name_snapshot ?: '-' }}</div>
                            </td>
                            <td>{{ $row->client_dispatch_description_snapshot ?: '-' }}</td>
                            <td class="text-end">{{ number_format((float) $row->dispatch_qty, 3) }}</td>
                            <td class="text-end">{{ number_format((float) $row->billed_qty, 3) }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $row->remaining_qty, 3) }}</td>
                            <td>
                                @php
                                    $badge = match($row->billing_status) {
                                        'fully_billed' => 'success',
                                        'partial' => 'warning',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $badge }}">{{ ucwords(str_replace('_', ' ', $row->billing_status)) }}</span>
                            </td>
                            <td class="text-end">
                                @if((float) $row->remaining_qty > 0.0001)
                                    <a href="{{ route('accounting.client-ra.create', ['client_id' => $row->client_party_id, 'project_id' => $row->project_id, 'production_v2_dispatch_id' => $row->dispatch_id]) }}" class="btn btn-sm btn-outline-primary">Bill</a>
                                @else
                                    <span class="text-muted small">Done</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-3">No finalized dispatch billing rows found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($rows->hasPages())
            <div class="card-footer">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
