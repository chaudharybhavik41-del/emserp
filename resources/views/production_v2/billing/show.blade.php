@extends('layouts.erp')

@section('title', 'Production V2 Bill')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $bill->bill_number }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.billing.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        @if($bill->isDraft() && auth()->user()?->can('production.billing.update'))
            <form method="post" action="{{ route('projects.production-v2.billing.finalize', ['project' => $project->id, 'bill' => $bill->id]) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-success">Finalize</button>
            </form>
            <form method="post" action="{{ route('projects.production-v2.billing.cancel', ['project' => $project->id, 'bill' => $bill->id]) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
            </form>
        @endif
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'billing'])

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Bill Date</div><div class="h5 mb-0">{{ $bill->bill_date?->format('Y-m-d') ?: '-' }}</div></div></div></div>
        <div class="col-12 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Status</div><div class="h5 mb-0">{{ ucfirst($bill->status) }}</div></div></div></div>
        <div class="col-12 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Subtotal</div><div class="h5 mb-0">{{ number_format((float) $bill->subtotal, 2) }}</div></div></div></div>
        <div class="col-12 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Grand Total</div><div class="h5 mb-0">{{ number_format((float) $bill->grand_total, 2) }}</div></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4"><div class="small text-body-secondary">Contractor</div><div>{{ $bill->contractor?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-4"><div class="small text-body-secondary">Period</div><div>{{ $bill->period_from?->format('Y-m-d') ?: '-' }} to {{ $bill->period_to?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">GST Type</div><div>{{ strtoupper($bill->gst_type) }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">GST Rate</div><div>{{ number_format((float) $bill->gst_rate, 2) }}%</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">CGST</div><div>{{ number_format((float) $bill->cgst_total, 2) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">SGST</div><div>{{ number_format((float) $bill->sgst_total, 2) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">IGST</div><div>{{ number_format((float) $bill->igst_total, 2) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Finalized By</div><div>{{ $bill->finalizedBy?->name ?: '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $bill->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Bill Lines</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Description</th>
                            <th>Source</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Tax</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($bill->lines as $line)
                        <tr>
                            <td>{{ $line->description }}</td>
                            <td>
                                {{ strtoupper($line->source_type) }}
                                @if($line->operationMaster)
                                    <div class="small text-body-secondary">{{ $line->operationMaster->name }}</div>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $line->qty, 3) }} {{ $line->qtyUom?->code ?: '' }}</td>
                            <td class="text-end">{{ number_format((float) $line->rate, 2) }} {{ $line->rateUom?->code ?: '' }}</td>
                            <td class="text-end">{{ number_format((float) $line->amount, 2) }}</td>
                            <td class="text-end">{{ number_format((float) ($line->cgst_amount + $line->sgst_amount + $line->igst_amount), 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No bill lines found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
