@extends('layouts.erp')

@section('content')
@php
    $taskIndexRoute = \Illuminate\Support\Facades\Route::has('tasks.index') ? 'tasks.index' : null;
    $taskCreateRoute = \Illuminate\Support\Facades\Route::has('tasks.create') ? 'tasks.create' : null;
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-receipt"></i> {{ $bill->bill_number }}</h2>
            <div class="text-muted small">
                Contractor: <span class="fw-semibold">{{ $bill->contractor?->name }}</span> |
                Period: {{ $bill->period_from?->format('Y-m-d') }} → {{ $bill->period_to?->format('Y-m-d') }} |
                Status:
                <span class="badge {{ $bill->status === 'finalized' ? 'text-bg-success' : ($bill->status === 'cancelled' ? 'text-bg-dark' : 'text-bg-secondary') }}">
                    {{ ucfirst($bill->status) }}
                </span>
            </div>
            <div class="text-muted small">
                @if((float)($bill->gst_rate ?? 0) > 0)
                    GST: {{ strtoupper(str_replace('_', '+', $bill->gst_type)) }} @ {{ number_format((float)$bill->gst_rate, 2) }}%
                @else
                    GST: <span class="badge text-bg-light">Not Applicable</span>
                    @if(empty(trim((string)($bill->contractor?->gstin ?? ''))))
                        <span class="text-muted">(Contractor GSTIN not set)</span>
                    @endif
                @endif

                @if($bill->finalized_at)
                    | Finalized: {{ $bill->finalized_at->format('Y-m-d H:i') }} ({{ $bill->finalizedBy?->name ?? '—' }})
                @endif
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('projects.production-billing.index', $project) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>

            @can('tasks.view')
                @if($taskIndexRoute)
                    <a href="{{ route($taskIndexRoute, ['project' => $project->id, 'q' => $bill->bill_number]) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-list-task"></i> Related Tasks
                    </a>
                @endif
            @endcan

            @can('tasks.create')
                @if($taskCreateRoute)
                    <a href="{{ route($taskCreateRoute, ['project' => $project->id, 'title' => 'Billing '. $bill->bill_number .' follow-up', 'description' => 'Linked from Billing '. $bill->bill_number]) }}" class="btn btn-outline-primary">
                        <i class="bi bi-plus-circle"></i> Add Task
                    </a>
                @endif
            @endcan

            @can('production.billing.update')
                @if($bill->status === 'draft')
                    <form method="POST" action="{{ route('projects.production-billing.finalize', [$project, $bill]) }}"
                          onsubmit="return confirm('Finalize this bill? It will be locked.');">
                        @csrf
                        <button class="btn btn-success">
                            <i class="bi bi-lock"></i> Finalize
                        </button>
                    </form>
                @endif

                @if($bill->status !== 'cancelled')
                    <form method="POST" action="{{ route('projects.production-billing.cancel', [$project, $bill]) }}"
                          onsubmit="return confirm('Cancel this bill?');">
                        @csrf
                        <button class="btn btn-outline-danger">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Subtotal</div>
                    <div class="fs-5 fw-semibold">₹ {{ number_format((float)$bill->subtotal, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Tax Total</div>
                    <div class="fs-5 fw-semibold">₹ {{ number_format((float)$bill->tax_total, 2) }}</div>
                    <div class="small text-muted">
                        CGST: ₹ {{ number_format((float)$bill->cgst_total, 2) }} |
                        SGST: ₹ {{ number_format((float)$bill->sgst_total, 2) }} |
                        IGST: ₹ {{ number_format((float)$bill->igst_total, 2) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Grand Total</div>
                    <div class="fs-5 fw-semibold">₹ {{ number_format((float)$bill->grand_total, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th class="text-end">Qty</th>
                        <th>UOM</th>
                        <th class="text-end">Rate</th>
                        <th>Rate UOM</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">GST</th>
                        <th class="text-end">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bill->lines as $ln)
                        <tr>
                            <td class="fw-semibold">{{ $ln->activity?->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float)$ln->qty, 3) }}</td>
                            <td>{{ $ln->qtyUom?->code ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float)$ln->rate, 2) }}</td>
                            <td>{{ $ln->rateUom?->code ?? '—' }}</td>
                            <td class="text-end">₹ {{ number_format((float)$ln->amount, 2) }}</td>
                            <td class="text-end">
                                @if((float)($bill->gst_rate ?? 0) <= 0)
                                    —
                                @else
                                    @if($bill->gst_type === 'igst')
                                        IGST ₹ {{ number_format((float)$ln->igst_amount, 2) }}
                                    @else
                                        CGST ₹ {{ number_format((float)$ln->cgst_amount, 2) }}<br>
                                        SGST ₹ {{ number_format((float)$ln->sgst_amount, 2) }}
                                    @endif
                                @endif
                            </td>
                            <td class="text-end">₹ {{ number_format((float)$ln->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="7" class="text-end">Grand Total</th>
                        <th class="text-end">₹ {{ number_format((float)$bill->grand_total, 2) }}</th>
                    </tr>
                </tfoot>
            </table>

            <div class="text-muted small">
                Note: Billing Qty is aligned with Rate UOM. If rate is per KG/MT, Qty is weight derived from Production Plan item planned weight.
            </div>
        </div>
    </div>
</div>
@endsection
