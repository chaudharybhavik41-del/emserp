@extends('layouts.vendor')

@section('title', 'Inquiry Closed')

@section('content')
    <div class="card">
        <div class="card-h">Inquiry Closed</div>
        <div class="card-b">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;">
                <div>
                    <div style="font-weight:900;font-size:16px;">RFQ: {{ $rfq->code }}</div>
                    <div class="muted small" style="margin-top:4px;">
                        Status: <strong>{{ strtoupper($rfq->status) }}</strong>
                        @if(!empty($linkExpiresAt))
                            • Link Valid Till: <strong>{{ $linkExpiresAt->format('d-m-Y H:i') }}</strong>
                        @endif
                    </div>
                </div>

                <div>
                    @if(($rfq->status ?? null) === 'po_generated')
                        <span class="badge success">PO Generated</span>
                    @elseif(($rfq->status ?? null) === 'closed')
                        <span class="badge warn">Closed</span>
                    @elseif(($rfq->status ?? null) === 'cancelled')
                        <span class="badge danger">Cancelled</span>
                    @else
                        <span class="badge">Not Accepting</span>
                    @endif
                </div>
            </div>

            <div class="alert info" style="margin-top:14px;">
                <div style="font-weight:900;margin-bottom:6px;">Message</div>

                @if(!empty($closedReason))
                    <div>{{ $closedReason }}</div>
                @else
                    <div>This RFQ is not accepting quotations on this link.</div>
                @endif

                <div class="small muted" style="margin-top:8px;">
                    If you need to submit a revised quotation, please contact the Purchase team for a fresh link.
                </div>
            </div>
        </div>
    </div>
@endsection
