@php
    /**
     * Client RA Bill Form (Sales Invoice)
     *
     * Expected variables (from controller):
     * - $clients, $projects, $uoms, $revenueAccounts
     * - $selectedClient (nullable), $selectedProject (nullable)
     * - $previousRa (nullable)
     * - $prefillLines (array|null) // when copying previous RA lines
     * - $nextRaNumber (string)
     * - $tdsSections (collection)
     *
     * When editing, pass $clientRa (ClientRaBill model).
     */

    $editing = isset($clientRa) && $clientRa && $clientRa->exists;

    $action = $editing
        ? route('accounting.client-ra.update', $clientRa)
        : route('accounting.client-ra.store');

    $defaultLine = [
        'id' => null,
        'boq_item_code' => '',
        'revenue_account_id' => null,
        'description' => '',
        'uom_id' => null,
        'contracted_qty' => 0,
        'previous_qty' => 0,
        'current_qty' => 0,
        'rate' => 0,
        'sac_hsn_code' => '',
        'remarks' => '',
    ];

    if ($editing) {
        $modelLines = $clientRa->lines->map(function ($l) {
            return [
                'id' => $l->id,
                'boq_item_code' => $l->boq_item_code,
                'revenue_account_id' => $l->revenue_account_id,
                'description' => $l->description,
                'uom_id' => $l->uom_id,
                'contracted_qty' => $l->contracted_qty,
                'previous_qty' => $l->previous_qty,
                'current_qty' => $l->current_qty,
                'rate' => $l->rate,
                'sac_hsn_code' => $l->sac_hsn_code,
                'remarks' => $l->remarks,
            ];
        })->toArray();

        $lines = old('lines', $modelLines);
    } else {
        $lines = old('lines', $prefillLines ?? [$defaultLine]);
    }
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if($editing)
        @method('PUT')
    @endif


    @if(!$editing && request()->boolean('copy_prev_lines') && empty($prefillLines))
        <div class="alert alert-warning py-2 mb-3">
            No previous Approved/Posted RA Bill lines found to copy for the selected Client + Project.
        </div>
    @endif

    <div class="row g-3 mb-2">
        <div class="col-md-3">
            <label class="form-label">RA Number</label>
            <input type="text" class="form-control form-control-sm" value="{{ $editing ? $clientRa->ra_number : ($nextRaNumber ?? '') }}" disabled>
            @if(!$editing && isset($previousRa) && $previousRa)
                <div class="form-text">Previous RA: {{ $previousRa->ra_number }} (Seq: {{ $previousRa->ra_sequence }})</div>
            @endif
        </div>

        <div class="col-md-3">
            <label class="form-label">Bill Date <span class="text-danger">*</span></label>
            <input type="date" name="bill_date" class="form-control form-control-sm" value="{{ old('bill_date', optional($clientRa->bill_date ?? null)->format('Y-m-d')) }}" required>
        </div>

        <div class="col-md-3">
            <label class="form-label">Due Date</label>
            <input type="date" name="due_date" class="form-control form-control-sm" value="{{ old('due_date', optional($clientRa->due_date ?? null)->format('Y-m-d')) }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">Revenue Type <span class="text-danger">*</span></label>
            @php $revType = old('revenue_type', $clientRa->revenue_type ?? 'service'); @endphp
            <select name="revenue_type" class="form-select form-select-sm" required>
                @foreach(['fabrication'=>'Fabrication','erection'=>'Erection','supply'=>'Supply','service'=>'Service','other'=>'Other'] as $k => $v)
                    <option value="{{ $k }}" @selected($revType === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label">Client <span class="text-danger">*</span></label>

            @if($editing)
                <input type="text" class="form-control form-control-sm" value="{{ $clientRa->client?->name }}" disabled>
            @else
                <select name="client_id" class="form-select form-select-sm" required>
                    <option value="">-- Select --</option>
                    @foreach($clients as $p)
                        <option value="{{ $p->id }}" @selected(old('client_id', optional($selectedClient)->id) == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="col-md-4">
            <label class="form-label">Project <span class="text-danger">*</span></label>

            @if($editing)
                <input type="text" class="form-control form-control-sm" value="{{ $clientRa->project?->name }}" disabled>
            @else
                <select name="project_id" class="form-select form-select-sm" required>
                    <option value="">-- Select --</option>
                    @foreach($projects as $proj)
                        <option value="{{ $proj->id }}" @selected(old('project_id', optional($selectedProject)->id) == $proj->id)>{{ $proj->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="col-md-4">
            <label class="form-label">Contract / PO Numbers</label>
            <div class="input-group input-group-sm">
                <input type="text" name="contract_number" class="form-control" placeholder="Contract No" value="{{ old('contract_number', $clientRa->contract_number ?? '') }}">
                <input type="text" name="po_number" class="form-control" placeholder="PO No" value="{{ old('po_number', $clientRa->po_number ?? '') }}">
            </div>
        </div>

        <div class="col-md-3">
            <label class="form-label">Period From</label>
            <input type="date" name="period_from" class="form-control form-control-sm" value="{{ old('period_from', optional($clientRa->period_from ?? null)->format('Y-m-d')) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Period To</label>
            <input type="date" name="period_to" class="form-control form-control-sm" value="{{ old('period_to', optional($clientRa->period_to ?? null)->format('Y-m-d')) }}">
        </div>

        <div class="col-md-6">
            <label class="form-label">Remarks</label>
            <input type="text" name="remarks" class="form-control form-control-sm" value="{{ old('remarks', $clientRa->remarks ?? '') }}">
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-2">
            <label class="form-label">Retention %</label>
            <input type="number" step="0.0001" name="retention_percent" class="form-control form-control-sm js-calc" value="{{ old('retention_percent', $clientRa->retention_percent ?? 0) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">Other Deductions</label>
            <input type="number" step="0.01" name="other_deductions" class="form-control form-control-sm js-calc" value="{{ old('other_deductions', $clientRa->other_deductions ?? 0) }}">
        </div>
        <div class="col-md-8">
            <label class="form-label">Deduction Remarks</label>
            <input type="text" name="deduction_remarks" class="form-control form-control-sm" value="{{ old('deduction_remarks', $clientRa->deduction_remarks ?? '') }}">
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-2">
            <label class="form-label">CGST %</label>
            <input type="number" step="0.0001" name="cgst_rate" class="form-control form-control-sm js-calc" value="{{ old('cgst_rate', $clientRa->cgst_rate ?? 0) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">SGST %</label>
            <input type="number" step="0.0001" name="sgst_rate" class="form-control form-control-sm js-calc" value="{{ old('sgst_rate', $clientRa->sgst_rate ?? 0) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">IGST %</label>
            <input type="number" step="0.0001" name="igst_rate" class="form-control form-control-sm js-calc" value="{{ old('igst_rate', $clientRa->igst_rate ?? 0) }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">TDS %</label>
            <input type="number" step="0.0001" name="tds_rate" class="form-control form-control-sm js-calc" value="{{ old('tds_rate', $clientRa->tds_rate ?? 0) }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">TDS Section</label>

            @if(isset($tdsSections) && $tdsSections->count())
                <select name="tds_section" id="tds_section" class="form-select form-select-sm">
                    <option value="">-- None --</option>
                    @foreach($tdsSections as $sec)
                        <option value="{{ $sec->code }}" data-rate="{{ $sec->default_rate }}" {{ old('tds_section', $clientRa->tds_section ?? '') == $sec->code ? 'selected' : '' }}>
                            {{ $sec->code }} - {{ $sec->name }} ({{ rtrim(rtrim(number_format((float) $sec->default_rate, 4), '0'), '.') }}%)
                        </option>
                    @endforeach
                </select>
                <div class="form-text">
                    Manage: <a href="{{ route('accounting.tds-sections.index') }}" target="_blank">TDS Sections</a>
                </div>
            @else
                <input type="text" name="tds_section" class="form-control form-control-sm" placeholder="e.g. 194J" value="{{ old('tds_section', $clientRa->tds_section ?? '') }}">
            @endif
        </div>
    </div>

    <hr>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">BOQ / Milestone Lines</h2>
        <div class="d-flex gap-2">
            @if(!$editing)
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-copy-prev-lines">
                    Copy Previous RA Lines
                </button>
            @endif
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-line">+ Add Line</button>
        </div>
    </div>

    <div class="text-muted small mb-2">
        BOQ/Milestone Code is optional. If this project does not have a BOQ, leave it blank and enter Description, Current Qty and Rate.
    </div>


    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered align-middle" id="lines-table">
            <thead class="table-light">
            <tr>
                <th style="width: 9%">BOQ/Milestone Code <span class="text-muted">(optional)</span></th>
                <th style="width: 14%">Revenue A/c</th>
                <th>Description <span class="text-danger">*</span></th>
                <th style="width: 8%">UOM</th>
                <th class="text-end" style="width: 9%">Prev Qty</th>
                <th class="text-end" style="width: 9%">Curr Qty <span class="text-danger">*</span></th>
                <th class="text-end" style="width: 9%">Rate <span class="text-danger">*</span></th>
                <th class="text-end" style="width: 10%">Curr Amt</th>
                <th style="width: 8%">HSN/SAC</th>
                <th style="width: 9%">Remarks</th>
                <th style="width: 5%"></th>
            </tr>
            </thead>
            <tbody>
            @foreach($lines as $i => $line)
                <tr class="line-row">
                    <td>
                        @if(!empty($line['id']))
                            <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $line['id'] }}">
                        @endif
                        <input type="text" name="lines[{{ $i }}][boq_item_code]" class="form-control form-control-sm" value="{{ $line['boq_item_code'] ?? '' }}" placeholder="(optional)">
                    </td>
                    <td>
                        <select name="lines[{{ $i }}][revenue_account_id]" class="form-select form-select-sm">
                            <option value="">--</option>
                            @foreach($revenueAccounts as $acc)
                                <option value="{{ $acc->id }}" @selected(($line['revenue_account_id'] ?? '') == $acc->id)>{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="text" name="lines[{{ $i }}][description]" class="form-control form-control-sm" value="{{ $line['description'] ?? '' }}" required>
                    </td>
                    <td>
                        <select name="lines[{{ $i }}][uom_id]" class="form-select form-select-sm">
                            <option value="">--</option>
                            @foreach($uoms as $u)
                                <option value="{{ $u->id }}" @selected(($line['uom_id'] ?? '') == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.0001" name="lines[{{ $i }}][previous_qty]" class="form-control form-control-sm text-end js-line" value="{{ $line['previous_qty'] ?? 0 }}">
                    </td>
                    <td>
                        <input type="number" step="0.0001" name="lines[{{ $i }}][current_qty]" class="form-control form-control-sm text-end js-line" value="{{ $line['current_qty'] ?? 0 }}" required>
                    </td>
                    <td>
                        <input type="number" step="0.01" name="lines[{{ $i }}][rate]" class="form-control form-control-sm text-end js-line" value="{{ $line['rate'] ?? 0 }}" required>
                    </td>
                    <td class="text-end">
                        <span class="js-curr-amt">0.00</span>
                    </td>
                    <td>
                        <input type="text" name="lines[{{ $i }}][sac_hsn_code]" class="form-control form-control-sm" value="{{ $line['sac_hsn_code'] ?? '' }}">
                    </td>
                    <td>
                        <input type="text" name="lines[{{ $i }}][remarks]" class="form-control form-control-sm" value="{{ $line['remarks'] ?? '' }}">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-line" title="Remove">×</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card mb-3">
        <div class="card-header py-2">
            <div class="fw-semibold">Calculated Summary (Preview)</div>
            <div class="small text-muted">These values will be re-calculated and saved by the system after you save.</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Current Amount</label>
                    <input type="text" class="form-control form-control-sm" id="sum_current" value="0.00" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Retention</label>
                    <input type="text" class="form-control form-control-sm" id="sum_retention" value="0.00" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Net Amount</label>
                    <input type="text" class="form-control form-control-sm" id="sum_net" value="0.00" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">GST Total</label>
                    <input type="text" class="form-control form-control-sm" id="sum_gst" value="0.00" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">TDS Amount</label>
                    <input type="text" class="form-control form-control-sm" id="sum_tds" value="0.00" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Invoice Total</label>
                    <input type="text" class="form-control form-control-sm fw-semibold" id="sum_total" value="0.00" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Receivable</label>
                    <input type="text" class="form-control form-control-sm" id="sum_receivable" value="0.00" readonly>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('accounting.client-ra.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            {{ $editing ? 'Update RA Bill' : 'Save Draft' }}
        </button>
    </div>
</form>

<template id="line-template">
    <tr class="line-row">
        <td>
            <input type="text" name="lines[__INDEX__][boq_item_code]" class="form-control form-control-sm" value="" placeholder="(optional)">
        </td>
        <td>
            <select name="lines[__INDEX__][revenue_account_id]" class="form-select form-select-sm">
                <option value="">--</option>
                @foreach($revenueAccounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="text" name="lines[__INDEX__][description]" class="form-control form-control-sm" value="" required>
        </td>
        <td>
            <select name="lines[__INDEX__][uom_id]" class="form-select form-select-sm">
                <option value="">--</option>
                @foreach($uoms as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" step="0.0001" name="lines[__INDEX__][previous_qty]" class="form-control form-control-sm text-end js-line" value="0">
        </td>
        <td>
            <input type="number" step="0.0001" name="lines[__INDEX__][current_qty]" class="form-control form-control-sm text-end js-line" value="0" required>
        </td>
        <td>
            <input type="number" step="0.01" name="lines[__INDEX__][rate]" class="form-control form-control-sm text-end js-line" value="0" required>
        </td>
        <td class="text-end">
            <span class="js-curr-amt">0.00</span>
        </td>
        <td>
            <input type="text" name="lines[__INDEX__][sac_hsn_code]" class="form-control form-control-sm" value="">
        </td>
        <td>
            <input type="text" name="lines[__INDEX__][remarks]" class="form-control form-control-sm" value="">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-line" title="Remove">×</button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
(function () {
    const tableBody = document.querySelector('#lines-table tbody');
    const addBtn = document.getElementById('btn-add-line');
    const tpl = document.getElementById('line-template');

    const copyBtn = document.getElementById('btn-copy-prev-lines');
    const clientSelect = document.querySelector('select[name="client_id"]');
    const projectSelect = document.querySelector('select[name="project_id"]');

    const tdsSectionSelect = document.getElementById('tds_section');
    const tdsRateInput     = document.querySelector('input[name="tds_rate"]');

    function toNumber(v) {
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function format2(n) {
        return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    function maybeAutofillTdsRate() {
        if (!tdsSectionSelect || !tdsRateInput) return;
        const opt = tdsSectionSelect.options[tdsSectionSelect.selectedIndex];
        if (!opt) return;

        const rateFromMaster = toNumber(opt.getAttribute('data-rate'));
        const currentRate = toNumber(tdsRateInput.value);

        if (rateFromMaster > 0 && (!currentRate || currentRate <= 0)) {
            tdsRateInput.value = rateFromMaster.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
        }
    }

    function recalcRow(row) {
        const currQty = toNumber(row.querySelector('input[name$="[current_qty]"]')?.value);
        const rate    = toNumber(row.querySelector('input[name$="[rate]"]')?.value);

        const currAmt = currQty * rate;

        const currSpan = row.querySelector('.js-curr-amt');
        if (currSpan) currSpan.textContent = format2(currAmt);

        return { currAmt };
    }

    function recalcAll() {
        let currentAmount = 0;

        document.querySelectorAll('#lines-table tbody tr.line-row').forEach(function (row) {
            const r = recalcRow(row);
            currentAmount += r.currAmt;
        });

        const retentionPercent = toNumber(document.querySelector('input[name="retention_percent"]')?.value);
        const otherDeductions  = toNumber(document.querySelector('input[name="other_deductions"]')?.value);

        const retentionAmt = (retentionPercent > 0) ? (currentAmount * retentionPercent / 100) : 0;
        const netAmount = currentAmount - (retentionAmt + otherDeductions);

        const cgstRate = toNumber(document.querySelector('input[name="cgst_rate"]')?.value);
        const sgstRate = toNumber(document.querySelector('input[name="sgst_rate"]')?.value);
        const igstRate = toNumber(document.querySelector('input[name="igst_rate"]')?.value);

        const cgstAmt = netAmount * cgstRate / 100;
        const sgstAmt = netAmount * sgstRate / 100;
        const igstAmt = netAmount * igstRate / 100;
        const gstTotal = cgstAmt + sgstAmt + igstAmt;

        const tdsRate = toNumber(document.querySelector('input[name="tds_rate"]')?.value);
        const tdsAmt  = netAmount * tdsRate / 100;

        const invoiceTotal = netAmount + gstTotal;
        const receivable = invoiceTotal - tdsAmt;

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = format2(val);
        };

        setVal('sum_current', currentAmount);
        setVal('sum_retention', retentionAmt);
        setVal('sum_net', netAmount);
        setVal('sum_gst', gstTotal);
        setVal('sum_tds', tdsAmt);
        setVal('sum_total', invoiceTotal);
        setVal('sum_receivable', receivable);
    }

    // Copy previous RA lines (reload with query params)
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            const cid = clientSelect ? clientSelect.value : '';
            const pid = projectSelect ? projectSelect.value : '';

            if (!cid || !pid) {
                alert('Please select Client and Project first.');
                return;
            }

            const base = "{{ route('accounting.client-ra.create') }}";
            const url = new URL(base, window.location.origin);
            url.searchParams.set('client_id', cid);
            url.searchParams.set('project_id', pid);
            url.searchParams.set('copy_prev_lines', '1');

            window.location.href = url.toString();
        });
    }

    // Add line
    if (addBtn && tableBody && tpl) {
        addBtn.addEventListener('click', function () {
            const idx = tableBody.querySelectorAll('tr.line-row').length;
            const html = tpl.innerHTML.replaceAll('__INDEX__', idx);
            const temp = document.createElement('tbody');
            temp.innerHTML = html.trim();
            const newRow = temp.firstElementChild;
            tableBody.appendChild(newRow);
            recalcAll();
        });
    }

    // Remove line (delegate)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-remove-line');
        if (!btn) return;

        const row = btn.closest('tr.line-row');
        if (!row) return;

        row.remove();
        recalcAll();
    });

    document.addEventListener('input', function (e) {
        if (e.target.matches('.js-line') || e.target.matches('.js-calc') || e.target.matches('input[name="tds_rate"]')) {
            recalcAll();
        }
    });

    if (tdsSectionSelect) {
        tdsSectionSelect.addEventListener('change', function () {
            maybeAutofillTdsRate();
            recalcAll();
        });
    }

    // Initial
    maybeAutofillTdsRate();
    recalcAll();
})();
</script>
@endpush


