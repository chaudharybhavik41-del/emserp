@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-receipt"></i> Machinery Bill: {{ $bill->bill_number }}</h2>
            <div class="text-muted small">
                Supplier: <strong>{{ $bill->supplier?->name ?? '—' }}</strong>
                • Bill Date: <strong>{{ $bill->bill_date?->format('d-m-Y') ?? '—' }}</strong>
                @if($bill->invoice_number)
                    • Invoice: <strong>{{ $bill->invoice_number }}</strong>
                @endif
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('machinery-bills.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>

            @if(\Illuminate\Support\Facades\Route::has('purchase.bills.show'))
                <a href="{{ route('purchase.bills.show', $bill) }}" class="btn btn-outline-info">
                    <i class="bi bi-file-earmark-text"></i> Open Purchase Bill
                </a>
            @endif

            @can('machinery.machine.create')
                @if($bill->status === 'posted' && ($summary['pending_qty'] ?? 0) > 0)
                    <form action="{{ route('machinery-bills.generate', $bill) }}"
                          method="POST"
                          onsubmit="return confirm('Generate missing machines for this bill?');">
                        @csrf
                        <button class="btn btn-primary">
                            <i class="bi bi-cpu"></i> Generate Missing Machines
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted">Machinery Qty</div>
                    <div class="fs-4 fw-bold">{{ $summary['machinery_qty'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted">Machines Generated</div>
                    <div class="fs-4 fw-bold">{{ $summary['generated_qty'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted">Pending</div>
                    <div class="fs-4 fw-bold">
                        @php $p = (int) ($summary['pending_qty'] ?? 0); @endphp
                        @if($p > 0)
                            <span class="badge text-bg-warning">{{ $p }}</span>
                        @else
                            <span class="badge text-bg-success">0</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Machinery Lines -->
    <div class="card mb-3">
        <div class="card-header"><strong>Machinery Items</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th style="width: 16%">Category</th>
                            <th style="width: 8%" class="text-end">Qty</th>
                            <th style="width: 12%" class="text-end">Taxable</th>
                            <th style="width: 12%" class="text-end">Total</th>
                            <th style="width: 10%" class="text-end">Created</th>
                            <th style="width: 10%" class="text-end">Pending</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($machLines as $line)
                            @php
                                $qty = max(0, (int) round((float) $line->qty));
                                $created = isset($machinesByLine[$line->id]) ? $machinesByLine[$line->id]->count() : 0;
                                $pending = max(0, $qty - $created);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $line->item?->name ?? '—' }}</div>
                                    <div class="small text-muted">
                                        Line #{{ $line->id }}
                                        @if($line->item?->code)
                                            • Item Code: {{ $line->item->code }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        {{ $line->item?->category?->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-end fw-semibold">{{ $qty }}</td>
                                <td class="text-end">{{ number_format((float) ($line->taxable_amount ?? 0), 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) ($line->total_amount ?? 0), 2) }}</td>
                                <td class="text-end">{{ $created }}</td>
                                <td class="text-end">
                                    @if($pending > 0)
                                        <span class="badge text-bg-warning">{{ $pending }}</span>
                                    @else
                                        <span class="badge text-bg-success">0</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">No machinery lines found on this bill.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Generated Machines -->
    <div class="card">
        <div class="card-header"><strong>Generated Machines</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 18%">Code</th>
                            <th>Name</th>
                            <th style="width: 18%">Serial</th>
                            <th style="width: 12%">Treatment</th>
                            <th style="width: 16%" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allMachines as $m)
                            <tr>
                                <td>
                                    <a href="{{ route('machines.show', $m) }}" class="fw-semibold">
                                        {{ $m->code }}
                                    </a>
                                </td>
                                <td>{{ $m->name }}</td>
                                <td><small>{{ $m->serial_number }}</small></td>
                                <td>
                                    @if($m->accounting_treatment === 'tool_stock')
                                        <span class="badge text-bg-info">Tool</span>
                                    @else
                                        <span class="badge text-bg-secondary">Fixed Asset</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('machines.show', $m) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                    @can('machinery.machine.update')
                                        <a href="{{ route('machines.edit', $m) }}" class="btn btn-sm btn-outline-primary ms-1">Edit</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    No machines generated yet.
                                    @if($bill->status === 'posted')
                                        <div class="small">Use <strong>Generate Missing Machines</strong> to create them.</div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
