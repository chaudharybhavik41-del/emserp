@php
/** @var \App\Models\PurchaseBill|null $bill */
$isEdit = isset($bill) && $bill->exists;

// ------- ITEM LINES (old() -> existing -> blank rows) -------
$itemLines = old('lines');
if ($itemLines === null) {
    if ($isEdit) {
        $itemLines = $bill->lines
            ->whereNotNull('item_id')
            ->values()
            ->map(function ($l) {
                return [
                    'id' => $l->id,
                    'material_receipt_id' => $l->material_receipt_id,
                    'material_receipt_line_id' => $l->material_receipt_line_id,
                    'item_id' => $l->item_id,
                    'uom_id' => $l->uom_id,
                    'qty' => $l->qty,
                    'rate' => $l->rate,
                    'discount_percent' => $l->discount_percent,
                    'discount_amount' => $l->discount_amount,
                    'basic_amount' => $l->basic_amount,
                    'tax_rate' => $l->tax_rate,
                    'tax_amount' => $l->tax_amount,
                    'cgst_amount' => $l->cgst_amount,
                    'sgst_amount' => $l->sgst_amount,
                    'igst_amount' => $l->igst_amount,
                    'total_amount' => $l->total_amount,
                    'account_id' => $l->account_id,
                ];
            })
            ->all();
    } else {
        $itemLines = [];
    }
}

$emptyLines = isset($emptyLines) ? (int) $emptyLines : 3;
$minRows = max($emptyLines, count($itemLines));
// always keep a few extra empty rows for data entry
$targetRows = max($minRows, 5);
for ($i = count($itemLines); $i < $targetRows; $i++) {
    $itemLines[] = [
        'id' => null,
        'material_receipt_id' => null,
        'material_receipt_line_id' => null,
        'item_id' => null,
        'uom_id' => null,
        'qty' => null,
        'rate' => null,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'basic_amount' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'cgst_amount' => 0,
        'sgst_amount' => 0,
        'igst_amount' => 0,
        'total_amount' => 0,
        'account_id' => null,
    ];
}

// ------- EXPENSE LINES (old() -> existing -> blank rows) -------
$expenseLines = old('expense_lines');
if ($expenseLines === null) {
    if ($isEdit) {
        $expenseLines = $bill->expenseLines->values()->map(function ($l) {
            return [
                'account_id' => $l->account_id,
                // Phase-B: preserve per-expense-line project split when editing
                'project_id' => $l->project_id,
                'description' => $l->description,
                'amount' => $l->basic_amount,
                'tax_rate' => $l->tax_rate,
                'tax_amount' => $l->tax_amount,
                'cgst_amount' => $l->cgst_amount,
                'sgst_amount' => $l->sgst_amount,
                'igst_amount' => $l->igst_amount,
                'total_amount' => $l->total_amount,
                'is_reverse_charge' => (bool) ($l->is_reverse_charge ?? false),
            ];
        })->all();
    } else {
        $expenseLines = [];
    }
}

$targetExpenseRows = max(3, count($expenseLines));
for ($i = count($expenseLines); $i < $targetExpenseRows; $i++) {
    $expenseLines[] = [
        'account_id' => null,
        'project_id' => null,
        'description' => null,
        'amount' => null,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'cgst_amount' => 0,
        'sgst_amount' => 0,
        'igst_amount' => 0,
        'total_amount' => 0,
        'is_reverse_charge' => false,
    ];
}

// Company GST State Code (for GST split preview)
$companyGstNumber = $company->gst_number ?? '';
$companyGstStateCode = (preg_match('/^\d{2}/', $companyGstNumber) ? substr($companyGstNumber, 0, 2) : '');

// Initial summary (will be re-calculated live by JS)
$initTotalBasic = (float) ($bill->total_basic ?? 0);
$initTotalTax = (float) ($bill->total_tax ?? 0);
$initTotalCgst = (float) ($bill->total_cgst ?? 0);
$initTotalSgst = (float) ($bill->total_sgst ?? 0);
$initTotalIgst = (float) ($bill->total_igst ?? 0);
$initCalculatedTotal = $initTotalBasic + $initTotalTax;
$initRoundOff = (float) ($bill->round_off ?? 0);
$initInvoiceTotal = (float) ($bill->total_amount ?? $initCalculatedTotal);
$initTcs = (float) ($bill->tcs_amount ?? 0);
$initTds = (float) ($bill->tds_amount ?? 0);
$initNet = (float) ($bill->net_payable ?? 0);
@endphp


<style>


/* Only expense table scroll */
#expense-table-wrapper {
    overflow-x: auto;
    overflow-y: hidden;
    max-height: 420px;   /* optional vertical scroll */
}

/* Prevent column shrink */
#expense-lines-table th,
#expense-lines-table td {
    white-space: nowrap;
    min-width: 110px;
    vertical-align: middle;
}

/* Ledger column wider */
#expense-lines-table th:nth-child(1),
#expense-lines-table td:nth-child(1) {
    min-width: 200px;
}

/* Project column wider */
#expense-lines-table th:nth-child(2),
#expense-lines-table td:nth-child(2) {
    min-width: 200px;
}

/* Description column bigger */
#expense-lines-table th:nth-child(3),
#expense-lines-table td:nth-child(3) {
    min-width: 250px;
}

/* Sticky header */
#expense-lines-table thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 2;
}

    /* Enable smooth horizontal scroll */
.table-responsive {
    overflow-x: auto;
    overflow-y: hidden;
}

/* Prevent column shrink */
#bill-lines-table th,
#bill-lines-table td {
    white-space: nowrap;
    min-width: 110px;
    vertical-align: middle;
}

/* Make Item column little wider */
#bill-lines-table th:first-child,
#bill-lines-table td:first-child {
    min-width: 220px;
}

/* Optional: Total column slightly bigger */
#bill-lines-table th:last-child,
#bill-lines-table td:last-child {
    min-width: 130px;
}
.table-responsive {
    max-height: 420px;
}

#bill-lines-table thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 2;
}

    .select2-container--default .select2-selection--single {
    height: 38px;
    border-radius: 8px;
    padding: 6px 10px;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 24px;
}

    .section-card {
        border: 1px solid #e9ecef;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
        padding: 20px;
    }

    .section-title {
        font-weight: 600;
        font-size: 14px;
        color: #495057;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .form-label {
        font-size: 13px;
        font-weight: 600;
        color: #495057;
        margin-bottom: 4px;
    }

    .form-control-sm,
    .form-select-sm {
        border-radius: 8px;
        padding: 6px 10px;
    }

    .form-text {
        font-size: 11px;
        color: #6c757d;
    }

    .btn-sm {
        border-radius: 8px;
        padding: 6px 14px;
        font-weight: 500;
    }

    .divider-line {
        border-top: 1px dashed #dee2e6;
        margin: 25px 0;
    }
</style>

<form method="POST"
      action="{{ $isEdit ? route('purchase.bills.update', $bill) : route('purchase.bills.store') }}"
      enctype="multipart/form-data"
      id="purchaseBillForm">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <input type="hidden" name="purchase_order_id" id="purchase_order_id" value="{{ old('purchase_order_id', $bill->purchase_order_id) }}">
<div class="section-card">

    <!-- ================= Supplier Section ================= -->
    <div class="section-title">Supplier Information</div>

    <div class="row g-3">
    <div class="col-lg-7">
        <label class="form-label">
            Supplier / Contractor <span class="text-danger">*</span>
        </label>
    
        <div class="d-flex gap-2">
    
            <!-- Supplier Select -->
            <div class="flex-grow-1">
                <select name="supplier_id" id="supplier_id"
                    class="form-select form-select-sm select2-basic @error('supplier_id') is-invalid @enderror">
                    <option value="">-- Select Supplier --</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" {{ (string) old('supplier_id', $bill->supplier_id) === (string) $s->id ? 'selected' : '' }}>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>
    
            <!-- Fetch Button -->
            <div>
                <button type="button" class="btn btn-primary btn-sm px-3" id="btnFetchGrn">
                    Fetch GRN/PO
                </button>
            </div>
    
        </div>
    
        <div class="form-text">
            Start typing to search supplier.
        </div>
    </div>


        <div class="col-lg-5">
            <label class="form-label">Supplier GSTIN / Branch</label>
            <select name="supplier_branch_id" id="supplier_branch_id" class="form-select form-select-sm">
                <option value="">-- Use Primary GSTIN --</option>
            </select>

            <div class="form-text">
                Selecting branch will affect GST calculation.
            </div>
        </div>
    </div>


    <div class="divider-line"></div>


    <!-- ================= Bill Details ================= -->
    <div class="section-title">Invoice Details</div>

    <div class="row g-3">

        <div class="col-md-3">
            <label class="form-label">Bill Date <span class="text-danger">*</span></label>
            <input type="date" name="bill_date" class="form-control form-control-sm">
        </div>

        <div class="col-md-3">
            <label class="form-label">Posting Date <span class="text-danger">*</span></label>
            <input type="date" name="posting_date" class="form-control form-control-sm">
        </div>

        <div class="col-md-3">
            <label class="form-label">Due Date</label>
            <input type="date" name="due_date" class="form-control form-control-sm">
        </div>

        <div class="col-md-3">
            <label class="form-label">Bill No <span class="text-danger">*</span></label>
            <input type="text" name="bill_number" class="form-control form-control-sm">
        </div>

        <div class="col-md-4">
            <label class="form-label">Invoice No (Supplier)</label>
            <input type="text" name="reference_no" class="form-control form-control-sm">
        </div>

        <div class="col-md-4">
            <label class="form-label">Challan No</label>
            <input type="text" name="challan_number" class="form-control form-control-sm">
        </div>

        <div class="col-md-4">
            <label class="form-label">Project</label>
            <select name="project_id" class="form-select form-select-sm select2-basic">
                <option value="">-- Select Project --</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}">
                        {{ $p->code }} - {{ $p->name }}
                    </option>
                @endforeach
            </select>
        </div>

    </div>


    <div class="divider-line"></div>


    <!-- ================= Finance Section ================= -->
    <div class="section-title">Financial Details</div>

    <div class="row g-3">

        <div class="col-md-4">
            <label class="form-label">Linked Purchase Order</label>
            <input type="text" class="form-control form-control-sm" placeholder="(Fetch GRN/PO to link)" readonly>
        </div>

        <div class="col-md-2">
            <label class="form-label">Currency</label>
            <input type="text" name="currency" class="form-control form-control-sm" value="INR">
        </div>

        <div class="col-md-2">
            <label class="form-label">Exchange Rate</label>
            <input type="number" step="0.0001" name="exchange_rate" class="form-control form-control-sm" value="1">
        </div>

        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="draft">Draft</option>
                <option value="posted">Posted</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <div class="col-md-12">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" rows="2" class="form-control form-control-sm"></textarea>
        </div>

    </div>

</div>

    {{-- <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Supplier / Contractor <span class="text-danger">*</span></label>
            <div class="d-flex gap-2">
                <select name="supplier_id"
                        id="supplier_id"
                        class="form-select form-select-sm select2-basic @error('supplier_id') is-invalid @enderror"
                        data-company-gst-state="{{ $companyGstStateCode }}">
                    <option value="">-- Select --</option>
                    @foreach($suppliers as $s)
                        @php
                            $gstState = $s->gst_state_code ?: (preg_match('/^\d{2}/', (string) $s->gstin) ? substr((string) $s->gstin, 0, 2) : '');
                        @endphp
                        <option value="{{ $s->id }}"
                                data-gst-state="{{ $gstState }}"
                                data-state="{{ $s->state }}"
                                {{ (string) old('supplier_id', $bill->supplier_id) === (string) $s->id ? 'selected' : '' }}>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>

                <button type="button"
                        class="btn btn-outline-primary btn-sm"
                        id="btnFetchGrn">
                    Fetch GRN/PO
                </button>
            </div>
            @error('supplier_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            <div class="form-text">
                Tip: start typing to search by name.
            </div>

            <div class="mt-2">
                <label class="form-label">Supplier GSTIN / Branch</label>
                <select name="supplier_branch_id"
                        id="supplier_branch_id"
                        class="form-select form-select-sm @error('supplier_branch_id') is-invalid @enderror"
                        data-selected="{{ old('supplier_branch_id', $bill->supplier_branch_id ?? '') }}">
                    <option value="">-- Use Primary / Party GSTIN --</option>
                </select>
                @error('supplier_branch_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    Select the GSTIN branch for this transaction (affects GST split).
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <label class="form-label">Bill Date <span class="text-danger">*</span></label>
            <input type="date"
                   name="bill_date"
                   id="bill_date"
                   class="form-control form-control-sm @error('bill_date') is-invalid @enderror"
                   value="{{ old('bill_date', optional($bill->bill_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
            @error('bill_date')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Invoice date (GST)</div>
        </div>

        <div class="col-md-2">
            <label class="form-label">Posting Date <span class="text-danger">*</span></label>
            <input type="date"
                   name="posting_date"
                   id="posting_date"
                   class="form-control form-control-sm @error('posting_date') is-invalid @enderror"
                   value="{{ old('posting_date', optional($bill->posting_date)->format('Y-m-d') ?? optional($bill->bill_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
            @error('posting_date')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Voucher date (Books)</div>
        </div>

        <div class="col-md-2">
            <label class="form-label">Due Date</label>
            <input type="date"
                   name="due_date"
                   class="form-control form-control-sm @error('due_date') is-invalid @enderror"
                   value="{{ old('due_date', optional($bill->due_date)->format('Y-m-d')) }}">
            @error('due_date')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Bill No <span class="text-danger">*</span></label>
            <input type="text"
                   name="bill_number"
                   class="form-control form-control-sm @error('bill_number') is-invalid @enderror"
                   value="{{ old('bill_number', $bill->bill_number) }}">
            @error('bill_number')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Invoice No (Supplier)</label>
            <input type="text"
                   name="reference_no"
                   id="invoice_number"
                   class="form-control form-control-sm @error('reference_no') is-invalid @enderror"
                   value="{{ old('reference_no', $bill->reference_no) }}">
            @error('reference_no')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Challan No</label>
            <input type="text"
                   name="challan_number"
                   id="challan_number"
                   class="form-control form-control-sm @error('challan_number') is-invalid @enderror"
                   value="{{ old('challan_number', $bill->challan_number) }}">
            @error('challan_number')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">Project</label>
            <select name="project_id"
                    id="project_id"
                    class="form-select form-select-sm select2-basic @error('project_id') is-invalid @enderror">
                <option value="">-- None --</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}" {{ (string) old('project_id', $bill->project_id ?? optional($bill->purchaseOrder)->project_id) === (string) $p->id ? 'selected' : '' }}>
                        {{ $p->code }} - {{ $p->name }}
                    </option>
                @endforeach
            </select>
            @error('project_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
                If a Project is selected, <strong>Expense lines</strong> will be posted to <strong>WIP-OTHER</strong> (Project Cost) instead of P&amp;L expense ledgers.
            </div>
        </div>


        <div class="col-md-6">
            <label class="form-label">Linked Purchase Order</label>
            <input type="text"
                   id="purchase_order_display"
                   class="form-control form-control-sm"
                   value="{{ $bill->purchaseOrder ? ($bill->purchaseOrder->code . ' - ' . optional($bill->purchaseOrder->project)->name) : '' }}"
                   placeholder="(Fetch GRN/PO to link)"
                   readonly>
            <div class="form-text">
                Item lines fetched from GRN will be locked to avoid mismatch.
            </div>
        </div>

        <div class="col-md-2">
            <label class="form-label">Currency</label>
            <input type="text"
                   name="currency"
                   class="form-control form-control-sm"
                   value="{{ old('currency', $bill->currency ?? 'INR') }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Exchange Rate</label>
            <input type="number"
                   step="0.0001"
                   name="exchange_rate"
                   class="form-control form-control-sm"
                   value="{{ old('exchange_rate', $bill->exchange_rate ?? 1) }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm">
                @php $st = old('status', $bill->status ?? 'draft'); @endphp
                <option value="draft" {{ $st === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="posted" {{ $st === 'posted' ? 'selected' : '' }}>Posted</option>
                <option value="cancelled" {{ $st === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>

        <div class="col-md-12">
            <label class="form-label">Remarks</label>
            <textarea name="remarks"
                      class="form-control form-control-sm"
                      rows="2">{{ old('remarks', $bill->remarks) }}</textarea>
        </div>
    </div>

    <hr class="my-3"> --}}

    {{-- ITEM LINES --}}
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="h6 mb-0">Items</h5>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddItemRow">+ Add Row</button>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle text-nowrap" id="bill-lines-table">
            <thead class="table-light">
            <tr>
                <th style="width:14%">Item</th>
                <th style="width:7%">UOM</th>
                <th style="width:7%">Qty</th>
                <th style="width:7%">Rate</th>
                <th style="width:6%">Disc %</th>
                <th style="width:8%">Disc Amt</th>
                <th style="width:8%">Basic</th>
                <th style="width:6%">GST %</th>
                <th style="width:8%">Tax</th>
                <th style="width:8%">CGST</th>
                <th style="width:8%">SGST</th>
                <th style="width:8%">IGST</th>
                <th style="width:10%">Total</th>
            </tr>
            </thead>
            <tbody id="bill-lines-tbody">
            @foreach($itemLines as $i => $line)
                @php
    $mrLineId = $line['material_receipt_line_id'] ?? null;
    $isLinked = !empty($mrLineId);
                @endphp
                <tr data-line-index="{{ $i }}" class="{{ $isLinked ? 'table-warning' : '' }}">
                    <td>
                        <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $line['id'] }}">
                        <input type="hidden" name="lines[{{ $i }}][material_receipt_id]" value="{{ $line['material_receipt_id'] }}">
                        <input type="hidden" name="lines[{{ $i }}][material_receipt_line_id]" value="{{ $mrLineId }}" class="mr-line-id">

                        <select name="lines[{{ $i }}][item_id]"
                                class="form-select form-select-sm item-select">
                            <option value="">--</option>
                            @foreach($items as $it)
                                <option value="{{ $it->id }}" {{ (string) ($line['item_id'] ?? '') === (string) $it->id ? 'selected' : '' }}>
                                    {{ $it->code }} - {{ $it->name }}
                                </option>
                            @endforeach
                        </select>
                        @if($isLinked)
                            <div class="form-text text-warning">Linked to GRN</div>
                        @endif
                    </td>

                    <td>
                        <select name="lines[{{ $i }}][uom_id]" class="form-select form-select-sm uom-select">
                            <option value="">--</option>
                            @foreach($uoms as $u)
                                <option value="{{ $u->id }}" {{ (string) ($line['uom_id'] ?? '') === (string) $u->id ? 'selected' : '' }}>
                                    {{ $u->code }}
                                </option>
                            @endforeach
                        </select>
                    </td>

                    <td>
                        <input type="number" step="0.0001"
                               name="lines[{{ $i }}][qty]"
                               class="form-control form-control-sm qty-input"
                               value="{{ $line['qty'] }}">
                    </td>

                    <td>
                        <input type="number" step="0.01"
                               name="lines[{{ $i }}][rate]"
                               class="form-control form-control-sm rate-input"
                               value="{{ $line['rate'] }}">
                    </td>

                    <td>
                        <input type="number" step="0.01"
                               name="lines[{{ $i }}][discount_percent]"
                               class="form-control form-control-sm discpct-input"
                               value="{{ $line['discount_percent'] ?? 0 }}">
                    </td>

                    <td>
                        <input type="number" step="0.01"
                               name="lines[{{ $i }}][discount_amount]"
                               class="form-control form-control-sm discamt-input"
                               value="{{ $line['discount_amount'] ?? 0 }}" readonly>
                    </td>

                    <td>
                        <input type="number" step="0.01"
                               name="lines[{{ $i }}][basic_amount]"
                               class="form-control form-control-sm basicamt-input"
                               value="{{ $line['basic_amount'] ?? 0 }}" readonly>
                    </td>

                    <td>
                        <input type="number" step="0.01"
                               name="lines[{{ $i }}][tax_rate]"
                               class="form-control form-control-sm taxrate-input"
                               value="{{ $line['tax_rate'] ?? 0 }}">
                    </td>

                    <td>
                        <input type="number" step="0.01"
                               name="lines[{{ $i }}][tax_amount]"
                               class="form-control form-control-sm taxamt-input"
                               value="{{ $line['tax_amount'] ?? 0 }}" readonly>
                    </td>

                    <td>
                        <input type="number" step="0.01"
                               name="lines[{{ $i }}][cgst_amount]"
                               class="form-control form-control-sm cgst-input"
                               value="{{ $line['cgst_amount'] ?? 0 }}" readonly>
                    </td>

                    <td>
                        <input type="number" step="0.01"
                               name="lines[{{ $i }}][sgst_amount]"
                               class="form-control form-control-sm sgst-input"
                               value="{{ $line['sgst_amount'] ?? 0 }}" readonly>
                    </td>

                    <td>
                        <input type="number" step="0.01"
                               name="lines[{{ $i }}][igst_amount]"
                               class="form-control form-control-sm igst-input"
                               value="{{ $line['igst_amount'] ?? 0 }}" readonly>
                    </td>

                    <td>
                        <input type="number" step="0.01"
                               name="lines[{{ $i }}][total_amount]"
                               class="form-control form-control-sm totalamt-input"
                               value="{{ $line['total_amount'] ?? 0 }}" readonly>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- EXPENSE LINES --}}
    <h5 class="h6 mt-4 mb-2">Expenses (for service / freight / transport bills)</h5>
    {{-- <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle" id="expense-lines-table"> --}}
            <div class="table-responsive" id="expense-table-wrapper">
                <table class="table table-sm table-bordered align-middle text-nowrap" id="expense-lines-table">

            <thead class="table-light">
            <tr>
                <th style="width:18%">Ledger</th>
                <th style="width:18%">Project</th>
                <th>Description</th>
                <th style="width:10%">Amount</th>
                <th style="width:7%">GST %</th>
                <th style="width:9%">Tax</th>
                <th style="width:9%">CGST</th>
                <th style="width:9%">SGST</th>
                <th style="width:9%">IGST</th>
                <th style="width:10%">Total</th>
                <th style="width:6%">RCM</th>
            </tr>
            </thead>
            <tbody id="expense-lines-tbody">
            @foreach($expenseLines as $i => $ex)
                <tr data-exp-index="{{ $i }}">
                    <td>
                        <select name="expense_lines[{{ $i }}][account_id]" class="form-select form-select-sm exp-account">
                            <option value="">--</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" {{ (string) ($ex['account_id'] ?? '') === (string) $acc->id ? 'selected' : '' }}>
                                    {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                <td>
                    @php
    $selProj = old('expense_lines.' . $i . '.project_id', $ex['project_id'] ?? old('project_id', $bill->project_id ?? optional($bill->purchaseOrder)->project_id));
                    @endphp
                    <select name="expense_lines[{{ $i }}][project_id]" class="form-select form-select-sm exp-project">
                        <option value="">-- Bill Project (Default) --</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" @selected((string) $selProj === (string) $p->id)>
                                {{ $p->code }} - {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </td>
                    <td>
                        <input type="text"
                               name="expense_lines[{{ $i }}][description]"
                               class="form-control form-control-sm exp-desc"
                               value="{{ $ex['description'] }}">
                    </td>
                    <td>
                        <input type="number" step="0.01"
                               name="expense_lines[{{ $i }}][amount]"
                               class="form-control form-control-sm exp-amount"
                               value="{{ $ex['amount'] }}">
                    </td>
                    <td>
                        <input type="number" step="0.01"
                               name="expense_lines[{{ $i }}][tax_rate]"
                               class="form-control form-control-sm exp-taxrate"
                               value="{{ $ex['tax_rate'] ?? 0 }}">
                    </td>
                    <td>
                        <input type="number" step="0.01"
                               name="expense_lines[{{ $i }}][tax_amount]"
                               class="form-control form-control-sm exp-taxamt"
                               value="{{ $ex['tax_amount'] ?? 0 }}" readonly>
                    </td>
                    <td>
                        <input type="number" step="0.01"
                               name="expense_lines[{{ $i }}][cgst_amount]"
                               class="form-control form-control-sm exp-cgst"
                               value="{{ $ex['cgst_amount'] ?? 0 }}" readonly>
                    </td>
                    <td>
                        <input type="number" step="0.01"
                               name="expense_lines[{{ $i }}][sgst_amount]"
                               class="form-control form-control-sm exp-sgst"
                               value="{{ $ex['sgst_amount'] ?? 0 }}" readonly>
                    </td>
                    <td>
                        <input type="number" step="0.01"
                               name="expense_lines[{{ $i }}][igst_amount]"
                               class="form-control form-control-sm exp-igst"
                               value="{{ $ex['igst_amount'] ?? 0 }}" readonly>
                    </td>
                    <td>
                        <input type="number" step="0.01"
                               name="expense_lines[{{ $i }}][total_amount]"
                               class="form-control form-control-sm exp-total"
                               value="{{ $ex['total_amount'] ?? 0 }}" readonly>
                    </td>
                    <td class="text-center">
                        @if(!empty($ex['is_reverse_charge']))
                            <span class="badge text-bg-warning">Yes</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <hr class="my-3">

    {{-- TDS/TCS & SUMMARY --}}
    <div class="row g-3">
        <div class="col-md-6">
            <h5 class="h6 mb-2">TDS / TCS</h5>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">TDS Section</label>
                    <select name="tds_section" id="tds_section" class="form-select form-select-sm">
                        <option value="">-- None --</option>
                        @foreach($tdsSections as $sec)
                            <option value="{{ $sec->code }}" data-default-rate="{{ $sec->default_rate }}"
                                    {{ (string) old('tds_section', $bill->tds_section) === (string) $sec->code ? 'selected' : '' }}>
                                {{ $sec->code }} - {{ $sec->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">TDS %</label>
                    <input type="number" step="0.0001" name="tds_rate" id="tds_rate"
                           class="form-control form-control-sm"
                           value="{{ old('tds_rate', $bill->tds_rate ?? 0) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">TDS Amount</label>
                    <input type="number" step="0.01" name="tds_amount" id="tds_amount"
                           class="form-control form-control-sm"
                           value="{{ old('tds_amount', $bill->tds_amount ?? 0) }}">
                </div>

                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               name="tds_auto_calculate"
                               id="tds_auto_calculate"
                               value="1" {{ old('tds_auto_calculate', $bill->tds_auto_calculate ?? 0) ? 'checked' : '' }}>
                        <label class="form-check-label" for="tds_auto_calculate">
                            Auto-calculate TDS on Total Basic
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">TCS Section</label>
                    <input type="text" name="tcs_section" class="form-control form-control-sm"
                           value="{{ old('tcs_section', $bill->tcs_section) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">TCS %</label>
                    <input type="number" step="0.0001" name="tcs_rate" id="tcs_rate"
                           class="form-control form-control-sm"
                           value="{{ old('tcs_rate', $bill->tcs_rate ?? 0) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">TCS Amount</label>
                    <input type="number" step="0.01" name="tcs_amount" id="tcs_amount"
                           class="form-control form-control-sm"
                           value="{{ old('tcs_amount', $bill->tcs_amount ?? 0) }}">
                </div>
            </div>
        </div>
<div class="col-md-6">

    <div class="card border-0 shadow-sm">
        <div class="card-body">

            <h6 class="fw-semibold mb-3 border-bottom pb-2">
                Invoice Summary
            </h6>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">

                    <tr>
                        <th class="text-muted text-end small">Total Basic</th>
                        <td class="text-end fw-semibold">
                            ₹ <span id="sum_total_basic">{{ number_format($initTotalBasic, 2) }}</span>
                        </td>
                    </tr>

                    <tr>
                        <th class="text-muted text-end small">Total Tax</th>
                        <td class="text-end">
                            ₹ <span id="sum_total_tax">{{ number_format($initTotalTax, 2) }}</span>
                        </td>
                    </tr>

                    <tr>
                        <th class="text-muted text-end small">CGST</th>
                        <td class="text-end">
                            ₹ <span id="sum_total_cgst">{{ number_format($initTotalCgst, 2) }}</span>
                        </td>
                    </tr>

                    <tr>
                        <th class="text-muted text-end small">SGST</th>
                        <td class="text-end">
                            ₹ <span id="sum_total_sgst">{{ number_format($initTotalSgst, 2) }}</span>
                        </td>
                    </tr>

                    <tr>
                        <th class="text-muted text-end small">IGST</th>
                        <td class="text-end">
                            ₹ <span id="sum_total_igst">{{ number_format($initTotalIgst, 2) }}</span>
                        </td>
                    </tr>

                    <tr class="border-top">
                        <th class="text-end">Calculated Total</th>
                        <td class="text-end fw-semibold">
                            ₹ <span id="sum_calculated_total">{{ number_format($initCalculatedTotal, 2) }}</span>
                        </td>
                    </tr>

                    <tr>
                        <th class="text-end">Round Off</th>
                        <td class="text-end">
                            ₹ <span id="sum_round_off">{{ number_format($initRoundOff, 2) }}</span>
                        </td>
                    </tr>

                    <tr class="table-light">
                        <th class="text-end fw-semibold">Invoice Total</th>
                        <td>
                            <div class="input-group input-group-sm justify-content-end" style="max-width: 230px;">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" name="invoice_total" id="invoice_total"
                                    class="form-control text-end @error('invoice_total') is-invalid @enderror"
                                    value="{{ old('invoice_total', $initInvoiceTotal) }}">
                                <button type="button" class="btn btn-outline-secondary" id="btn_round_invoice_total">
                                    Round
                                </button>
                            </div>

                            @error('invoice_total')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                            @enderror
                        </td>
                    </tr>

                    <tr>
                        <th class="text-end text-success">+ TCS</th>
                        <td class="text-end">
                            ₹ <span id="sum_tcs">{{ number_format($initTcs, 2) }}</span>
                        </td>
                    </tr>

                    <tr>
                        <th class="text-end text-danger">– TDS</th>
                        <td class="text-end">
                            ₹ <span id="sum_tds">{{ number_format($initTds, 2) }}</span>
                        </td>
                    </tr>

                    <tr class="table-light border-top">
                        <th class="text-end fs-6">Net Payable</th>
                        <td class="text-end fw-bold fs-5">
                            ₹ <span id="sum_net">{{ number_format($initNet, 2) }}</span>
                        </td>
                    </tr>

                </table>
            </div>

            <div class="small text-muted mt-3">
                Final amounts are calculated server-side on save.
            </div>

        </div>
    </div>

</div>

    </div>

    <hr class="my-3">

    {{-- ATTACHMENTS --}}
<div class="card border-0 shadow-sm mt-4">
    <div class="card-body">

        <h6 class="fw-semibold border-bottom pb-2 mb-3">
            Attachments
        </h6>

        <div class="row g-4">

            <!-- Upload Section -->
            <div class="col-md-6">

                <label class="form-label fw-semibold small text-muted">
                    Upload Files
                </label>

                <input type="file" name="attachments[]" multiple
                    class="form-control form-control-sm @error('attachments.*') is-invalid @enderror"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">

                @error('attachments.*')
                    <div class="invalid-feedback d-block">
                        {{ $message }}
                    </div>
                @enderror

                <div class="small text-muted mt-2">
                    Allowed: PDF, Images, Word, Excel.
                </div>

            </div>


            <!-- Existing Attachments -->
            <div class="col-md-6">

                @if($isEdit && $bill->attachments && $bill->attachments->count())

                    <label class="form-label fw-semibold small text-muted">
                        Existing Files
                    </label>

                    <div class="border rounded p-3 bg-light" style="max-height: 180px; overflow:auto;">

                        @foreach($bill->attachments as $att)

                            <div class="d-flex justify-content-between align-items-start py-2 border-bottom">

                                <div class="text-truncate pe-2" style="max-width: 75%;">
                                    <a href="{{ Storage::disk('public')->url($att->path) }}" target="_blank" rel="noopener"
                                        class="text-decoration-none fw-semibold">

                                        {{ $att->original_name ?? basename($att->path) }}
                                    </a>

                                    <div class="small text-muted">
                                        {{ number_format(($att->size ?? 0) / 1024, 1) }} KB
                                    </div>
                                </div>

                                <div class="form-check ms-2">
                                    <input class="form-check-input" type="checkbox" name="attachments_delete[]"
                                        value="{{ $att->id }}" id="att_del_{{ $att->id }}">
                                    <label class="form-check-label small text-danger" for="att_del_{{ $att->id }}">
                                        Remove
                                    </label>
                                </div>

                            </div>

                        @endforeach

                    </div>

                    <div class="small text-muted mt-2">
                        Tick “Remove” and click Save to delete selected files.
                    </div>

                @endif

            </div>

        </div>

    </div>
</div>


    <hr class="my-3">

    <div class="d-flex justify-content-between">
        <a href="{{ route('purchase.bills.index') }}" class="btn btn-outline-secondary btn-sm">Back to list</a>
        <button type="submit" class="btn btn-primary btn-sm">
            {{ $isEdit ? 'Save Changes' : 'Save Draft' }}
        </button>
    </div>
</form>

{{--
  GRN/PO MODAL
  Note: moved to <body> via JS to avoid layout / z-index issues in nested containers.
--}}
<div class="modal fade" id="grnModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fetch from GRN (linked to PO)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Purchase Order</label>
                        <select id="po-select" class="form-select form-select-sm">
                            <option value="">-- Select PO --</option>
                        </select>
                        <div class="form-text">Only approved POs for selected supplier are shown.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Selected PO</label>
                        <input type="text" id="po-selected-display" class="form-control form-control-sm" readonly>
                    </div>
                </div>

                <hr class="my-3">

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle" id="grn-lines-table">
                        <thead class="table-light">
                        <tr>
                            <th style="width:3%;"><input type="checkbox" id="grn-select-all"></th>
                            <th style="width:16%;">GRN</th>
                            <th>Item</th>
                            <th style="width:8%;">Remaining</th>
                            <th style="width:10%;">Bill Qty</th>
                            <th style="width:10%;">Rate</th>
                            <th style="width:8%;">GST %</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div class="alert alert-info py-2 small mb-0">
                    Item/UOM will be locked in the bill once fetched. You can reduce qty (partial billing) but cannot exceed Remaining qty.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" id="apply-grn-selection">Apply Selection</button>
            </div>
        </div>
    </div>
</div>

<template id="item-line-template">
    <tr data-line-index="__INDEX__">
        <td>
            <input type="hidden" name="lines[__INDEX__][id]" value="">
            <input type="hidden" name="lines[__INDEX__][material_receipt_id]" value="">
            <input type="hidden" name="lines[__INDEX__][material_receipt_line_id]" value="" class="mr-line-id">
            <select name="lines[__INDEX__][item_id]" class="form-select form-select-sm item-select">
                <option value="">--</option>
                @foreach($items as $it)
                    <option value="{{ $it->id }}">{{ $it->code }} - {{ $it->name }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="lines[__INDEX__][uom_id]" class="form-select form-select-sm uom-select">
                <option value="">--</option>
                @foreach($uoms as $u)
                    <option value="{{ $u->id }}">{{ $u->code }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" step="0.0001" name="lines[__INDEX__][qty]" class="form-control form-control-sm qty-input" value=""></td>
        <td><input type="number" step="0.01" name="lines[__INDEX__][rate]" class="form-control form-control-sm rate-input" value=""></td>
        <td><input type="number" step="0.01" name="lines[__INDEX__][discount_percent]" class="form-control form-control-sm discpct-input" value="0"></td>
        <td><input type="number" step="0.01" name="lines[__INDEX__][discount_amount]" class="form-control form-control-sm discamt-input" value="0" readonly></td>
        <td><input type="number" step="0.01" name="lines[__INDEX__][basic_amount]" class="form-control form-control-sm basicamt-input" value="0" readonly></td>
        <td><input type="number" step="0.01" name="lines[__INDEX__][tax_rate]" class="form-control form-control-sm taxrate-input" value="0"></td>
        <td><input type="number" step="0.01" name="lines[__INDEX__][tax_amount]" class="form-control form-control-sm taxamt-input" value="0" readonly></td>
        <td><input type="number" step="0.01" name="lines[__INDEX__][cgst_amount]" class="form-control form-control-sm cgst-input" value="0" readonly></td>
        <td><input type="number" step="0.01" name="lines[__INDEX__][sgst_amount]" class="form-control form-control-sm sgst-input" value="0" readonly></td>
        <td><input type="number" step="0.01" name="lines[__INDEX__][igst_amount]" class="form-control form-control-sm igst-input" value="0" readonly></td>
        <td><input type="number" step="0.01" name="lines[__INDEX__][total_amount]" class="form-control form-control-sm totalamt-input" value="0" readonly></td>
    </tr>
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ------- Select2 for Supplier (search by typing) -------
    if (window.$ && $.fn.select2) {
        $('#supplier_id').select2({
            width: '100%'
        });
    }

    // ------- Helpers -------
    function toNum(v) {
        if (v === null || v === undefined || v === '') return 0;
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }
    function round2(n) {
        return Math.round((n + Number.EPSILON) * 100) / 100;
    }

    // ------- Auto Round (Tally style) -------
    // Default: enabled for NEW bill, disabled for EDIT (to preserve saved invoice total).
    // If user manually edits Invoice Total, auto-round is turned OFF until you click the Round button.
    var autoRoundEnabled = {{ $isEdit ? 'false' : 'true' }};

    function getSelectedSupplierGstStateCode() {
        // Prefer selected supplier branch GST state (if chosen), else fallback to supplier master GST state.
        var branchSel = document.getElementById('supplier_branch_id');
        if (branchSel && branchSel.value) {
            var bOpt = branchSel.options[branchSel.selectedIndex];
            var bState = (bOpt && bOpt.dataset) ? (bOpt.dataset.gstState || '') : '';
            if (bState) return bState;
        }
        var supSel = document.getElementById('supplier_id');
        var opt = supSel && supSel.options[supSel.selectedIndex];
        return (opt && opt.dataset) ? (opt.dataset.gstState || '') : '';
    }

    function getGstMode() {
        var supplierSel = document.getElementById('supplier_id');
        if (!supplierSel) return 'intra';
        var opt = supplierSel.options[supplierSel.selectedIndex];
        var companyState = supplierSel.dataset.companyGstState || '';
        var supplierState = getSelectedSupplierGstStateCode();
        if (!companyState || !supplierState) {
            // fallback: assume intra-state
            return 'intra';
        }
        return (String(companyState) === String(supplierState)) ? 'intra' : 'inter';
    }

    function calcSplit(taxable, taxRate) {
        taxable = toNum(taxable);
        taxRate = toNum(taxRate);
        var tax = round2(taxable * taxRate / 100);
        var mode = getGstMode();
        var cgst = 0, sgst = 0, igst = 0;
        if (tax <= 0) {
            return {tax: 0, cgst: 0, sgst: 0, igst: 0};
        }
        if (mode === 'inter') {
            igst = tax;
        } else {
            cgst = round2(tax / 2);
            sgst = round2(tax - cgst);
        }
        return {tax: tax, cgst: cgst, sgst: sgst, igst: igst};
    }

    // ------- Line calculations -------
    // ------- Supplier GSTIN / Branch (multi-GST) -------
    var supplierBranchSel = document.getElementById('supplier_branch_id');
    var branchesUrlTemplate = "{{ route('api.parties.branches', ['party' => '__PARTY__']) }}";

    async function loadSupplierBranches(supplierId, preferredId) {
        if (!supplierBranchSel) return;

        // Reset
        supplierBranchSel.innerHTML = '<option value="">-- Use Primary / Party GSTIN --</option>';
        supplierBranchSel.value = '';

        if (!supplierId) {
            return;
        }

        var url = branchesUrlTemplate.replace('__PARTY__', encodeURIComponent(String(supplierId)));

        let resp;
        try {
            resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
        } catch (e) {
            console.warn('Failed to load supplier branches (network error).', e);
            return;
        }

        let json = null;
        try {
            json = await resp.json();
        } catch (e) {
            console.warn('Failed to parse branches response as JSON.');
        }

        if (!resp.ok || !json || !Array.isArray(json.branches)) {
            return;
        }

        (json.branches || []).forEach(function (b) {
            var opt = document.createElement('option');
            opt.value = b.id;

            var gst = (b.gstin || '').toString();
            var stateCode = (b.gst_state_code || (gst.length >= 2 ? gst.substring(0, 2) : '') || '').toString();
            opt.dataset.gstState = stateCode;

            var label = '';
            if (b.branch_name) {
                label += b.branch_name + ' - ';
            }
            label += (b.gstin || '');
            opt.textContent = label;

            supplierBranchSel.appendChild(opt);
        });

        // Choose selection: preferredId > data-selected (old/edit)
        var selected = (preferredId !== undefined && preferredId !== null) ? String(preferredId) : (supplierBranchSel.dataset.selected || '');
        if (selected) {
            supplierBranchSel.value = selected;
        }
    }

    function recalcItemRow(tr) {
        if (!tr) return;
        var qty = toNum(tr.querySelector('.qty-input')?.value);
        var rate = toNum(tr.querySelector('.rate-input')?.value);
        var discPct = toNum(tr.querySelector('.discpct-input')?.value);
        var taxRate = toNum(tr.querySelector('.taxrate-input')?.value);

        var gross = qty * rate;
        var disc = round2(gross * discPct / 100);
        var taxable = round2(gross - disc);

        var split = calcSplit(taxable, taxRate);
        var total = round2(taxable + split.tax);

        var discAmt = tr.querySelector('.discamt-input');
        var basicAmt = tr.querySelector('.basicamt-input');
        var taxAmt = tr.querySelector('.taxamt-input');
        var cgst = tr.querySelector('.cgst-input');
        var sgst = tr.querySelector('.sgst-input');
        var igst = tr.querySelector('.igst-input');
        var tot = tr.querySelector('.totalamt-input');

        if (discAmt) discAmt.value = disc;
        if (basicAmt) basicAmt.value = taxable;
        if (taxAmt) taxAmt.value = split.tax;
        if (cgst) cgst.value = split.cgst;
        if (sgst) sgst.value = split.sgst;
        if (igst) igst.value = split.igst;
        if (tot) tot.value = total;
    }

    function recalcExpenseRow(tr) {
        if (!tr) return;
        var amt = toNum(tr.querySelector('.exp-amount')?.value);
        var taxRate = toNum(tr.querySelector('.exp-taxrate')?.value);
        var split = calcSplit(amt, taxRate);
        var total = round2(amt + split.tax);

        var taxAmt = tr.querySelector('.exp-taxamt');
        var cgst = tr.querySelector('.exp-cgst');
        var sgst = tr.querySelector('.exp-sgst');
        var igst = tr.querySelector('.exp-igst');
        var tot = tr.querySelector('.exp-total');

        if (taxAmt) taxAmt.value = split.tax;
        if (cgst) cgst.value = split.cgst;
        if (sgst) sgst.value = split.sgst;
        if (igst) igst.value = split.igst;
        if (tot) tot.value = total;
    }

    function recalcSummary() {
        var totalBasic = 0, totalTax = 0, totalCgst = 0, totalSgst = 0, totalIgst = 0, calculatedTotal = 0;

        // Item lines
        document.querySelectorAll('#bill-lines-tbody tr').forEach(function (tr) {
            var itemId = tr.querySelector('.item-select')?.value;
            var qty = toNum(tr.querySelector('.qty-input')?.value);
            if (!itemId || qty <= 0) return;

            totalBasic += toNum(tr.querySelector('.basicamt-input')?.value);
            totalTax   += toNum(tr.querySelector('.taxamt-input')?.value);
            totalCgst  += toNum(tr.querySelector('.cgst-input')?.value);
            totalSgst  += toNum(tr.querySelector('.sgst-input')?.value);
            totalIgst  += toNum(tr.querySelector('.igst-input')?.value);
            calculatedTotal += toNum(tr.querySelector('.totalamt-input')?.value);
        });

        // Expense lines
        document.querySelectorAll('#expense-lines-tbody tr').forEach(function (tr) {
            var accId = tr.querySelector('.exp-account')?.value;
            var amt = toNum(tr.querySelector('.exp-amount')?.value);
            if (!accId || amt <= 0) return;

            totalBasic += amt;
            totalTax   += toNum(tr.querySelector('.exp-taxamt')?.value);
            totalCgst  += toNum(tr.querySelector('.exp-cgst')?.value);
            totalSgst  += toNum(tr.querySelector('.exp-sgst')?.value);
            totalIgst  += toNum(tr.querySelector('.exp-igst')?.value);
            calculatedTotal += toNum(tr.querySelector('.exp-total')?.value);
        });

        totalBasic = round2(totalBasic);
        totalTax   = round2(totalTax);
        totalCgst  = round2(totalCgst);
        totalSgst  = round2(totalSgst);
        totalIgst  = round2(totalIgst);
        calculatedTotal = round2(calculatedTotal);

        // Auto-calc TDS if enabled
        var tdsRateEl = document.getElementById('tds_rate');
        var tdsAmtEl  = document.getElementById('tds_amount');
        var tdsAutoEl = document.getElementById('tds_auto_calculate');
        var tdsRate = toNum(tdsRateEl ? tdsRateEl.value : 0);
        if (tdsAutoEl && tdsAutoEl.checked && tdsAmtEl) {
            tdsAmtEl.value = round2(totalBasic * tdsRate / 100);
        }

        var tcsAmtEl = document.getElementById('tcs_amount');
        var tcsAmt = toNum(tcsAmtEl ? tcsAmtEl.value : 0);
        var tdsAmt = toNum(tdsAmtEl ? tdsAmtEl.value : 0);

        // Invoice Total (Tally style Auto-Round)
        // - Default (new bills): auto-round to nearest rupee from calculated total
        // - If user edits Invoice Total: switch to manual mode
        var invoiceTotalEl = document.getElementById('invoice_total');
        var invoiceTotalRaw = invoiceTotalEl ? String(invoiceTotalEl.value ?? '').trim() : '';

        var invoiceTotal = calculatedTotal;

        if (autoRoundEnabled) {
            // Auto-round to nearest rupee (0 decimals)
            invoiceTotal = Math.round(calculatedTotal);
            invoiceTotal = round2(invoiceTotal);

            // Do not overwrite while user is typing in the field
            if (invoiceTotalEl && document.activeElement !== invoiceTotalEl) {
                invoiceTotalEl.value = invoiceTotal.toFixed(2);
            }
        } else {
            // Manual mode: use typed invoice_total; if blank fallback to calculated total
            invoiceTotal = (invoiceTotalRaw === '') ? calculatedTotal : toNum(invoiceTotalRaw);
            invoiceTotal = round2(invoiceTotal);
        }

        var roundOff = round2(invoiceTotal - calculatedTotal);
        var net = round2(invoiceTotal + tcsAmt - tdsAmt);

        // Update UI
        document.getElementById('sum_total_basic').textContent = totalBasic.toFixed(2);
        document.getElementById('sum_total_tax').textContent = totalTax.toFixed(2);
        document.getElementById('sum_total_cgst').textContent = totalCgst.toFixed(2);
        document.getElementById('sum_total_sgst').textContent = totalSgst.toFixed(2);
        document.getElementById('sum_total_igst').textContent = totalIgst.toFixed(2);
        document.getElementById('sum_calculated_total').textContent = calculatedTotal.toFixed(2);
        document.getElementById('sum_round_off').textContent = roundOff.toFixed(2);
        document.getElementById('sum_tcs').textContent = tcsAmt.toFixed(2);
        document.getElementById('sum_tds').textContent = tdsAmt.toFixed(2);
        document.getElementById('sum_net').textContent = net.toFixed(2);
    }

    function recalcAll() {
        document.querySelectorAll('#bill-lines-tbody tr').forEach(recalcItemRow);
        document.querySelectorAll('#expense-lines-tbody tr').forEach(recalcExpenseRow);
        recalcSummary();
    }

    // Events: any change in lines should recalc
    document.getElementById('bill-lines-tbody')?.addEventListener('input', function (e) {
        var tr = e.target.closest('tr');
        if (!tr) return;
        if (e.target.classList.contains('qty-input') || e.target.classList.contains('rate-input') || e.target.classList.contains('discpct-input') || e.target.classList.contains('taxrate-input')) {
            recalcItemRow(tr);
            recalcSummary();
        }
    });
    document.getElementById('expense-lines-tbody')?.addEventListener('input', function (e) {
        var tr = e.target.closest('tr');
        if (!tr) return;
        if (e.target.classList.contains('exp-amount') || e.target.classList.contains('exp-taxrate')) {
            recalcExpenseRow(tr);
            recalcSummary();
        }
    });

    document.getElementById('supplier_id')?.addEventListener('change', async function () {
        // Supplier change affects GST split + branch list; reload branches then recalc
        if (supplierBranchSel) {
            supplierBranchSel.dataset.selected = '';
        }
        await loadSupplierBranches(this.value, '');
        recalcAll();
    });

    document.getElementById('supplier_branch_id')?.addEventListener('change', function () {
        // Branch selection affects GST split
        recalcAll();
    });
    document.getElementById('tds_rate')?.addEventListener('input', recalcSummary);
    document.getElementById('tds_amount')?.addEventListener('input', recalcSummary);
    document.getElementById('tds_auto_calculate')?.addEventListener('change', recalcSummary);
    document.getElementById('tcs_amount')?.addEventListener('input', recalcSummary);
    document.getElementById('invoice_total')?.addEventListener('input', function () {
        var raw = String(this.value ?? '').trim();
        // If user types a value => manual mode; if they clear field => return to auto round
        autoRoundEnabled = (raw === '');
        recalcSummary();
    });

    document.getElementById('btn_round_invoice_total')?.addEventListener('click', function () {
        var calcEl = document.getElementById('sum_calculated_total');
        var calculatedTotal = calcEl ? toNum(calcEl.textContent) : 0;

        autoRoundEnabled = true;

        var invoiceTotalEl = document.getElementById('invoice_total');
        if (invoiceTotalEl) {
            invoiceTotalEl.value = Math.round(calculatedTotal).toFixed(2);
        }
        recalcSummary();
    });

    // TDS default rate from master when selecting section
    document.getElementById('tds_section')?.addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        if (opt && opt.dataset && opt.dataset.defaultRate && document.getElementById('tds_rate')) {
            document.getElementById('tds_rate').value = opt.dataset.defaultRate;
        }
        recalcSummary();
    });

    // ------- Item rows dynamic add -------
    var itemTemplate = document.getElementById('item-line-template');
    var billLinesTbody = document.getElementById('bill-lines-tbody');
    function addItemRow() {
        if (!itemTemplate || !billLinesTbody) return null;
        var nextIndex = billLinesTbody.querySelectorAll('tr').length;
        var html = itemTemplate.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        var tmp = document.createElement('tbody');
        tmp.innerHTML = html.trim();
        var tr = tmp.firstElementChild;
        billLinesTbody.appendChild(tr);
        return tr;
    }
    document.getElementById('btnAddItemRow')?.addEventListener('click', function () {
        addItemRow();
    });

    // ------- GRN/PO Modal Logic -------
    var grnModalEl = document.getElementById('grnModal');
    var grnModal = (grnModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) ? new bootstrap.Modal(grnModalEl) : null;

    // Move modal to body (prevents nested container issues)
    if (grnModalEl && grnModalEl.parentElement !== document.body) {
        document.body.appendChild(grnModalEl);
    }

    var btnFetchGrn = document.getElementById('btnFetchGrn');
    var poSelect = document.getElementById('po-select');

    function setProjectFromPoOption(optionEl) {
        var projSelect = document.getElementById('project_id');
        if (!projSelect) return;

        var projId = optionEl && optionEl.dataset ? (optionEl.dataset.projectId || '') : '';
        // Set value
        projSelect.value = projId;

        // If Select2 is enabled, trigger change so UI updates
        try {
            if (typeof window.$ !== 'undefined' && window.$(projSelect).data('select2')) {
                window.$(projSelect).val(projId).trigger('change');
            }
        } catch (e) {
            // ignore
        }

        // Also trigger native change (so expense-line defaults sync even if Select2 is not used)
        try {
            projSelect.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e) {
            // ignore
        }
    }

    async function setBranchFromPoOption(optionEl) {
        var branchId = optionEl && optionEl.dataset ? (optionEl.dataset.vendorBranchId || '') : '';
        var supplierId = document.getElementById('supplier_id')?.value || '';
        if (!supplierId) return;
        // Persist preferred branch selection for this session
        if (supplierBranchSel) {
            supplierBranchSel.dataset.selected = branchId;
        }
        await loadSupplierBranches(supplierId, branchId);
        recalcAll();
    }

    // ------- Phase-B: Expense line Project defaults -------
    function syncExpenseProjectDefaults() {
        var headerProj = document.getElementById('project_id');
        if (!headerProj) return;
        var headerVal = headerProj.value || '';
        document.querySelectorAll('select.exp-project').forEach(function (sel) {
            // Only auto-fill if currently blank
            if (!sel.value && headerVal) {
                sel.value = headerVal;
                // If Select2 is applied globally, trigger change
                try {
                    if (typeof window.$ !== 'undefined' && window.$(sel).data('select2')) {
                        window.$(sel).val(headerVal).trigger('change');
                    }
                } catch (e) {}
            }
        });
    }

    document.getElementById('project_id')?.addEventListener('change', function () {
        syncExpenseProjectDefaults();
    });

    // Initial sync (handles edit + PO fetch default project)
    syncExpenseProjectDefaults();

    var grnTbody = document.querySelector('#grn-lines-table tbody');

    function clearGrnModal() {
        if (poSelect) {
            poSelect.innerHTML = '<option value="">-- Select PO --</option>';
        }
        if (grnTbody) {
            grnTbody.innerHTML = '';
        }
        document.getElementById('po-selected-display').value = '';
    }

    async function loadPurchaseOrdersForSupplier(supplierId) {
        clearGrnModal();

        if (!supplierId) {
            return;
        }

        var url = "{{ route('purchase.bills.ajax.purchase-orders') }}" + '?supplier_id=' + encodeURIComponent(supplierId);

        var resp;
        try {
            resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
        } catch (e) {
            alert('Failed to load purchase orders (network error).');
            return;
        }

        var json = null;
        try {
            json = await resp.json();
        } catch (e) {
            // non-JSON response
        }

        if (!resp.ok || !json || !json.success) {
            alert('Failed to load purchase orders.');
            return;
        }

        (json.orders || []).forEach(function (po) {
            var opt = document.createElement('option');
            opt.value = po.id;

            var label = (po.code || ('PO#' + po.id));
            if (po.po_date) {
                label += ' | ' + po.po_date;
            }
            if (po.project_code) {
                label += ' | ' + po.project_code;
            }
            if (po.project_name) {
                label += ' - ' + po.project_name;
            }

            opt.textContent = label;
            opt.dataset.display = label;
            opt.dataset.projectId = (po.project_id || "");
            opt.dataset.vendorBranchId = (po.vendor_branch_id || "");
            poSelect.appendChild(opt);
        });

        // Auto select existing PO if present
        var existingPoId = document.getElementById('purchase_order_id')?.value;
        if (existingPoId) {
            poSelect.value = existingPoId;
            if (poSelect.value) {
                document.getElementById('po-selected-display').value = poSelect.options[poSelect.selectedIndex].dataset.display || '';
                setProjectFromPoOption(poSelect.options[poSelect.selectedIndex]);
                await setBranchFromPoOption(poSelect.options[poSelect.selectedIndex]);
                await loadGrnLinesForPo(supplierId, existingPoId);
            }
        }
    }

    async function loadGrnLinesForPo(supplierId, poId) {
        if (!supplierId || !poId) {
            if (grnTbody) grnTbody.innerHTML = '';
            return;
        }

        if (grnTbody) {
            grnTbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>';
        }

        var billId = "{{ $isEdit ? $bill->id : '' }}";

        var url = "{{ route('purchase.bills.ajax.grn-lines') }}"
            + '?supplier_id=' + encodeURIComponent(supplierId)
            + '&purchase_order_id=' + encodeURIComponent(poId)
            + (billId ? ('&bill_id=' + encodeURIComponent(billId)) : '');

        var resp;
        try {
            resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
        } catch (e) {
            if (grnTbody) grnTbody.innerHTML = '';
            alert('Failed to load GRN lines (network error).');
            return;
        }

        var json = null;
        try {
            json = await resp.json();
        } catch (e) {
            // non-JSON response
        }

        if (!json || !json.success) {
            if (grnTbody) grnTbody.innerHTML = '';
            alert('Failed to load GRN lines');
            return;
        }

        if (!json.lines || json.lines.length === 0) {
            if (grnTbody) grnTbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No billable GRN lines found.</td></tr>';
            return;
        }

        if (grnTbody) grnTbody.innerHTML = '';

        json.lines.forEach(function (line) {
            var tr = document.createElement('tr');
            tr.dataset.materialReceiptId = line.material_receipt_id;
            tr.dataset.materialReceiptLineId = line.material_receipt_line_id;
            tr.dataset.itemId = line.item_id;
            tr.dataset.uomId = line.uom_id;
            tr.dataset.remainingQty = line.remaining_qty;
            tr.dataset.rate = line.rate;
            tr.dataset.taxRate = line.tax_rate;
            tr.dataset.invoiceNumber = line.invoice_number || '';
            tr.dataset.challanNumber = line.challan_number || '';

            var grnText = (line.grn_no || '') + (line.grn_date ? ('\n' + line.grn_date) : '');

            tr.innerHTML = `
                <td class="text-center"><input type="checkbox" class="grn-line-check"></td>
                <td><div style="white-space:pre-line">${grnText}</div></td>
                <td>
                    <div class="fw-semibold">${(line.item_code ? (line.item_code + ' - ') : '')}${(line.item_name || ('Item#' + line.item_id))}</div>
                    ${line.uom_code ? '<div class="small text-muted">UOM: ' + line.uom_code + '</div>' : ''}
                </td>
                <td class="text-end">${Number(line.remaining_qty).toFixed(4)}</td>
                <td>
                    <input type="number" step="0.0001" class="form-control form-control-sm grn-bill-qty" value="${Number(line.remaining_qty).toFixed(4)}" min="0" max="${Number(line.remaining_qty)}">
                </td>
                <td class="text-end">${Number(line.rate).toFixed(2)}</td>
                <td class="text-end">${Number(line.tax_rate).toFixed(2)}</td>
            `;

            grnTbody.appendChild(tr);
        });
    }

    btnFetchGrn?.addEventListener('click', async function () {
        var supplierId = document.getElementById('supplier_id')?.value;
        if (!supplierId) {
            alert('Please select Supplier / Contractor first.');
            return;
        }
        await loadPurchaseOrdersForSupplier(supplierId);
        grnModal?.show();
    });

    poSelect?.addEventListener('change', async function () {
        var supplierId = document.getElementById('supplier_id')?.value;
        var poId = this.value;
        var disp = this.options[this.selectedIndex]?.dataset?.display || '';
        document.getElementById('po-selected-display').value = disp;
        setProjectFromPoOption(this.options[this.selectedIndex]);
        await setBranchFromPoOption(this.options[this.selectedIndex]);
        await loadGrnLinesForPo(supplierId, poId);
    });

    document.getElementById('grn-select-all')?.addEventListener('change', function () {
        var checked = this.checked;
        document.querySelectorAll('#grn-lines-table .grn-line-check').forEach(function (cb) {
            cb.checked = checked;
        });
    });

    // Apply GRN selection to item lines
    document.getElementById('apply-grn-selection')?.addEventListener('click', function () {
        var selected = Array.from(document.querySelectorAll('#grn-lines-table tbody tr'))
            .filter(function (tr) {
                return tr.querySelector('.grn-line-check')?.checked;
            });

        if (selected.length === 0) {
            alert('Please select at least one GRN line.');
            return;
        }

        var invoiceField = document.getElementById('invoice_number');
        var challanField = document.getElementById('challan_number');

        var invoiceSet = new Set();
        var challanSet = new Set();

        // Ensure PO linked
        var poId = poSelect?.value || '';
        if (poId) {
            document.getElementById('purchase_order_id').value = poId;
            document.getElementById('purchase_order_display').value = document.getElementById('po-selected-display').value;
        }

        selected.forEach(function (grnRow) {
            var itemId = grnRow.dataset.itemId;
            var uomId = grnRow.dataset.uomId;
            var mrId = grnRow.dataset.materialReceiptId;
            var mrLineId = grnRow.dataset.materialReceiptLineId;
            var maxQty = toNum(grnRow.dataset.remainingQty);
            var qty = toNum(grnRow.querySelector('.grn-bill-qty')?.value);
            if (qty <= 0) return;
            if (qty > maxQty) {
                qty = maxQty;
            }
            var rate = toNum(grnRow.dataset.rate);
            var taxRate = toNum(grnRow.dataset.taxRate);

            if (grnRow.dataset.invoiceNumber) invoiceSet.add(grnRow.dataset.invoiceNumber);
            if (grnRow.dataset.challanNumber) challanSet.add(grnRow.dataset.challanNumber);

            // Find an empty row
            var targetRow = null;
            Array.from(document.querySelectorAll('#bill-lines-tbody tr')).some(function (tr) {
                var itemVal = tr.querySelector('.item-select')?.value;
                if (!itemVal) {
                    targetRow = tr;
                    return true;
                }
                return false;
            });
            if (!targetRow) {
                targetRow = addItemRow();
            }
            if (!targetRow) return;

            targetRow.classList.add('table-warning');
            targetRow.dataset.grnLinked = '1';
            targetRow.dataset.lockItemId = itemId;
            targetRow.dataset.lockUomId = uomId;
            targetRow.dataset.maxQty = String(maxQty);

            targetRow.querySelector('input[name$="[material_receipt_id]"]').value = mrId;
            targetRow.querySelector('input[name$="[material_receipt_line_id]"]').value = mrLineId;

            var itemSel = targetRow.querySelector('.item-select');
            var uomSel = targetRow.querySelector('.uom-select');
            if (itemSel) itemSel.value = itemId;
            if (uomSel) uomSel.value = uomId;

            var qtyInput = targetRow.querySelector('.qty-input');
            if (qtyInput) {
                qtyInput.value = qty;
                qtyInput.max = String(maxQty);
            }
            var rateInput = targetRow.querySelector('.rate-input');
            if (rateInput) rateInput.value = rate;

            var taxRateInput = targetRow.querySelector('.taxrate-input');
            if (taxRateInput) taxRateInput.value = taxRate;

            recalcItemRow(targetRow);
        });

        // Auto-fill invoice/challan only if user hasn't already typed AND exactly 1 unique value.
        if (invoiceField && !invoiceField.value && invoiceSet.size === 1) {
            invoiceField.value = Array.from(invoiceSet)[0];
        }
        if (challanField && !challanField.value && challanSet.size === 1) {
            challanField.value = Array.from(challanSet)[0];
        }
        if (invoiceField && !invoiceField.value && invoiceSet.size > 1) {
            console.warn('Multiple invoice numbers found in selected GRNs. Invoice No not auto-filled.');
        }
        if (challanField && !challanField.value && challanSet.size > 1) {
            console.warn('Multiple challan numbers found in selected GRNs. Challan No not auto-filled.');
        }

        // Lock GRN linked rows: prevent changing item/uom, enforce max qty
        lockGrnLinkedRows();

        recalcSummary();
        grnModal?.hide();
    });

    // ------- Locking for GRN linked lines -------
    function lockGrnLinkedRows() {
        document.querySelectorAll('#bill-lines-tbody tr').forEach(function (tr) {
            var mrLineId = tr.querySelector('.mr-line-id')?.value;
            if (!mrLineId) return;

            // Mark
            tr.dataset.grnLinked = '1';
            var itemSel = tr.querySelector('.item-select');
            var uomSel  = tr.querySelector('.uom-select');
            if (itemSel) tr.dataset.lockItemId = itemSel.value;
            if (uomSel) tr.dataset.lockUomId = uomSel.value;

            // Prevent changing item/uom
            itemSel?.addEventListener('change', function () {
                if (tr.dataset.grnLinked === '1') {
                    this.value = tr.dataset.lockItemId || '';
                    alert('Item is locked because this line is linked to a GRN.');
                }
            });
            uomSel?.addEventListener('change', function () {
                if (tr.dataset.grnLinked === '1') {
                    this.value = tr.dataset.lockUomId || '';
                    alert('UOM is locked because this line is linked to a GRN.');
                }
            });

            // Enforce max qty (if known)
            var qtyInput = tr.querySelector('.qty-input');
            qtyInput?.addEventListener('input', function () {
                var max = toNum(this.max || tr.dataset.maxQty || 0);
                if (max > 0 && toNum(this.value) > max) {
                    this.value = max;
                    alert('Qty cannot exceed remaining GRN qty.');
                }
                recalcItemRow(tr);
                recalcSummary();
            });
        });
    }

    lockGrnLinkedRows();

    // Initial branch load (edit mode / old input)
    var initialSupplierId = document.getElementById('supplier_id')?.value || '';
    loadSupplierBranches(initialSupplierId).finally(function () {
        recalcAll();
    });
});
</script>
@endpush
