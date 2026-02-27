@extends('layouts.erp')

@section('title', 'RFQ Details')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1">RFQ: {{ $rfq->code }}</h1>
        <div class="text-muted small">
            Status: <span class="badge bg-secondary">{{ strtoupper($rfq->status) }}</span>
            @if($rfq->purchase_indent_id)
                | Indent:
                <a href="{{ route('purchase-indents.show', $rfq->purchase_indent_id) }}">
                    {{ $rfq->indent?->code ?? ('#'.$rfq->purchase_indent_id) }}
                </a>
            @endif
            | Project: {{ $rfq->project?->name ?? 'General / Store' }}
            | Department: {{ $rfq->department?->name ?? '-' }}
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="{{ route('purchase-rfqs.index') }}" class="btn btn-sm btn-secondary">Back</a>
        <a href="{{ route('purchase-rfqs.quotes.edit', $rfq) }}" class="btn btn-sm btn-primary">Quotes &amp; L1</a>

        @if(!in_array($rfq->status, ['po_generated','cancelled','closed']))
            @can('purchase.po.create')
                @php
                    $poAction = \Illuminate\Support\Facades\Route::has('purchase-orders.store-from-rfq')
                        ? route('purchase-orders.store-from-rfq', ['purchase_rfq' => $rfq->id])
                        : url('purchase-orders/from-rfq/' . $rfq->id);
                @endphp
                <form action="{{ $poAction }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Generate Purchase Order(s) based on current L1 selections?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">
                        Generate PO to L1 (Each Line)
                    </button>
                </form>
            @endcan
        @endif


        @if(!in_array($rfq->status, ['cancelled','closed']))
            <form action="{{ route('purchase-rfqs.send', $rfq) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    Send RFQ Emails
                </button>
            </form>

            <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    data-bs-toggle="collapse"
                    data-bs-target="#cancelBox"
                    aria-expanded="false"
                    aria-controls="cancelBox">
                Cancel RFQ
            </button>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="collapse mb-3" id="cancelBox">
    <div class="card card-body border-danger">
        <form action="{{ route('purchase-rfqs.cancel', $rfq) }}" method="POST">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-9">
                    <label class="form-label mb-1">Cancel Reason</label>
                    <input type="text" name="reason" class="form-control" required maxlength="255"
                           placeholder="Reason for cancellation">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-danger w-100" type="submit">Confirm Cancel</button>
                </div>
            </div>
            <div class="small text-muted mt-2">
                Rule: if any PO exists for this RFQ, cancel PO first.
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <strong>RFQ Summary</strong>
            </div>
            <div class="card-body">
                <div class="row small">
                    <div class="col-md-4"><span class="text-muted">Due Date:</span> {{ $rfq->due_date ? \Carbon\Carbon::parse($rfq->due_date)->format('d-m-Y') : '-' }}</div>
                    <div class="col-md-4"><span class="text-muted">Created:</span> {{ $rfq->created_at?->format('d-m-Y H:i') }}</div>
                    <div class="col-md-4"><span class="text-muted">Vendors:</span> {{ $rfq->vendors?->count() ?? 0 }}</div>
                </div>
                <hr>
                <div class="small text-muted">
                    Use <strong>Quotes &amp; L1</strong> to enter quotations, create revisions, compare vendors and select L1 per item.
                </div>
            </div>
        </div>

        @if(class_exists(\App\Models\PurchaseRfqActivity::class))
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Activity Log</strong>
                <span class="text-muted small">Latest first</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width: 160px;">Date</th>
                                <th style="width: 160px;">User</th>
                                <th style="width: 160px;">Action</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($activities ?? collect()) as $a)
                                <tr>
                                    <td class="small">{{ $a->created_at?->format('d-m-Y H:i') }}</td>
                                    <td class="small">{{ $a->user?->name ?? '-' }}</td>
                                    <td class="small"><span class="badge bg-light text-dark">{{ $a->action }}</span></td>
                                    <td class="small">{{ $a->message }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted small p-3">No activity yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <strong>Vendors</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rfq->vendors ?? [] as $v)
                                <tr>
                                    <td class="small">{{ $v->vendor?->name ?? ('Vendor #'.$v->vendor_id) }}</td>
                                    <td class="small text-end">
                                        <span class="badge bg-light text-dark">{{ strtoupper($v->status ?? 'DRAFT') }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted small p-3">No vendors added.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
