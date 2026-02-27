<p>Dear {{ optional($vendor->vendor)->name }},</p>

<p>Please find attached the Request for Quotation (RFQ) <strong>{{ $rfq->code }}</strong> for your kind consideration.</p>

<p>
    Project:
    @if($rfq->project)
        {{ $rfq->project->code }} - {{ $rfq->project->name }}
    @else
        N/A
    @endif
    <br>
    RFQ Date: {{ optional($rfq->rfq_date)?->format('d-m-Y') ?? '-' }}<br>
    Due Date for quotation: {{ optional($rfq->due_date)?->format('d-m-Y') ?? '-' }}
</p>

<p>
    Kindly submit your quotation by the due date, mentioning RFQ no.
    <strong>{{ $rfq->code }}</strong> in all your communication.
</p>

<p>Regards,<br>
{{ optional($rfq->creator)->name ?? '' }}<br>
(Purchase)</p>
