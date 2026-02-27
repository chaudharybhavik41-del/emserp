@extends('layouts.erp')

@section('title', 'Client RA Bills')

@section('content')
<div class="container-fluid">
    @include('partials.alerts')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Client RA Bills (Sales Invoices)</h1>

        <a href="{{ route('accounting.client-ra.create') }}" class="btn btn-primary btn-sm">
            + New Client RA Bill
        </a>
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
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- All --</option>
                        @foreach(['draft','submitted','approved','posted','rejected'] as $st)
                            <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
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
                    <a href="{{ route('accounting.client-ra.index') }}" class="btn btn-link btn-sm">Reset</a>
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
                        <th style="width: 16%">RA No</th>
                        <th style="width: 10%">Date</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th class="text-end" style="width: 11%">Net</th>
                        <th class="text-end" style="width: 10%">GST</th>
                        <th class="text-end" style="width: 10%">TDS</th>
                        <th class="text-end" style="width: 12%">Invoice</th>
                        <th style="width: 9%">Status</th>
                        <th class="text-end" style="width: 14%">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($raBills as $bill)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $bill->ra_number }}</div>
                                @if($bill->invoice_number)
                                    <div class="small text-muted">Invoice: {{ $bill->invoice_number }}</div>
                                @endif
                                @if($bill->status === 'posted' && $bill->voucher)
                                    <div class="small text-muted">Voucher: {{ $bill->voucher->voucher_no ?? ('#' . $bill->voucher->id) }}</div>
                                @endif
                            </td>
                            <td>{{ $bill->bill_date?->format('d-m-Y') }}</td>
                            <td>{{ $bill->client?->name }}</td>
                            <td>{{ $bill->project?->name }}</td>
                            <td class="text-end">{{ number_format((float) $bill->net_amount, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $bill->total_gst, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $bill->tds_amount, 2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $bill->total_amount, 2) }}</td>
                            <td>
                                @php
                                    $badge = match($bill->status) {
                                        'posted'    => 'success',
                                        'approved'  => 'primary',
                                        'submitted' => 'warning',
                                        'rejected'  => 'danger',
                                        default     => 'secondary',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $badge }}">{{ ucfirst($bill->status) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('accounting.client-ra.show', $bill) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                @if($bill->status !== 'posted')
                                    <a href="{{ route('accounting.client-ra.edit', $bill) }}" class="btn btn-sm btn-outline-primary ms-1">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-3">No client RA bills found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($raBills->hasPages())
            <div class="card-footer">
                {{ $raBills->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
