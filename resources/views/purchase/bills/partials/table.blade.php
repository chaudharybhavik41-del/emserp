<div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 16%">Bill No</th>
                <th style="width: 12%">Posting / Bill</th>
                <th>Supplier</th>
                <th>Project</th>
                <th style="width: 11%" class="text-end">Basic</th>
                <th style="width: 11%" class="text-end">GST</th>
                <th style="width: 11%" class="text-end">Invoice Total</th>
                <th style="width: 10%" class="text-end">TDS</th>
                <th style="width: 11%" class="text-end">Net Payable</th>
                <th style="width: 9%">Status</th>
                <th style="width: 14%" class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bills as $bill)
                @php
                    $invoice = (float) ($bill->total_amount ?? 0);
                    $tcs = (float) ($bill->tcs_amount ?? 0);
                    $tds = (float) ($bill->tds_amount ?? 0);
                    $net = ($invoice + $tcs) - $tds;
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $bill->bill_number }}</div>
                        @if($bill->status === 'posted' && $bill->voucher)
                            <div class="small text-muted">Voucher:
                                {{ $bill->voucher->voucher_no ?? ('#' . $bill->voucher->id) }}</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ ($bill->posting_date ?: $bill->bill_date)?->format('d-m-Y') }}</div>
                        <div class="small text-muted">Bill: {{ $bill->bill_date?->format('d-m-Y') }}</div>
                    </td>
                    <td>{{ $bill->supplier?->name }}</td>
                    <td>
                        @php
                            $proj = $bill->project ?? $bill->purchaseOrder?->project;
                            $multi = false;

                            if (!$proj && $bill->relationLoaded('expenseLines')) {
                                $unique = $bill->expenseLines
                                    ->map(fn($l) => $l->project)
                                    ->filter()
                                    ->unique('id')
                                    ->values();
                                if ($unique->count() === 1) {
                                    $proj = $unique->first();
                                } elseif ($unique->count() > 1) {
                                    $multi = true;
                                }
                            }
                        @endphp
                        @if($proj)
                            <div class="fw-semibold">
                                {{ $proj->code }}
                                @if($multi)
                                    <span class="badge text-bg-info ms-1">Multiple</span>
                                @endif
                            </div>
                            <div class="small text-muted">{{ $proj->name }}</div>
                        @elseif($multi)
                            <span class="badge text-bg-info">Multiple</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end">{{ number_format((float) $bill->total_basic, 2) }}</td>
                    <td class="text-end">{{ number_format((float) $bill->total_tax, 2) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($invoice, 2) }}</td>
                    <td class="text-end">{{ number_format($tds, 2) }}</td>
                    <td class="text-end fw-bold">{{ number_format($net, 2) }}</td>
                    <td>
                        @if($bill->status === 'posted')
                            <span class="badge text-bg-success">Posted</span>
                        @elseif($bill->status === 'cancelled')
                            <span class="badge text-bg-danger">Cancelled</span>
                        @else
                            <span class="badge text-bg-secondary">Draft</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('purchase.bills.show', $bill) }}"
                            class="btn btn-sm btn-outline-secondary">View</a>

                        @if($bill->status !== 'posted')
                            <a href="{{ route('purchase.bills.edit', $bill) }}"
                                class="btn btn-sm btn-outline-primary ms-1">Edit</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center text-muted py-3">No purchase bills found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($bills->hasPages())
    <div class="p-2">
        {{ $bills->links() }}
    </div>
@endif