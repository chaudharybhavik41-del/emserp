@php
    /**
     * Client Billing Form
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
        'production_v2_dispatch_id' => null,
        'production_v2_dispatch_line_id' => null,
        'source_summary' => null,
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
                'production_v2_dispatch_id' => $l->production_v2_dispatch_id,
                'production_v2_dispatch_line_id' => $l->production_v2_dispatch_line_id,
                'source_summary' => $l->productionV2Dispatch?->dispatch_number,
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

    $invoiceTotalValue = old(
        'invoice_total',
        number_format((float) ($clientRa->total_amount ?? 0), 2, '.', '')
    );
    $roundOffValue = old(
        'round_off_preview',
        number_format((float) ($clientRa->round_off ?? 0), 2, '.', '')
    );
    $revenueAccountMeta = $revenueAccountMeta ?? ['role_by_account_id' => [], 'ids_by_role' => [], 'default_account_ids' => []];
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if($editing)
        @method('PUT')
    @endif


    @if(!$editing && request()->boolean('copy_prev_lines') && empty($prefillLines))
        <div class="alert alert-warning py-2 mb-3">
            No previous Approved/Posted client billing lines found to copy for the selected Client + Project.
        </div>
    @endif

    <div class="row g-3 mb-2">
        <div class="col-md-3">
            <label class="form-label">Billing Number</label>
            <input type="text" class="form-control form-control-sm" value="{{ $editing ? $clientRa->ra_number : ($nextRaNumber ?? '') }}" disabled>
            @if(!$editing && isset($previousRa) && $previousRa)
                <div class="form-text">Previous Billing: {{ $previousRa->ra_number }} (Seq: {{ $previousRa->ra_sequence }})</div>
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
            @php
                $revType = old('revenue_type', $clientRa->revenue_type ?? (!empty($dispatchImport) ? 'supply' : 'service'));
                $selectedBillKind = old('bill_kind', $clientRa->bill_kind ?? (!empty($dispatchImport) ? \App\Models\ClientRaBill::BILL_KIND_MATERIAL_SALES : \App\Models\ClientRaBill::BILL_KIND_PROJECT_LABOUR_SERVICE));
                $selectedSourceBasis = old('source_basis', $clientRa->source_basis ?? (!empty($dispatchImport) ? \App\Models\ClientRaBill::SOURCE_BASIS_PRODUCTION_DISPATCH : \App\Models\ClientRaBill::SOURCE_BASIS_MANUAL));
                $selectedMaterialScope = old('material_scope', $clientRa->material_scope ?? \App\Models\ClientRaBill::defaultMaterialScopeFor($selectedBillKind));
            @endphp
            <select name="revenue_type" class="form-select form-select-sm" required>
                @foreach(['fabrication'=>'Fabrication','erection'=>'Erection','supply'=>'Supply','service'=>'Service','other'=>'Other'] as $k => $v)
                    <option value="{{ $k }}" @selected($revType === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label">Bill Kind <span class="text-danger">*</span></label>
            <select name="bill_kind" id="bill_kind" class="form-select form-select-sm" required>
                @foreach(($billKindOptions ?? []) as $value => $label)
                    <option value="{{ $value }}" @selected($selectedBillKind === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Source Basis <span class="text-danger">*</span></label>
            <select name="source_basis" id="source_basis" class="form-select form-select-sm" required>
                @foreach(($sourceBasisOptions ?? []) as $value => $label)
                    <option value="{{ $value }}" @selected($selectedSourceBasis === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Material Scope <span class="text-danger">*</span></label>
            <select name="material_scope" id="material_scope" class="form-select form-select-sm" required>
                @foreach(($materialScopeOptions ?? []) as $value => $label)
                    <option value="{{ $value }}" @selected($selectedMaterialScope === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if(!$editing && !empty($dispatchImport))
        <div class="alert alert-info py-2 mb-3">
            Importing finalized V2 dispatch <strong>{{ $dispatchImport['dispatch']->dispatch_number }}</strong>.
            Ready client-billing lines: {{ $dispatchImport['remaining_count'] }}.
            Client-dispatchable assembly parts from dispatch are carried into the imported line description.
        </div>
    @endif

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
                    
                        <option value="{{ $proj->id }}" @selected(old('project_id', optional($selectedProject)->id) == $proj->id)>{{ $proj->code }} -{{ $proj->name }}</option>
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
        <h2 class="h6 mb-0" id="billing-lines-title">Billing Lines</h2>
        <div class="d-flex gap-2">
            @if(!$editing)
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-copy-prev-lines">
                    Copy Previous Billing Lines
                </button>
            @endif
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-line">+ Add Line</button>
        </div>
    </div>

    <div class="text-muted small mb-2" id="billing-lines-help">
        BOQ/Milestone Code is optional. If this billing is dispatch-based or not BOQ-driven, leave it blank and enter Description, Current Qty and Rate.
    </div>


    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered align-middle" id="lines-table">
            <thead class="table-light">
            <tr>
                <th style="width: 9%" id="billing-code-header">BOQ/Milestone Code <span class="text-muted">(optional)</span></th>
                <th style="width: 14%" id="billing-revenue-header">Revenue A/c</th>
                <th id="billing-description-header">Description <span class="text-danger">*</span></th>
                <th style="width: 8%">UOM</th>
                <th class="text-end" style="width: 9%">Prev Qty</th>
                <th class="text-end" style="width: 9%">Curr Qty <span class="text-danger">*</span></th>
                <th class="text-end" style="width: 9%">Rate <span class="text-danger">*</span></th>
                <th class="text-end" style="width: 10%">Curr Amt</th>
                <th style="width: 8%" id="billing-taxcode-header">HSN/SAC</th>
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
                        <input type="hidden" name="lines[{{ $i }}][production_v2_dispatch_id]" value="{{ $line['production_v2_dispatch_id'] ?? '' }}">
                        <input type="hidden" name="lines[{{ $i }}][production_v2_dispatch_line_id]" value="{{ $line['production_v2_dispatch_line_id'] ?? '' }}">
                        <input type="text" name="lines[{{ $i }}][boq_item_code]" class="form-control form-control-sm" value="{{ $line['boq_item_code'] ?? '' }}" placeholder="(optional)">
                    </td>
                    <td>
                        <select name="lines[{{ $i }}][revenue_account_id]" class="form-select form-select-sm js-revenue-account">
                            <option value="">--</option>
                            @foreach($revenueAccounts as $acc)
                                <option value="{{ $acc->id }}" data-role="{{ $revenueAccountMeta['role_by_account_id'][$acc->id] ?? 'generic' }}" @selected(($line['revenue_account_id'] ?? '') == $acc->id)>{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="text" name="lines[{{ $i }}][description]" class="form-control form-control-sm" value="{{ $line['description'] ?? '' }}" required>
                        @if(!empty($line['production_v2_dispatch_line_id']))
                            <div class="small text-primary mt-1">Source: {{ $line['source_summary'] ?? 'V2 Dispatch' }}</div>
                        @endif
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
                        <input type="number" step="0.0001" name="lines[{{ $i }}][previous_qty]" class="form-control form-control-sm text-end js-line" value="{{ $line['previous_qty'] ?? 0 }}" @if(!empty($line['production_v2_dispatch_line_id'])) readonly @endif>
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
                    <label class="form-label">Calculated Total</label>
                    <input type="text" class="form-control form-control-sm" id="sum_calculated_total" value="0.00" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Round Off</label>
                    <input type="text" class="form-control form-control-sm" id="sum_round_off" value="{{ $roundOffValue }}" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Invoice Total</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" name="invoice_total" id="invoice_total" class="form-control text-end {{ isset($errors) && $errors->has('invoice_total') ? 'is-invalid' : '' }}" value="{{ $invoiceTotalValue }}">
                        <button type="button" class="btn btn-outline-secondary" id="btn_round_invoice_total">Round</button>
                    </div>
                    @if(isset($errors) && $errors->has('invoice_total'))
                        <div class="invalid-feedback d-block">{{ $errors->first('invoice_total') }}</div>
                    @endif
                </div>
                <div class="col-md-2">
                    <label class="form-label">Receivable</label>
                    <input type="text" class="form-control form-control-sm fw-semibold" id="sum_receivable" value="0.00" readonly>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('accounting.client-ra.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            {{ $editing ? 'Update Client Billing' : 'Save Draft' }}
        </button>
    </div>
</form>

<template id="line-template">
    <tr class="line-row">
        <td>
            <input type="hidden" name="lines[__INDEX__][production_v2_dispatch_id]" value="">
            <input type="hidden" name="lines[__INDEX__][production_v2_dispatch_line_id]" value="">
            <input type="text" name="lines[__INDEX__][boq_item_code]" class="form-control form-control-sm" value="" placeholder="(optional)">
        </td>
        <td>
            <select name="lines[__INDEX__][revenue_account_id]" class="form-select form-select-sm js-revenue-account">
                <option value="">--</option>
                @foreach($revenueAccounts as $acc)
                    <option value="{{ $acc->id }}" data-role="{{ $revenueAccountMeta['role_by_account_id'][$acc->id] ?? 'generic' }}">{{ $acc->name }}</option>
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
    const invoiceTotalInput = document.getElementById('invoice_total');
    const roundInvoiceBtn = document.getElementById('btn_round_invoice_total');
    const billKindSelect = document.getElementById('bill_kind');
    const sourceBasisSelect = document.getElementById('source_basis');
    const materialScopeSelect = document.getElementById('material_scope');
    const dispatchImported = @json(!empty($dispatchImport));
    const revenueAccountMeta = @json($revenueAccountMeta);
    const lineTitle = document.getElementById('billing-lines-title');
    const lineHelp = document.getElementById('billing-lines-help');
    const codeHeader = document.getElementById('billing-code-header');
    const revenueHeader = document.getElementById('billing-revenue-header');
    const descriptionHeader = document.getElementById('billing-description-header');
    const taxCodeHeader = document.getElementById('billing-taxcode-header');

    const billKindDefaults = {
        fabrication: 'project_mfg_service',
        erection: 'project_mfg_service',
        supply: 'material_sales',
        service: 'project_labour_service',
        other: 'other',
    };

    const materialScopeDefaults = {
        project_mfg_service: 'own_material',
        project_labour_service: 'client_material',
        material_sales: 'own_material',
        scrap_sales: 'scrap',
        other: 'na',
    };

    const allowedRevenueRoles = {
        project_mfg_service: ['fabrication', 'erection', 'service', 'other', 'default', 'generic'],
        project_labour_service: ['service', 'other', 'default', 'generic'],
        material_sales: ['supply', 'other', 'default', 'generic'],
        scrap_sales: ['scrap', 'other', 'default', 'generic'],
        other: ['fabrication', 'erection', 'supply', 'service', 'scrap', 'other', 'default', 'generic'],
    };

    function toNumber(v) {
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function format2(n) {
        return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    function roundedInvoiceTotal(calculatedTotal) {
        return Math.round(toNumber(calculatedTotal));
    }

    function maybeAutofillTdsRate() {
        if (!tdsSectionSelect || !tdsRateInput) return;
        const opt = tdsSectionSelect.options[tdsSectionSelect.selectedIndex];
        if (!opt) return;

        if (!String(tdsSectionSelect.value || '').trim()) {
            tdsRateInput.value = '0';
            return;
        }

        const rateFromMaster = toNumber(opt.getAttribute('data-rate'));
        const currentRate = toNumber(tdsRateInput.value);

        if (rateFromMaster > 0 && (!currentRate || currentRate <= 0)) {
            tdsRateInput.value = rateFromMaster.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
        }
    }

    function syncBillingDefaultsFromRevenueType() {
        const revenueType = document.querySelector('select[name="revenue_type"]')?.value;
        if (!billKindSelect || !sourceBasisSelect || !materialScopeSelect || !revenueType) return;

        if (!billKindSelect.dataset.userChanged) {
            billKindSelect.value = dispatchImported ? 'material_sales' : (billKindDefaults[revenueType] || 'other');
        }

        if (!sourceBasisSelect.dataset.userChanged) {
            sourceBasisSelect.value = dispatchImported ? 'production_dispatch' : (billKindSelect.value === 'material_sales' ? 'stock_sale' : 'manual');
        }

        if (!materialScopeSelect.dataset.userChanged) {
            materialScopeSelect.value = materialScopeDefaults[billKindSelect.value] || 'na';
        }
    }

    function preferredRevenueAccountId() {
        if (!billKindSelect) return null;

        const kind = billKindSelect.value || 'other';
        const revenueType = document.querySelector('select[name="revenue_type"]')?.value || 'other';
        const roleIds = revenueAccountMeta.ids_by_role || {};
        const defaultIds = revenueAccountMeta.default_account_ids || {};

        if (kind === 'project_mfg_service') {
            if (revenueType === 'erection' && roleIds.erection?.length) return String(roleIds.erection[0]);
            if (revenueType === 'service' && roleIds.service?.length) return String(roleIds.service[0]);
        }

        return defaultIds[kind] ? String(defaultIds[kind]) : null;
    }

    function syncRevenueAccountOptions() {
        const kind = billKindSelect?.value || 'other';
        const allowed = new Set(allowedRevenueRoles[kind] || allowedRevenueRoles.other);

        tableBody.querySelectorAll('select.js-revenue-account').forEach(function (select) {
            const currentValue = String(select.value || '');
            let currentVisible = false;

            Array.from(select.options).forEach(function (option) {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const role = option.dataset.role || 'generic';
                const visible = allowed.has(role) || option.value === currentValue;
                option.hidden = !visible;

                if (option.value === currentValue && visible) {
                    currentVisible = true;
                }
            });

            if (!currentVisible) {
                select.value = '';
            }

            if (!select.value) {
                const preferredId = preferredRevenueAccountId();
                if (preferredId && Array.from(select.options).some(option => option.value === preferredId && !option.hidden)) {
                    select.value = preferredId;
                }
            }
        });
    }

    function syncLinePresentation() {
        const kind = billKindSelect?.value || 'other';
        const isMaterial = kind === 'material_sales' || kind === 'scrap_sales';

        if (lineTitle) {
            lineTitle.textContent = isMaterial ? 'Material Billing Lines' : 'Service Billing Lines';
        }

        if (lineHelp) {
            lineHelp.textContent = isMaterial
                ? 'For supply or scrap billing, use client-facing material lines. Enter item/material details, quantity and rate. Dispatch-linked rows remain traceable automatically.'
                : 'BOQ/Milestone Code is optional. If this billing is not BOQ-driven, leave it blank and enter service description, Current Qty and Rate.';
        }

        if (codeHeader) {
            codeHeader.innerHTML = isMaterial
                ? 'Item / Material Code <span class="text-muted">(optional)</span>'
                : 'BOQ/Milestone Code <span class="text-muted">(optional)</span>';
        }

        if (revenueHeader) {
            revenueHeader.textContent = isMaterial ? 'Material Revenue A/c' : 'Service Revenue A/c';
        }

        if (descriptionHeader) {
            descriptionHeader.innerHTML = (isMaterial ? 'Material Description' : 'Service Description') + ' <span class="text-danger">*</span>';
        }

        if (taxCodeHeader) {
            taxCodeHeader.textContent = isMaterial ? 'HSN' : 'SAC';
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

        const cgstAmt = Math.round((netAmount * cgstRate) * 100) / 10000;
        const sgstAmt = Math.round((netAmount * sgstRate) * 100) / 10000;
        const igstAmt = Math.round((netAmount * igstRate) * 100) / 10000;
        const gstTotal = cgstAmt + sgstAmt + igstAmt;

        const tdsRate = toNumber(document.querySelector('input[name="tds_rate"]')?.value);
        let tdsAmt = 0;
        if (tdsRate > 0 && netAmount > 0) {
            tdsAmt = Math.round((netAmount * tdsRate) / 100);
        }

        const calculatedTotal = (Math.round(((netAmount + gstTotal) + Number.EPSILON) * 100) / 100);

        let invoiceTotal = 0;
        if (invoiceTotalInput && invoiceTotalInput.dataset.userChanged === '1') {
            invoiceTotal = Math.round((toNumber(invoiceTotalInput.value) + Number.EPSILON) * 100) / 100;
        } else {
            invoiceTotal = roundedInvoiceTotal(calculatedTotal);
            if (invoiceTotalInput) {
                invoiceTotalInput.value = format2(invoiceTotal);
            }
        }

        const roundOff = Math.round(((invoiceTotal - calculatedTotal) + Number.EPSILON) * 100) / 100;
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
        setVal('sum_calculated_total', calculatedTotal);
        setVal('sum_round_off', roundOff);
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
            syncRevenueAccountOptions();
            syncLinePresentation();
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

    if (billKindSelect) {
        billKindSelect.addEventListener('change', function () {
            billKindSelect.dataset.userChanged = '1';
            if (materialScopeSelect && !materialScopeSelect.dataset.userChanged) {
                materialScopeSelect.value = materialScopeDefaults[billKindSelect.value] || 'na';
            }
            if (sourceBasisSelect && !sourceBasisSelect.dataset.userChanged) {
                sourceBasisSelect.value = dispatchImported ? 'production_dispatch' : (billKindSelect.value === 'material_sales' ? 'stock_sale' : 'manual');
            }
            syncLinePresentation();
            syncRevenueAccountOptions();
        });
    }

    if (sourceBasisSelect) {
        sourceBasisSelect.addEventListener('change', function () {
            sourceBasisSelect.dataset.userChanged = '1';
        });
    }

    if (materialScopeSelect) {
        materialScopeSelect.addEventListener('change', function () {
            materialScopeSelect.dataset.userChanged = '1';
        });
    }

    const revenueTypeSelect = document.querySelector('select[name="revenue_type"]');
    if (revenueTypeSelect) {
        revenueTypeSelect.addEventListener('change', function () {
            syncBillingDefaultsFromRevenueType();
            syncLinePresentation();
            syncRevenueAccountOptions();
        });
    }

    if (tdsSectionSelect) {
        tdsSectionSelect.addEventListener('change', function () {
            maybeAutofillTdsRate();
            recalcAll();
        });
    }

    if (invoiceTotalInput) {
        invoiceTotalInput.addEventListener('input', function() {
            invoiceTotalInput.dataset.userChanged = '1';
            recalcAll();
        });
        invoiceTotalInput.addEventListener('blur', function () {
            if (String(invoiceTotalInput.value || '').trim() !== '') {
                invoiceTotalInput.value = format2(toNumber(invoiceTotalInput.value));
            }
            recalcAll();
        });
    }

    if (roundInvoiceBtn) {
        roundInvoiceBtn.addEventListener('click', function () {
            if (invoiceTotalInput) {
                invoiceTotalInput.dataset.userChanged = '0';
            }
            recalcAll();
        });
    }

    // Initial
    syncBillingDefaultsFromRevenueType();
    syncLinePresentation();
    syncRevenueAccountOptions();
    maybeAutofillTdsRate();
    recalcAll();
})();
</script>
@endpush
