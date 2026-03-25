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
            <th style="width: 10%">Accounting</th>
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
                <td>
                    @if($receipt->is_client_material)
                        <span class="badge bg-light text-dark border">N/A</span>
                    @elseif(($receipt->billing_status ?? null) === 'billed')
                        <span class="badge bg-success">BILLED</span>
                    @else
                        <span class="badge bg-warning text-dark">UNBILLED</span>
                    @endif
                </td>
                <td class="text-end pe-3">
                    <a href="{{ route('material-receipts.show', $receipt) }}"
                       class="btn btn-sm btn-outline-secondary">
                        View
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="text-center text-muted py-3">
                    No GRNs recorded yet.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($receipts->hasPages())
    <div class="card-footer pb-0 border-top-0">
        {{ $receipts->links() }}
    </div>
@endif
