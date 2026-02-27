@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">Purchase Debit Note: {{ $note->note_number }}</h4>
            <div class="text-muted small">Supplier: {{ $note->supplier?->name }} | Date: {{ optional($note->note_date)->format('Y-m-d') }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('accounting.purchase-debit-notes.index') }}" class="btn btn-light btn-sm">Back</a>
            @if($note->status==='draft')
                <a href="{{ route('accounting.purchase-debit-notes.edit', $note) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                <form method="POST" action="{{ route('accounting.purchase-debit-notes.post', $note) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-success btn-sm" onclick="return confirm('Post this Debit Note to accounts?')">Post</button>
                </form>
            @endif
            @if($note->status==='posted')
                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelDNModal">Cancel</button>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3"><strong>Status:</strong> {{ strtoupper($note->status) }}</div>
                <div class="col-md-3"><strong>Total:</strong> {{ number_format((float)$note->total_amount,2) }}</div>
                <div class="col-md-6"><strong>Voucher:</strong>
                    @if($note->voucher)
                        <a href="{{ route('accounting.vouchers.show', $note->voucher) }}">{{ $note->voucher->voucher_no }}</a>
                    @else
                        <span class="text-muted">Not posted</span>
                    @endif
                </div>
            </div>
            @if($note->reference)
                <div class="mt-2"><strong>Reference:</strong> {{ $note->reference }}</div>
            @endif
            @if($note->remarks)
                <div class="mt-2"><strong>Remarks:</strong> {{ $note->remarks }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Lines</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Account (Cr)</th>
                    <th>Description</th>
                    <th class="text-end">Basic</th>
                    <th class="text-end">CGST</th>
                    <th class="text-end">SGST</th>
                    <th class="text-end">IGST</th>
                    <th class="text-end">Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach($note->lines as $l)
                    <tr>
                        <td>{{ $l->line_no }}</td>
                        <td>{{ $l->account?->name }} ({{ $l->account?->code }})</td>
                        <td>{{ $l->description }}</td>
                        <td class="text-end">{{ number_format((float)$l->basic_amount,2) }}</td>
                        <td class="text-end">{{ number_format((float)$l->cgst_amount,2) }}</td>
                        <td class="text-end">{{ number_format((float)$l->sgst_amount,2) }}</td>
                        <td class="text-end">{{ number_format((float)$l->igst_amount,2) }}</td>
                        <td class="text-end">{{ number_format((float)$l->total_amount,2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($note->status==='posted')
    <div class="modal fade" id="cancelDNModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Cancel Debit Note</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('accounting.purchase-debit-notes.cancel', $note) }}">
                    @csrf
                    <div class="modal-body">
                        <label class="form-label form-label-sm">Reason</label>
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Optional">
                        <div class="text-muted small mt-2">This will create a reversal journal voucher and mark the note cancelled.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                        <button class="btn btn-danger btn-sm" onclick="return confirm('Cancel this debit note?')">Confirm Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
