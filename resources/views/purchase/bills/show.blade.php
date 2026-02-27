@extends('layouts.erp')

@section('title', 'Purchase Bill Details')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">
                Purchase Bill - {{ $bill->bill_number }}
            </h1>
            <div class="small mt-1">
                @if($bill->status === 'posted')
                    <span class="badge text-bg-success me-2">Posted</span>
                    @if($bill->voucher)
                        <span class="badge text-bg-light border">
                            Voucher:
                            {{ $bill->voucher->voucher_no ?? ('#' . $bill->voucher->id) }}
                            @if($bill->voucher->voucher_date)
                                ({{ $bill->voucher->voucher_date->format('d-m-Y') }})
                            @endif
                        </span>
                    @endif
                @elseif($bill->status === 'cancelled')
                    <span class="badge text-bg-danger">Cancelled</span>
                @else
                    <span class="badge text-bg-secondary">Draft</span>
                @endif
            </div>
        </div>

        <div>
            <a href="{{ route('purchase.bills.edit', $bill) }}"
               class="btn btn-outline-primary btn-sm">Edit</a>
            <a href="{{ route('purchase.bills.index') }}"
               class="btn btn-outline-secondary btn-sm ms-1">Back</a>
        </div>
    </div>
		@include('purchase.bills._reverse_modal', ['bill' => $bill])
    {{-- Header details --}}
    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-2">Supplier</dt>
                <dd class="col-sm-4">{{ $bill->supplier?->name }}</dd>

                <dt class="col-sm-2">Bill No / Invoice Date</dt>
                <dd class="col-sm-4">
                    {{ $bill->bill_number }} &nbsp; | &nbsp;
                    {{ optional($bill->bill_date)->format('d-m-Y') }}
                </dd>

                <dt class="col-sm-2">Posting Date</dt>
                <dd class="col-sm-4">
                    {{ optional($bill->posting_date ?: $bill->bill_date)->format('d-m-Y') }}
                </dd>

                <dt class="col-sm-2">Invoice No</dt>
                <dd class="col-sm-4">{{ $bill->reference_no ?: '—' }}</dd>

                <dt class="col-sm-2">Challan No</dt>
                <dd class="col-sm-4">{{ $bill->challan_number ?: '—' }}</dd>

                <dt class="col-sm-2">Linked PO</dt>
                <dd class="col-sm-4">
                    @if($bill->purchaseOrder)
                        {{ $bill->purchaseOrder->code }}
                        @if(optional($bill->purchaseOrder->project)->name)
                            <span class="text-muted">| {{ $bill->purchaseOrder->project->name }}</span>
                        @endif
                    @else
                        —
                    @endif
                </dd>

                <dt class="col-sm-2">Project</dt>
                <dd class="col-sm-4">
                    @php
                        $proj = $bill->project ?? $bill->purchaseOrder?->project;

                        // Phase-B: if bill header project is empty, infer from expense line projects (if any)
                        if (!$proj) {
                            $lineProjects = $bill->expenseLines
                                ->map(fn($l) => $l->project)
                                ->filter()
                                ->unique('id')
                                ->values();
                            if ($lineProjects->count() === 1) {
                                $proj = $lineProjects->first();
                            }
                        }
                    @endphp
                    @if($proj)
                        {{ $proj->code }} <span class="text-muted">|</span> {{ $proj->name }}
                        @php
                            $uniqueProjects = $bill->expenseLines
                                ->map(fn($l) => $l->project)
                                ->filter()
                                ->unique('id')
                                ->count();
                        @endphp
                        @if(!$bill->project && $uniqueProjects > 1)
                            <span class="badge text-bg-info ms-2">Multiple</span>
                        @endif
                    @else
                        —
                    @endif
                </dd>


                <dt class="col-sm-2">Status</dt>
                <dd class="col-sm-4">
                    @if($bill->status === 'posted')
                        <span class="badge text-bg-success">Posted</span>
                    @elseif($bill->status === 'cancelled')
                        <span class="badge text-bg-danger">Cancelled</span>
                    @else
                        <span class="badge text-bg-secondary">Draft</span>
                    @endif
                </dd>

                <dt class="col-sm-2">Voucher</dt>
                <dd class="col-sm-4">
                    @if($bill->voucher)
                        {{ $bill->voucher->voucher_no ?? ('#' . $bill->voucher->id) }}
                        @if($bill->voucher->voucher_date)
                            ({{ $bill->voucher->voucher_date->format('d-m-Y') }})
                        @endif
                    @else
                        —
                    @endif
                </dd>

                <dt class="col-sm-2">TDS</dt>
                <dd class="col-sm-4">
                    @if($bill->tds_section)
                        <span class="badge text-bg-light border me-1">{{ $bill->tds_section }}</span>
                    @endif
                    {{ rtrim(rtrim(number_format((float) $bill->tds_rate, 4), '0'), '.') }} % |
                    {{ number_format((float) $bill->tds_amount, 2) }}
                </dd>

                <dt class="col-sm-2">TCS</dt>
                <dd class="col-sm-4">
                    @if($bill->tcs_section)
                        <span class="badge text-bg-light border me-1">{{ $bill->tcs_section }}</span>
                    @endif
                    {{ rtrim(rtrim(number_format((float) $bill->tcs_rate, 4), '0'), '.') }} % |
                    {{ number_format((float) $bill->tcs_amount, 2) }}
                </dd>

                <dt class="col-sm-2">Remarks</dt>
                <dd class="col-sm-10">
                    {{ $bill->remarks ?: '—' }}
                </dd>
            </dl>
        </div>
    </div>

    @if($bill->attachments && $bill->attachments->count())
        <div class="card mb-3">
            <div class="card-header py-2">
                <span class="fw-semibold small">Attachments</span>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    @foreach($bill->attachments as $att)
                        <li class="mb-1">
                            <a href="{{ Storage::disk('public')->url($att->path) }}" target="_blank" rel="noopener">
                                {{ $att->original_name ?? basename($att->path) }}
                            </a>
                            <span class="text-muted small">({{ number_format(($att->size ?? 0) / 1024, 1) }} KB)</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Item lines --}}
    <div class="card mb-3">
        <div class="card-header py-2">
            <span class="fw-semibold small">Item Lines</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th>UoM</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Basic</th>
                        <th class="text-end">CGST</th>
                        <th class="text-end">SGST</th>
                        <th class="text-end">IGST</th>
                        <th class="text-end">Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $itemBasic = $bill->lines->sum('basic_amount');
                        $itemCgst  = $bill->lines->sum('cgst_amount');
                        $itemSgst  = $bill->lines->sum('sgst_amount');
                        $itemIgst  = $bill->lines->sum('igst_amount');
                        $itemTotal = $bill->lines->sum('total_amount');
                    @endphp
                    @foreach($bill->lines as $idx => $line)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td>{{ $line->item?->name ?? '-' }}</td>
                            <td class="text-end">{{ number_format((float) $line->qty, 3) }}</td>
                            <td>{{ $line->uom?->code ?? $line->item?->uom?->code ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float) $line->rate, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->basic_amount, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->cgst_amount, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->sgst_amount, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->igst_amount, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->total_amount, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="5" class="text-end">Totals</th>
                        <th class="text-end">{{ number_format((float) $itemBasic, 2) }}</th>
                        <th class="text-end">{{ number_format((float) $itemCgst, 2) }}</th>
                        <th class="text-end">{{ number_format((float) $itemSgst, 2) }}</th>
                        <th class="text-end">{{ number_format((float) $itemIgst, 2) }}</th>
                        <th class="text-end fw-semibold">{{ number_format((float) $itemTotal, 2) }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Expense lines --}}
    @if($bill->expenseLines && $bill->expenseLines->count())
        <div class="card mb-3">
            <div class="card-header py-2">
                <span class="fw-semibold small">Expense Lines</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Ledger</th>
                            <th>Project</th>
                            <th>Desc</th>
                            <th class="text-center">RCM</th>
                            <th class="text-end">Basic</th>
                            <th class="text-end">CGST</th>
                            <th class="text-end">SGST</th>
                            <th class="text-end">IGST</th>
                            <th class="text-end">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            $expBasic = $bill->expenseLines->sum('basic_amount');
                            $expCgst  = $bill->expenseLines->sum('cgst_amount');
                            $expSgst  = $bill->expenseLines->sum('sgst_amount');
                            $expIgst  = $bill->expenseLines->sum('igst_amount');
                            $expTotal = $bill->expenseLines->sum('total_amount');
                        @endphp
                        @foreach($bill->expenseLines as $idx => $line)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>{{ $line->account?->name ?? ('#' . $line->account_id) }}</td>
                                <td>
                                    @php
                                        $lineProj = $line->project ?? $bill->project ?? $bill->purchaseOrder?->project;
                                    @endphp
                                    @if($lineProj)
                                        {{ $lineProj->code }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $line->description ?: '—' }}</td>
                                <td class="text-center">
                                    @if($line->is_reverse_charge)
                                        <span class="badge bg-warning text-dark">RCM</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format((float) $line->basic_amount, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $line->cgst_amount, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $line->sgst_amount, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $line->igst_amount, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $line->total_amount, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="5" class="text-end">Totals</th>
                            <th class="text-end">{{ number_format((float) $expBasic, 2) }}</th>
                            <th class="text-end">{{ number_format((float) $expCgst, 2) }}</th>
                            <th class="text-end">{{ number_format((float) $expSgst, 2) }}</th>
                            <th class="text-end">{{ number_format((float) $expIgst, 2) }}</th>
                            <th class="text-end fw-semibold">{{ number_format((float) $expTotal, 2) }}</th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Bill summary --}}
    <div class="card mb-3">
        <div class="card-header py-2">
            <span class="fw-semibold small">Bill Summary</span>
        </div>
        <div class="card-body">
            @php
                $invoiceTotal = (float) ($bill->total_amount ?? 0);
                $roundOff     = (float) ($bill->round_off ?? 0);
                $calculatedTotal = $invoiceTotal - $roundOff;
                $tcsAmount    = (float) ($bill->tcs_amount ?? 0);
                $tdsAmount    = (float) ($bill->tds_amount ?? 0);
                $grossPayable = $invoiceTotal + $tcsAmount;
                $netPayable   = $grossPayable - $tdsAmount;
            @endphp
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody>
                    <tr>
                        <th class="text-muted">Taxable Value (Basic)</th>
                        <td class="text-end">{{ number_format((float) $bill->total_basic, 2) }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">GST Total (Invoice)</th>
                        <td class="text-end">{{ number_format((float) $bill->total_tax, 2) }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Calculated Total (Basic + GST)</th>
                        <td class="text-end">{{ number_format($calculatedTotal, 2) }}</td>
                    </tr>
                    @if(abs($roundOff) > 0)
                        <tr>
                            <th class="text-muted">Round Off</th>
                            <td class="text-end">{{ number_format($roundOff, 2) }}</td>
                        </tr>
                    @endif
                    @if(((float) ($bill->total_rcm_tax ?? 0)) > 0)
                        <tr>
                            <th class="text-muted">RCM GST Total (Self-assessed)</th>
                            <td class="text-end">{{ number_format((float) $bill->total_rcm_tax, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <th class="text-muted">Invoice Total (Payable)</th>
                        <td class="text-end fw-semibold">{{ number_format($invoiceTotal, 2) }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">+ TCS</th>
                        <td class="text-end">{{ number_format($tcsAmount, 2) }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">– TDS</th>
                        <td class="text-end">{{ number_format($tdsAmount, 2) }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Net Payable</th>
                        <td class="text-end fw-bold">{{ number_format($netPayable, 2) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
	@if(($bill->status ?? null) === 'posted')
    @php
        $allocRows = \App\Models\Accounting\AccountBillAllocation::query()
            ->whereIn('bill_type', [\App\Models\PurchaseBill::class, 'purchase_bills'])
            ->where('bill_id', $bill->id)
            ->where('mode', 'against')
            ->orderByDesc('id')
            ->get();

        // Group by voucher_line_id to show net allocation per payment line
        $grouped = $allocRows->groupBy('voucher_line_id')->map(function($rows){
            return [
                'rows' => $rows,
                'net'  => (float) $rows->sum('amount'),
            ];
        });
    @endphp

    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Bill Allocations (Payments)</strong>
            <span class="text-muted small">Un-allocate payments before reversing this bill</span>
        </div>
        <div class="card-body">

            @if($grouped->count() === 0)
                <div class="text-muted">No allocations found for this bill.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Payment Voucher Line</th>
                            <th class="text-end">Allocated (Net)</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($grouped as $voucherLineId => $g)
                            @php
                                $net = (float) ($g['net'] ?? 0);
                                if ($net < 0) $net = 0; // net should not go negative
                                $first = $g['rows']->first();
                            @endphp
                            <tr>
                                <td>#{{ $voucherLineId }}</td>
                                <td class="text-end">{{ number_format($net, 2) }}</td>
                                <td class="text-end">
                                    @if($net > 0.009)
                                        <!-- Unallocate button -->
                                        <button class="btn btn-outline-danger btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#unallocModal{{ $voucherLineId }}">
                                            Un-allocate
                                        </button>

                                        <!-- Modal -->
                                        <div class="modal fade" id="unallocModal{{ $voucherLineId }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title">Un-allocate Payment</h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="{{ route('purchase.bills.unallocate', $bill) }}">
                                                        @csrf
                                                        <div class="modal-body">
                                                            <input type="hidden" name="allocation_id" value="{{ $first->id }}">

                                                            <div class="mb-2">
                                                                <label class="form-label form-label-sm">Amount to un-allocate</label>
                                                                <input type="number" step="0.01" min="0.01"
                                                                       class="form-control form-control-sm"
                                                                       name="unallocate_amount"
                                                                       value="{{ number_format($net, 2, '.', '') }}">
                                                                <div class="text-muted small">Max: {{ number_format($net, 2) }}</div>
                                                            </div>

                                                            <div class="mb-2">
                                                                <label class="form-label form-label-sm">Reason (optional)</label>
                                                                <input type="text" class="form-control form-control-sm" name="reason"
                                                                       placeholder="e.g. Payment entry mistake">
                                                            </div>

                                                            <div class="alert alert-warning small mb-0">
                                                                This will create a reversing allocation entry (audit-safe).
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger btn-sm"
                                                                    onclick="return confirm('Un-allocate this payment amount from the bill?')">
                                                                Confirm Un-allocate
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

      	  </div>
    		</div>
		@endif

  
    {{-- Posting History --}}
    <div class="card mt-3">
        <div class="card-header py-2">
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold small">Posting History</span>
                <span class="small text-muted">Latest posting events for this bill</span>
            </div>
        </div>
        <div class="card-body p-0">
            @php
                $postingLogs = \App\Models\ActivityLog::query()
                    ->where('subject_type', get_class($bill))
                    ->where('subject_id', $bill->id)
                    ->where('action', 'posted_purchase_bill')
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get();
            @endphp

            @if($postingLogs->isEmpty())
                <p class="text-muted small mb-0 p-3">
                    No posting activity recorded yet for this bill.
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                        <tr class="small">
                            <th style="width: 20%">When</th>
                            <th style="width: 20%">User</th>
                            <th style="width: 20%">Voucher</th>
                            <th>Note</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($postingLogs as $log)
                            @php $meta = $log->metadata ?? []; @endphp
                            <tr class="small">
                                <td>{{ optional($log->created_at)->format('d-m-Y H:i') }}</td>
                                <td>{{ $log->user_name ?? $log->user?->name ?? 'System' }}</td>
                                <td>
                                    @if(!empty($meta['voucher_no']))
                                        {{ $meta['voucher_no'] }}
                                    @elseif(!empty($meta['voucher_id']))
                                        #{{ $meta['voucher_id'] }}
                                    @elseif($bill->voucher)
                                        {{ $bill->voucher->voucher_no ?? ('#' . $bill->voucher->id) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $log->description }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection



