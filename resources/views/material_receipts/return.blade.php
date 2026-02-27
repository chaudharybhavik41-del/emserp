@extends('layouts.erp')

@section('title', 'Vendor Return (GRN)')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Vendor Return - GRN {{ $receipt->receipt_number }}</h1>
        <a href="{{ route('material-receipts.show', $receipt) }}" class="btn btn-sm btn-outline-secondary">Back to GRN</a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-warning">
        <strong>Important:</strong>
        Vendor return is allowed only if <strong>none of the GRN stock is issued/consumed/reserved</strong>.
        This will reduce the GRN received quantities (correction-style) and update PO/Indent received totals.
    </div>

    @if(!empty($receipt->vendorReturns) && $receipt->vendorReturns->count())
        <div class="alert alert-info">
            <strong>Existing Vendor Returns:</strong>
            <ul class="mb-0">
                @foreach($receipt->vendorReturns as $vr)
                    <li>
                        {{ $vr->vendor_return_number ?? ('#' . $vr->id) }} ({{ $vr->return_date?->format('Y-m-d') ?? '-' }})
                        @if($vr->voucher_id)
                            - Voucher:
                            <a href="{{ route('accounting.vouchers.show', $vr->voucher_id) }}" class="text-decoration-none">
                                {{ $vr->voucher?->voucher_no ?? ('#' . $vr->voucher_id) }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('material-receipts.return.store', $receipt) }}">
                @csrf

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Return Date</label>
                        <input type="date"
                               class="form-control form-control-sm"
                               name="return_date"
                               value="{{ old('return_date', now()->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reason</label>
                        <input type="text"
                               class="form-control form-control-sm"
                               name="reason"
                               value="{{ old('reason') }}"
                               placeholder="Damaged / Wrong spec / Excess / ...">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Remarks</label>
                        <input type="text"
                               class="form-control form-control-sm"
                               name="remarks"
                               value="{{ old('remarks') }}">
                    </div>
                </div>

                <div class="small text-muted mb-2">
                    <strong>Tip:</strong> For plates/consumables, you can either enter <em>Return Pcs</em> OR select exact <em>Stock Pieces</em>.
                    If stock pieces are selected, the return pcs will be taken from selection.
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 25%">Item</th>
                                <th style="width: 10%">Category</th>
                                <th style="width: 10%" class="text-end">Received Pcs</th>
                                <th style="width: 12%" class="text-end">Received Wt (kg)</th>
                                <th style="width: 10%" class="text-end">Return Pcs</th>
                                <th style="width: 12%" class="text-end">Return Wt (kg)</th>
                                <th style="width: 21%">Select Stock Pieces (Optional)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receipt->lines as $i => $line)
                                @php
                                    $lineStocks = $stocksByLine[$line->id] ?? collect();
                                    $availableStocks = $lineStocks->where('status', 'available')->values();
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $line->item?->name ?? ('Item #' . $line->item_id) }}</div>
                                        <div class="small text-muted">
                                            UOM: {{ $line->item?->uom?->name ?? '-' }}
                                            @if(!empty($line->brand)) | Brand: {{ $line->brand }} @endif
                                        </div>
                                    </td>
                                    <td>{{ $line->material_category }}</td>
                                    <td class="text-end">{{ (int) $line->qty_pcs }}</td>
                                    <td class="text-end">{{ $line->received_weight_kg !== null ? number_format((float) $line->received_weight_kg, 3) : '-' }}</td>

                                    <td>
                                        <input type="hidden" name="lines[{{ $i }}][line_id]" value="{{ $line->id }}">
                                        <input type="number"
                                               class="form-control form-control-sm text-end"
                                               name="lines[{{ $i }}][return_qty_pcs]"
                                               value="{{ old('lines.' . $i . '.return_qty_pcs', 0) }}"
                                               min="0"
                                               max="{{ (int) $line->qty_pcs }}">
                                    </td>
                                    <td>
                                        @if($line->material_category === 'steel_section')
                                            <input type="number"
                                                   class="form-control form-control-sm text-end"
                                                   name="lines[{{ $i }}][return_weight_kg]"
                                                   value="{{ old('lines.' . $i . '.return_weight_kg', 0) }}"
                                                   step="0.001"
                                                   min="0">
                                            <div class="form-text">
                                                For steel_section, you can return by pcs and/or weight.
                                            </div>
                                        @else
                                            <input type="number"
                                                   class="form-control form-control-sm text-end"
                                                   value="0"
                                                   step="0.001"
                                                   min="0"
                                                   disabled>
                                            <div class="form-text">
                                                Auto-calculated from selected piece weights.
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        @if($line->material_category === 'steel_section')
                                            <div class="small text-muted">
                                                Combined stock (single row). Selection is not applicable.
                                            </div>
                                        @else
                                            @if($availableStocks->isEmpty())
                                                <div class="small text-muted">No available stock to return.</div>
                                            @else
                                                <div style="max-height: 140px; overflow: auto;">
                                                    @foreach($availableStocks as $s)
                                                        <div class="form-check small">
                                                            <input class="form-check-input"
                                                                   type="checkbox"
                                                                   name="lines[{{ $i }}][stock_ids][]"
                                                                   value="{{ $s->id }}"
                                                                   id="line{{ $line->id }}_stock{{ $s->id }}">
                                                            <label class="form-check-label" for="line{{ $line->id }}_stock{{ $s->id }}">
                                                                #{{ $s->id }}
                                                                @if(!empty($s->plate_number)) | Plate: {{ $s->plate_number }} @endif
                                                                @if(!empty($s->heat_number)) | Heat: {{ $s->heat_number }} @endif
                                                                @if(!empty($s->color_code)) | Color: {{ $s->color_code }} @endif
                                                                @if(!empty($s->weight_kg_total)) | {{ number_format((float) $s->weight_kg_total, 3) }} kg @endif
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('material-receipts.show', $receipt) }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-danger">Save Vendor Return</button>
                </div>
            </form>
        </div>
    </div>
@endsection
