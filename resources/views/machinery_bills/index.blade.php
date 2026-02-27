@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-receipt"></i> Machinery Bills</h2>
    </div>

    @if(!empty($machineryTypeMissing))
        <div class="alert alert-warning">
            <strong>Setup missing:</strong> MaterialType with code <code>MACHINERY</code> was not found.
            Please create it (or run the seeders), then reload this page.
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('machinery-bills.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text"
                           name="q"
                           value="{{ request('q') }}"
                           class="form-control form-control-sm"
                           placeholder="Bill no / invoice no">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Supplier / Contractor</label>
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">-- All --</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>
                                {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Bill Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- All --</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                        <option value="posted" @selected(request('status') === 'posted')>Posted</option>
                        <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
                    <a href="{{ route('machinery-bills.index') }}" class="btn btn-link btn-sm">Reset</a>
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
                            <th style="width: 16%">Bill No</th>
                            <th style="width: 12%">Bill Date</th>
                            <th>Supplier</th>
                            <th style="width: 10%">Status</th>
                            <th style="width: 10%" class="text-end">Mach Qty</th>
                            <th style="width: 10%" class="text-end">Generated</th>
                            <th style="width: 10%" class="text-end">Pending</th>
                            <th style="width: 22%" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bills as $bill)
                            @php
                                $qty = (int) ($machineryQtyByBill[$bill->id] ?? 0);
                                $generated = (int) ($generatedCounts[$bill->id] ?? 0);
                                $pending = max(0, $qty - $generated);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $bill->bill_number }}</div>
                                    @if($bill->invoice_number)
                                        <div class="small text-muted">Inv: {{ $bill->invoice_number }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $bill->bill_date?->format('d-m-Y') }}</div>
                                    @if($bill->posting_date)
                                        <div class="small text-muted">Post: {{ $bill->posting_date->format('d-m-Y') }}</div>
                                    @endif
                                </td>
                                <td>{{ $bill->supplier?->name }}</td>
                                <td>
                                    @if($bill->status === 'posted')
                                        <span class="badge text-bg-success">Posted</span>
                                    @elseif($bill->status === 'cancelled')
                                        <span class="badge text-bg-danger">Cancelled</span>
                                    @else
                                        <span class="badge text-bg-secondary">Draft</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ $qty }}</td>
                                <td class="text-end">{{ $generated }}</td>
                                <td class="text-end">
                                    @if($pending > 0)
                                        <span class="badge text-bg-warning">{{ $pending }}</span>
                                    @else
                                        <span class="badge text-bg-success">0</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('machinery-bills.show', $bill) }}"
                                       class="btn btn-sm btn-outline-secondary">View</a>

                                    @if(\Illuminate\Support\Facades\Route::has('purchase.bills.show'))
                                        <a href="{{ route('purchase.bills.show', $bill) }}"
                                           class="btn btn-sm btn-outline-info ms-1">Purchase Bill</a>
                                    @endif

                                    @can('machinery.machine.create')
                                        @if($bill->status === 'posted' && $pending > 0)
                                            <form action="{{ route('machinery-bills.generate', $bill) }}"
                                                  method="POST"
                                                  class="d-inline ms-1"
                                                  onsubmit="return confirm('Generate missing machines for this bill?');">
                                                @csrf
                                                <button class="btn btn-sm btn-primary">
                                                    <i class="bi bi-cpu"></i> Generate
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">No machinery bills found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($bills, 'hasPages') && $bills->hasPages())
            <div class="card-footer">
                {{ $bills->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
