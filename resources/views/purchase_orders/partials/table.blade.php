<div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>PO No</th>
                <th>Vendor</th>
                <th>Project</th>
                <th>PO Date</th>
                <th>Expected</th>
                <th>Status</th>
                <th class="text-end">Total</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                <tr>
                    <td>{{ $order->code }}</td>
                    <td>{{ optional($order->vendor)->name }}</td>
                    <td>@if($order->project)
                        {{ $order->project->code }} - {{ $order->project->name }}
                    @else
                        <span class="text-muted">-</span>
                    @endif</td>

                    <td>{{ optional($order->po_date)?->format('d-m-Y') }}</td>
                    <td>{{ optional(value: $order->expected_delivery_date)?->format('d-m-Y') }}</td>
                     <td>
                                        <span class="badge
                                            @if($order->status === 'approved')
                                                bg-success
                                            @elseif($order->status === 'cancelled')
                                                bg-danger
                                            @else
                                                bg-secondary
                                            @endif
                                        ">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </td>

                    <td class="text-end">{{ number_format($order->total_amount, 2) }}</td>
                    <td class="text-end">
                        <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">
                            Open
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-3">
                        No purchase orders found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="p-2">
    {{ $orders->links() }}
</div>