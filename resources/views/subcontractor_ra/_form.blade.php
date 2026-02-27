@php
    /**
     * Subcontractor RA Bill Form
     *
     * Expected variables (from controller):
     * - $subcontractors, $projects, $uoms
     * - $selectedSubcontractor (nullable), $selectedProject (nullable)
     * - $previousRa (nullable)
     * - $prefillLines (array|null) // when copying previous RA lines
     * - $nextRaNumber (string)
     * - $tdsSections (collection)
     *
     * When editing, pass $subcontractorRa (SubcontractorRaBill model).
     */

    $editing = isset($subcontractorRa) && $subcontractorRa && $subcontractorRa->exists;

    $action = $editing
        ? route('accounting.subcontractor-ra.update', $subcontractorRa)
        : route('accounting.subcontractor-ra.store');

    // Build lines dataset for UI
    $defaultLine = [
        'id' => null,
        'boq_item_code' => '',
        'description' => '',
        'uom_id' => null,
        'contracted_qty' => 0,
        'previous_qty' => 0,
        'current_qty' => 0,
        'rate' => 0,
        'remarks' => '',
    ];

    if ($editing) {
        $modelLines = $subcontractorRa->lines->map(function ($l) {
            return [
                'id' => $l->id,
                'boq_item_code' => $l->boq_item_code,
                'description' => $l->description,
                'uom_id' => $l->uom_id,
                'contracted_qty' => $l->contracted_qty,
                'previous_qty' => $l->previous_qty,
                'current_qty' => $l->current_qty,
                'rate' => $l->rate,
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
            No previous Approved/Posted RA Bill lines found to copy for the selected Subcontractor + Project.
        </div>
    @endif

    <div class="row g-3 mb-2">
        <div class="col-md-3">
            <label class="form-label">RA Number</label>
            <input type="text" class="form-control form-control-sm" value="{{ $editing ? $subcontractorRa->ra_number : ($nextRaNumber ?? '') }}" disabled>
            @if(!$editing && isset($previousRa) && $previousRa)
                <div class="form-text">Previous RA: {{ $previousRa->ra_number }} (Seq: {{ $previousRa->ra_sequence }})</div>
            @endif
        </div>

        <div class="col-md-3">
            <label class="form-label">Bill Number (Subcontractor)</label>
            <input type="text" name="bill_number" class="form-control form-control-sm" value="{{ old('bill_number', $subcontractorRa->bill_number ?? '') }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">Bill Date <span class="text-danger">*</span></label>
            <input type="date" name="bill_date" class="form-control form-control-sm" value="{{ old('bill_date', optional($subcontractorRa->bill_date ?? null)->format('Y-m-d')) }}" required>
        </div>

        <div class="col-md-3">
            <label class="form-label">Due Date</label>
            <input type="date" name="due_date" class="form-control form-control-sm" value="{{ old('due_date', optional($subcontractorRa->due_date ?? null)->format('Y-m-d')) }}">
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label">Subcontractor <span class="text-danger">*</span></label>

            @if($editing)
                <input type="text" class="form-control form-control-sm" value="{{ $subcontractorRa->subcontractor?->name }}" disabled>
                <input type="hidden" id="js_subcontractor_id" value="{{ $subcontractorRa->subcontractor_id }}">
            @else
                <select name="subcontractor_id" class="form-select form-select-sm" required>
                    <option value="">-- Select --</option>
                    @foreach($subcontractors as $p)
                        <option value="{{ $p->id }}" data-gstin="{{ $p->gstin }}" @selected(old('subcontractor_id', optional($selectedSubcontractor)->id) == $p->id)>
                            {{ $p->name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text" id="subcontractor_gstin_text"></div>
            @endif
        </div>

        <div class="col-md-4">
            <label class="form-label">Project <span class="text-danger">*</span></label>

            @if($editing)
                <input type="text" class="form-control form-control-sm" value="{{ $subcontractorRa->project?->name }}" disabled>
                <input type="hidden" id="js_project_id" value="{{ $subcontractorRa->project_id }}">
            @else
                <select name="project_id" class="form-select form-select-sm" required>
                    <option value="">-- Select --</option>
                    @foreach($projects as $proj)
                        <option value="{{ $proj->id }}" @selected(old('project_id', optional($selectedProject)->id) == $proj->id)>
                            {{ $proj->name }}
                        </option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="col-md-4">
            <label class="form-label">Work Order Number</label>
            <input type="text" name="work_order_number" class="form-control form-control-sm" value="{{ old('work_order_number', $subcontractorRa->work_order_number ?? '') }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">Period From</label>
            <input type="date" name="period_from" class="form-control form-control-sm" value="{{ old('period_from', optional($subcontractorRa->period_from ?? null)->format('Y-m-d')) }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">Period To</label>
            <input type="date" name="period_to" class="form-control form-control-sm" value="{{ old('period_to', optional($subcontractorRa->period_to ?? null)->format('Y-m-d')) }}">
        </div>

        <div class="col-md-6">
            <label class="form-label">Remarks</label>
            <input type="text" name="remarks" class="form-control form-control-sm" value="{{ old('remarks', $subcontractorRa->remarks ?? '') }}">
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-2">
            <label class="form-label">Retention %</label>
            <input type="number" step="0.0001" name="retention_percent" class="form-control form-control-sm js-calc" value="{{ old('retention_percent', $subcontractorRa->retention_percent ?? 0) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">Advance Recovery</label>
            <input type="number" step="0.01" name="advance_recovery" class="form-control form-control-sm js-calc" value="{{ old('advance_recovery', $subcontractorRa->advance_recovery ?? 0) }}">
            <div class="form-text small" id="subcontractor_advance_balance_text"></div>
        </div>
        <div class="col-md-2">
            <label class="form-label">Other Deductions</label>
            <input type="number" step="0.01" name="other_deductions" class="form-control form-control-sm js-calc" value="{{ old('other_deductions', $subcontractorRa->other_deductions ?? 0) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Deduction Remarks</label>
            <input type="text" name="deduction_remarks" class="form-control form-control-sm" value="{{ old('deduction_remarks', $subcontractorRa->deduction_remarks ?? '') }}">
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-2">
            <label class="form-label">CGST %</label>
            <input type="number" step="0.0001" name="cgst_rate" class="form-control form-control-sm js-calc" value="{{ old('cgst_rate', $subcontractorRa->cgst_rate ?? 0) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">SGST %</label>
            <input type="number" step="0.0001" name="sgst_rate" class="form-control form-control-sm js-calc" value="{{ old('sgst_rate', $subcontractorRa->sgst_rate ?? 0) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">IGST %</label>
            <input type="number" step="0.0001" name="igst_rate" class="form-control form-control-sm js-calc" value="{{ old('igst_rate', $subcontractorRa->igst_rate ?? 0) }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">TDS %</label>
            <input type="number" step="0.0001" name="tds_rate" class="form-control form-control-sm js-calc" value="{{ old('tds_rate', $subcontractorRa->tds_rate ?? 0) }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">TDS Section</label>

            @if(isset($tdsSections) && $tdsSections->count())
                <select name="tds_section" id="tds_section" class="form-select form-select-sm">
                    <option value="">-- None --</option>
                    @foreach($tdsSections as $sec)
                        <option value="{{ $sec->code }}"
                                data-rate="{{ $sec->default_rate }}"
                                {{ old('tds_section', $subcontractorRa->tds_section ?? '') == $sec->code ? 'selected' : '' }}>
                            {{ $sec->code }} - {{ $sec->name }} ({{ rtrim(rtrim(number_format((float) $sec->default_rate, 4), '0'), '.') }}%)
                        </option>
                    @endforeach
                </select>
                <div class="form-text">
                    Manage: <a href="{{ route('accounting.tds-sections.index') }}" target="_blank">TDS Sections</a>
                </div>
            @else
                <input type="text" name="tds_section" class="form-control form-control-sm" placeholder="e.g. 194C" value="{{ old('tds_section', $subcontractorRa->tds_section ?? '') }}">
            @endif
        </div>
    </div>

    <hr>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">BOQ / Work Lines</h2>
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
        BOQ/Work Code is optional. If this project does not have a BOQ, leave it blank and just enter Description, Current Qty and Rate.
    </div>


    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered align-middle" id="lines-table">
            <thead class="table-light">
            <tr>
                <th style="width: 9%">BOQ/Work Code <span class="text-muted">(optional)</span></th>
                <th>Description <span class="text-danger">*</span></th>
                <th style="width: 10%">UOM</th>
                <th style="width: 9%" class="text-end">Contract</th>
                <th style="width: 9%" class="text-end">Prev Qty</th>
                <th style="width: 9%" class="text-end">Curr Qty <span class="text-danger">*</span></th>
                <th style="width: 9%" class="text-end">Rate <span class="text-danger">*</span></th>
                <th style="width: 9%" class="text-end">Prev Amt</th>
                <th style="width: 9%" class="text-end">Curr Amt</th>
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
                        <input type="number" step="0.0001" name="lines[{{ $i }}][contracted_qty]" class="form-control form-control-sm text-end js-line" value="{{ $line['contracted_qty'] ?? 0 }}">
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
                        <span class="js-prev-amt">0.00</span>
                    </td>
                    <td class="text-end">
                        <span class="js-curr-amt">0.00</span>
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
                    <label class="form-label">Payable</label>
                    <input type="text" class="form-control form-control-sm fw-semibold" id="sum_total" value="0.00" readonly>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('accounting.subcontractor-ra.index') }}" class="btn btn-outline-secondary">Cancel</a>
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
            <input type="number" step="0.0001" name="lines[__INDEX__][contracted_qty]" class="form-control form-control-sm text-end js-line" value="0">
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
            <span class="js-prev-amt">0.00</span>
        </td>
        <td class="text-end">
            <span class="js-curr-amt">0.00</span>
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
    const subcontractorSelect = document.querySelector('select[name="subcontractor_id"]');
    const projectSelect = document.querySelector('select[name="project_id"]');

    const billDateInput = document.querySelector('input[name="bill_date"]');
    const advanceTextEl = document.getElementById('subcontractor_advance_balance_text');
    const partySummaryUrl = "{{ route('accounting.subcontractor-ra.party-summary') }}";

    const gstinTextEl = document.getElementById('subcontractor_gstin_text');
    const cgstInput = document.querySelector('input[name="cgst_rate"]');
    const sgstInput = document.querySelector('input[name="sgst_rate"]');
    const igstInput = document.querySelector('input[name="igst_rate"]');

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

    function getSelectedSubcontractorGstin() {
        if (!subcontractorSelect) return '';
        const opt = subcontractorSelect.options[subcontractorSelect.selectedIndex];
        return (opt ? (opt.getAttribute('data-gstin') || '') : '').trim();
    }

    function applyGstDefaultsFromSubcontractor() {
        if (!subcontractorSelect || !cgstInput || !sgstInput || !igstInput) return;

        const gstin = getSelectedSubcontractorGstin();
        const hasGst = gstin.length >= 5;

        if (gstinTextEl) {
            gstinTextEl.innerHTML = gstin
                ? `GSTIN: <span class="fw-semibold">${gstin}</span>`
                : `GSTIN: <span class="text-danger">Not available</span>`;
        }

        const cg = toNumber(cgstInput.value);
        const sg = toNumber(sgstInput.value);
        const ig = toNumber(igstInput.value);

        if (!hasGst) {
            // If subcontractor is not GST-registered, don't apply GST in bill.
            cgstInput.value = 0;
            sgstInput.value = 0;
            igstInput.value = 0;
            return;
        }

        // If subcontractor is GST-registered and user has not entered GST yet, default to 18% (9 + 9)
        if (cg <= 0 && sg <= 0 && ig <= 0) {
            cgstInput.value = 9;
            sgstInput.value = 9;
            igstInput.value = 0;
        }
    }



    function formatMoneyInr(n) {
        try {
            return new Intl.NumberFormat('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(toNumber(n));
        } catch (e) {
            return format2(toNumber(n));
        }
    }

    function getSelectedPartyId() {
        if (subcontractorSelect && subcontractorSelect.value) {
            return subcontractorSelect.value;
        }
        const hidden = document.getElementById('js_subcontractor_id');
        return hidden ? (hidden.value || '') : '';
    }

    function getSelectedProjectId() {
        if (projectSelect && projectSelect.value) {
            return projectSelect.value;
        }
        const hidden = document.getElementById('js_project_id');
        return hidden ? (hidden.value || '') : '';
    }

    function getBillDateValue() {
        return billDateInput ? (billDateInput.value || '') : '';
    }

    async function refreshAdvanceBalance() {
        if (!advanceTextEl) return;

        const partyId = getSelectedPartyId();
        if (!partyId) {
            advanceTextEl.innerHTML = '<span class="text-muted">Select subcontractor to view advance balance.</span>';
            return;
        }

        const params = new URLSearchParams();
        params.set('party_id', partyId);

        const projId = getSelectedProjectId();
        if (projId) params.set('project_id', projId);

        const asOf = getBillDateValue();
        if (asOf) params.set('as_of', asOf);

        advanceTextEl.innerHTML = '<span class="text-muted">Fetching advance balance...</span>';

        try {
            const res = await fetch(`${partySummaryUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });

            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }

            const data = await res.json();
            if (!data || data.success === false) {
                advanceTextEl.innerHTML = '<span class="text-muted">Unable to fetch advance balance.</span>';
                return;
            }

            const overallAdv = toNumber(data?.overall?.advance);
            const overallPay = toNumber(data?.overall?.payable);

            let htmlParts = [];

            if (data.project) {
                const projAdv = toNumber(data?.project?.advance);
                const projPay = toNumber(data?.project?.payable);

                htmlParts.push(
                    `Proj: Adv <span class="fw-semibold">₹${formatMoneyInr(projAdv)}</span>` +
                    (projPay > 0 ? ` · Payable <span class="fw-semibold">₹${formatMoneyInr(projPay)}</span>` : '')
                );
            }

            htmlParts.push(
                `Overall: Adv <span class="fw-semibold">₹${formatMoneyInr(overallAdv)}</span>` +
                (overallPay > 0 ? ` · Payable <span class="fw-semibold">₹${formatMoneyInr(overallPay)}</span>` : '')
            );

            advanceTextEl.innerHTML = htmlParts.join('<br>') + '<br><span class="text-muted">As on bill date</span>';
        } catch (e) {
            advanceTextEl.innerHTML = '<span class="text-muted">Unable to fetch advance balance.</span>';
        }
    }
    function recalcRow(row) {
        const prevQty = toNumber(row.querySelector('input[name$="[previous_qty]"]')?.value);
        const currQty = toNumber(row.querySelector('input[name$="[current_qty]"]')?.value);
        const rate    = toNumber(row.querySelector('input[name$="[rate]"]')?.value);

        const prevAmt = prevQty * rate;
        const currAmt = currQty * rate;

        const prevSpan = row.querySelector('.js-prev-amt');
        const currSpan = row.querySelector('.js-curr-amt');

        if (prevSpan) prevSpan.textContent = format2(prevAmt);
        if (currSpan) currSpan.textContent = format2(currAmt);

        return { prevAmt, currAmt };
    }

    function recalcAll() {
        let currentAmount = 0;

        document.querySelectorAll('#lines-table tbody tr.line-row').forEach(function (row) {
            const r = recalcRow(row);
            currentAmount += r.currAmt;
        });

        const retentionPercent = toNumber(document.querySelector('input[name="retention_percent"]')?.value);
        const advanceRecovery  = toNumber(document.querySelector('input[name="advance_recovery"]')?.value);
        const otherDeductions  = toNumber(document.querySelector('input[name="other_deductions"]')?.value);

        const retentionAmt = (retentionPercent > 0) ? (currentAmount * retentionPercent / 100) : 0;
        const netAmount = currentAmount - (retentionAmt + advanceRecovery + otherDeductions);

        const cgstRate = toNumber(document.querySelector('input[name="cgst_rate"]')?.value);
        const sgstRate = toNumber(document.querySelector('input[name="sgst_rate"]')?.value);
        const igstRate = toNumber(document.querySelector('input[name="igst_rate"]')?.value);

        const cgstAmt = netAmount * cgstRate / 100;
        const sgstAmt = netAmount * sgstRate / 100;
        const igstAmt = netAmount * igstRate / 100;
        const gstTotal = cgstAmt + sgstAmt + igstAmt;

        const tdsRate = toNumber(document.querySelector('input[name="tds_rate"]')?.value);
        const tdsAmt  = netAmount * tdsRate / 100;

        const payable = netAmount + gstTotal - tdsAmt;

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = format2(val);
        };

        setVal('sum_current', currentAmount);
        setVal('sum_retention', retentionAmt);
        setVal('sum_net', netAmount);
        setVal('sum_gst', gstTotal);
        setVal('sum_tds', tdsAmt);
        setVal('sum_total', payable);
    }

    // Copy previous RA lines (reload with query params)
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            const sid = subcontractorSelect ? subcontractorSelect.value : '';
            const pid = projectSelect ? projectSelect.value : '';

            if (!sid || !pid) {
                alert('Please select Subcontractor and Project first.');
                return;
            }

            const base = "{{ route('accounting.subcontractor-ra.create') }}";
            const url = new URL(base, window.location.origin);
            url.searchParams.set('subcontractor_id', sid);
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

    // Recalc on input changes
    document.addEventListener('input', function (e) {
        if (e.target.matches('.js-line') || e.target.matches('.js-calc') || e.target.matches('input[name="tds_rate"]')) {
            recalcAll();
        }
    });

    if (subcontractorSelect) {
        subcontractorSelect.addEventListener('change', function () {
            applyGstDefaultsFromSubcontractor();
            refreshAdvanceBalance();
            recalcAll();
        });
    }

    if (projectSelect) {
        projectSelect.addEventListener('change', function () {
            refreshAdvanceBalance();
        });
    }

    if (billDateInput) {
        billDateInput.addEventListener('change', function () {
            refreshAdvanceBalance();
        });
    }

    if (tdsSectionSelect) {
        tdsSectionSelect.addEventListener('change', function () {
            maybeAutofillTdsRate();
            recalcAll();
        });
    }

    // Initial
    applyGstDefaultsFromSubcontractor();
    maybeAutofillTdsRate();
    recalcAll();
    refreshAdvanceBalance();
})();
</script>
@endpush


