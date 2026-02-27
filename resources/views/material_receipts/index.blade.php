@extends('layouts.erp')

@section('title', 'Material Receipts (GRN)')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Material Receipts (GRN)</h1>
        @can('store.material_receipt.create')
            <a href="{{ route('material-receipts.create') }}" class="btn btn-sm btn-primary">
                New GRN
            </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 12%">GRN No</th>
                        <th style="width: 10%">Date</th>
                        <th style="width: 20%">PO No</th>
                        <th style="width: 20%">Supplier / Client</th>
                        <th style="width: 20%">Project</th>
                        <th style="width: 12%">Invoice</th>
                        <th style="width: 10%">Type</th>
                        <th style="width: 8%">Status</th>
                        <th style="width: 8%"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($receipts as $receipt)
                        <tr>
                            <td>{{ $receipt->receipt_number }}</td>
                            <td>{{ optional($receipt->receipt_date)->format('d-m-Y') }}</td>

                            {{-- PO No column --}}
                            <td>
                                @if($receipt->purchaseOrder)
                                    <a href="{{ route('purchase-orders.show', $receipt->purchaseOrder) }}">
                                        {{ $receipt->purchaseOrder->code }}
                                    </a>
                                @else
                                    {{ $receipt->po_number }}
                                @endif
                            </td>

                            {{-- Supplier / Client column --}}
                            <td>
                                @if($receipt->is_client_material && $receipt->client)
                                    {{ $receipt->client->name }}
                                @elseif($receipt->supplier)
                                    {{ $receipt->supplier->name }}
                                @else
                                    -
                                @endif
                            </td>

                            <td>
                                @if($receipt->project)
                                    {{ $receipt->project->code }} - {{ $receipt->project->name }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($receipt->invoice_number)
                                    {{ $receipt->invoice_number }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                {{ $receipt->is_client_material ? 'Client Material' : 'Own Material' }}
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ strtoupper($receipt->status) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('material-receipts.show', $receipt) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-3">
                                No GRNs recorded yet.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($receipts->hasPages())
            <div class="card-footer pb-0">
                {{ $receipts->links() }}
            </div>
        @endif
    </div>
@endsection
