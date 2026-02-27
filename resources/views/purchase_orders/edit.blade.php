
@extends('layouts.erp')

@section('title', 'Edit Purchase Order ' . $order->code)

@section('content')
            <style>
                .table-wrapper {
                    overflow-x: auto;
                    overflow-y: auto;
                    max-height: 500px;
                    border-radius: 6px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                }

                table th,
                table td {
                    white-space: normal !important;
                    vertical-align: middle;
                    font-size: 13px;
                    padding: 6px;
                }

                /* Sticky Header */
                thead th {
                    position: sticky;
                    top: 0;
                    background: #f8f9fa;
                    z-index: 2;
                }

                /* Item column wider */
                table th:nth-child(2),
                table td:nth-child(2) {
                    min-width: 220px;
                }

                /* Numeric alignment */
                .text-end {
                    text-align: right;
                }

                /* Input full width */
                table input.form-control {
                    width: 100%;
                    min-width: 90px;
                    padding: 3px 6px;
                    font-size: 13px;
                }

                /* Description wrap */
                .small.text-muted {
                    word-break: break-word;
                }

                /* Scrollbar styling */
                .table-wrapper::-webkit-scrollbar {
                    height: 8px;
                    width: 8px;
                }

                .table-wrapper::-webkit-scrollbar-thumb {
                    background: #0d6efd;
                    border-radius: 10px;
                }

                .table-wrapper::-webkit-scrollbar-track {
                    background: #e9ecef;
                }
            </style>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h1 class="h4 mb-0">Edit Purchase Order {{ $order->code }}</h1>
                                    <div class="small text-muted">
                                        Status: {{ ucfirst($order->status) }}
                                    </div>
                                </div>
                                <div class="text-end">
                                    <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-sm btn-secondary">
                                        Back to PO
                                    </a>
                                </div>
                            </div>

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <div class="fw-bold mb-2">Please fix the following errors:</div>
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('purchase-orders.update', $order) }}">
                                @csrf
                                @method('PUT')

                                <div class="card mb-3">
                                    <div class="card-header">
                                        PO Header
                                    </div>
                                    <div class="card-body row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">PO Date</label>
                                            <input type="date"
                                                   name="po_date"
                                                   class="form-control"
                                                   value="{{ old('po_date', optional($order->po_date)->toDateString() ?? now()->toDateString()) }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Expected Delivery</label>
                                            <input type="date"
                                                   name="expected_delivery_date"
                                                   class="form-control"
                                                   value="{{ old('expected_delivery_date', optional($order->expected_delivery_date)->toDateString()) }}">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Payment Terms (days)</label>
                                            <input type="number"
                                                   name="payment_terms_days"
                                                   class="form-control"
                                                   value="{{ old('payment_terms_days', $order->payment_terms_days) }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Delivery Terms (days)</label>
                                            <input type="number"
                                                   name="delivery_terms_days"
                                                   class="form-control"
                                                   value="{{ old('delivery_terms_days', $order->delivery_terms_days) }}">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Freight Terms</label>
                                            <input type="text"
                                                   name="freight_terms"
                                                   class="form-control"
                                                   value="{{ old('freight_terms', $order->freight_terms) }}">
                                        </div>
                                        <div class="col-md-4">
                            <label class="form-label">T&amp;C Template</label>
                            <select name="standard_term_id"
                                    id="standard_term_id"
                                    class="form-select form-select-sm">
                                <option value="">-- Custom / None --</option>
                                @foreach($terms as $term)
                                    <option value="{{ $term->id }}"
                                        @selected(old('standard_term_id', $order->standard_term_id) == $term->id)>
                                        {{ $term->name }} @if($term->is_default) (Default) @endif
                                    </option>
                                @endforeach
                                </select>
                                </div>

                                <div class="col-12">
                            <label class="form-label">Terms &amp; Conditions Text</label>
                            <textarea name="terms_text" id="terms_text" rows="6" class="form-control">{{ old('terms_text', $order->terms_text) }}</textarea>
                            <div class="form-text">
                                This is the actual text that will appear on PO/PDF. You can edit after selecting a template.
                            </div>
                                </div>

                                @push('scripts')
                                <script>
                            const termsMap = @json(
        $terms->mapWithKeys(fn($t) => [$t->id => $t->content])
    );

                            document.getElementById('standard_term_id')?.addEventListener('change', function () {
                                const id = this.value;
                                if (!id || !termsMap[id]) {
                                    return;
                                }
                                if (!confirm('Replace current Terms & Conditions text with this template?')) {
                                    this.value = '';
                                    return;
                                }
                                document.getElementById('terms_text').value = termsMap[id];
                            });
                                </script>
                                @endpush



                                        <div class="col-12">
                                            <label class="form-label">Remarks</label>
                                            <textarea name="remarks" rows="2" class="form-control">{{ old('remarks', $order->remarks) }}</textarea>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Project</label>
                                            <div class="form-control-plaintext">
                                                {{ optional($order->project)->code }} - {{ optional($order->project)->name }}
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Department</label>
                                            <div class="form-control-plaintext">
                                                {{ optional($order->department)->name }}
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Vendor</label>
                                            <div class="form-control-plaintext">
                                                {{ optional($order->vendor)->name }}
                                            </div>

                                            <label class="form-label mt-2">Vendor GSTIN / Branch</label>
                                            <select name="vendor_branch_id"
                                                    id="vendor_branch_id"
                                                    class="form-select form-select-sm @error('vendor_branch_id') is-invalid @enderror">
                                                <option value="">-- Use Primary / Party GSTIN --</option>
                                                @foreach(optional($order->vendor)->branches ?? [] as $br)
                                                    <option value="{{ $br->id }}"
                                                            data-gst-state="{{ $br->gst_state_code }}"
                                                            @selected(old('vendor_branch_id', $order->vendor_branch_id) == $br->id)>
                                                        {{ $br->branch_name ? ($br->branch_name . ' - ') : '' }}{{ $br->gstin }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('vendor_branch_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">Select the GSTIN branch for this order (affects tax type).</div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Linked RFQ / Indent</label>
                                            <div class="form-control-plaintext">
                                                @if($order->rfq)
                                                    RFQ: <a href="{{ route('purchase-rfqs.show', $order->rfq) }}">{{ $order->rfq->code }}</a>
                                                @endif
                                                @if($order->indent)
                                                    <br>Indent: <a href="{{ route('purchase-indents.show', $order->indent) }}">{{ $order->indent->code }}</a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                        <div class="card mb-3 shadow-sm border-0">

                            <div class="card-header bg-primary text-white fw-semibold py-2">
                                Items
                            </div>

                            <div class="card-body p-0">

                                <div class="table-wrapper">

                                    <table class="table table-bordered table-hover table-sm align-middle mb-0">

                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Item</th>
                                                <th>Grade</th>
                                                <th>L (mm)</th>
                                                <th>W (mm)</th>
                                                <th>T (mm)</th>
                                                <th>Wt/m (kg)</th>
                                                <th>Qty pcs</th>
                                                <th>Qty ({{ $order->items->first()->uom->name ?? 'UOM' }})</th>
                                                <th>UOM</th>
                                                <th>Rate</th>
                                                <th>Disc %</th>
                                                <th>Tax %</th>
                                                <th>GRN Tol %</th>
                                                <th>Amount</th>
                                                <th>Net</th>
                                            </tr>

                                        </thead>

                                        <tbody>
                                            @foreach($order->items as $index => $item)
                                                @php $rowKey = $item->id; @endphp
                                                <tr>
                                                    <td>{{ $index + 1 }}</td>

                                                    <td>
                                                        {{ optional($item->item)->name }}
                                                        <div class="small text-muted">
                                                            {{ $item->description }}
                                                        </div>
                                                    </td>

                                                    <td>{{ $item->grade }}</td>
                                                    <td class="text-end">{{ $item->length_mm }}</td>
                                                    <td class="text-end">{{ $item->width_mm }}</td>
                                                    <td class="text-end">{{ $item->thickness_mm }}</td>
                                                    <td class="text-end">{{ $item->weight_per_meter_kg }}</td>
                                                    <td class="text-end">{{ $item->qty_pcs }}</td>

                                                    <td>
                                                        <input type="number" step="0.001" name="items[{{ $rowKey }}][quantity]"
                                                            class="form-control form-control-sm text-end"
                                                            value="{{ old("items.$rowKey.quantity", $item->quantity) }}">
                                                    </td>

                                                    <td>{{ optional($item->uom)->name }}</td>

                                                    <td>
                                                        <input type="number" step="0.01" name="items[{{ $rowKey }}][rate]"
                                                            class="form-control form-control-sm text-end"
                                                            value="{{ old("items.$rowKey.rate", $item->rate) }}">
                                                    </td>

                                                    <td>
                                                        <input type="number" step="0.01" name="items[{{ $rowKey }}][discount_percent]"
                                                            class="form-control form-control-sm text-end"
                                                            value="{{ old("items.$rowKey.discount_percent", $item->discount_percent) }}">
                                                    </td>

                                                    <td>
                                                        <input type="number" step="0.01" name="items[{{ $rowKey }}][tax_percent]"
                                                            class="form-control form-control-sm text-end"
                                                            value="{{ old("items.$rowKey.tax_percent", $item->tax_percent) }}">
                                                    </td>

                                                    <td>
                                                        <input type="number" step="0.01" name="items[{{ $rowKey }}][grn_tolerance_percent]"
                                                            class="form-control form-control-sm text-end"
                                                            value="{{ old("items.$rowKey.grn_tolerance_percent", $item->grn_tolerance_percent) }}">
                                                    </td>

                                                    <td class="text-end fw-semibold">
                                                        {{ number_format($item->amount, 2) }}
                                                    </td>

                                                    <td class="text-end fw-bold text-success">
                                                        {{ number_format($item->net_amount, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>

                                    </table>

                                </div>

                                <div class="bg-light p-2 text-end fw-bold border-top">
                                    Last Saved Total :
                                    <span class="text-success fs-6">
                                        {{ number_format($order->total_amount, 2) }}
                                    </span>
                                </div>

                            </div>
                        </div>


                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        Save Changes
                                    </button>
                                </div>
                            </form>
@endsection





