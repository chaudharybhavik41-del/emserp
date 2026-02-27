<p>Dear {{ optional($order->vendor)->name }},</p>

<p>
    Please find attached the Purchase Order
    <strong>{{ $order->code }}</strong>
    for your kind attention.
</p>

<p>
    Project:
    @if($order->project)
        {{ $order->project->code }} - {{ $order->project->name }}
    @else
        N/A
    @endif
    <br>
    PO Date: {{ optional($order->po_date)?->format('d-m-Y') ?? '-' }}<br>
    Expected Delivery: {{ optional($order->expected_delivery_date)?->format('d-m-Y') ?? '-' }}
</p>

<p>
    Kindly acknowledge receipt of this Purchase Order and arrange supply as per the terms and conditions.
</p>

<p>Regards,<br>
{{ optional($order->creator)->name ?? '' }}<br>
(Purchase)</p>
