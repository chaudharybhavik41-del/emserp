@php
    $currencySymbol = config('crm.currency_symbol', '₹');
    /** @var \App\Models\CrmQuotation|null $quotation */
    /** @var \App\Models\CrmLead|null $lead */

    // Create view does not pass $quotation; ensure we always have an object
    $quotation = $quotation ?? new \App\Models\CrmQuotation();

    $isEdit = $quotation->exists;

    // Build initial items array: prefer old() (after validation error), otherwise existing quotation items, otherwise empty
    $oldItems = old('items');

    if (is_array($oldItems)) {
        $formItems = $oldItems;
    } elseif ($isEdit) {
        // NOTE: controller loads items.costBreakups for edit; still safe if it lazy-loads.
        $formItems = $quotation->items->map(function ($item) {
            $breakups = $item->costBreakups ?? collect();

            return [
                'item_id'          => $item->item_id,
                'description'      => $item->description,
                'quantity'         => $item->quantity,
                'uom_id'           => $item->uom_id,
                'unit_price'       => $item->unit_price,
                'line_total'       => $item->line_total,
                'sort_order'       => $item->sort_order,
                'cost_breakup_json'=> $breakups->count()
                    ? $breakups->map(function ($cb) {
                        return [
                            'code'  => $cb->component_code,
                            'name'  => $cb->component_name,
                            'basis' => $cb->basis,
                            'rate'  => (float) $cb->rate,
                        ];
                    })->values()->toJson()
                    : '',
            ];
        })->toArray();
    } else {
        $formItems = [];
    }

    $cancelUrl = $isEdit
        ? route('crm.quotations.show', $quotation)
        : (isset($lead) ? route('crm.leads.show', $lead) : route('crm.leads.index'));

    $quoteMode  = old('quote_mode', $quotation->quote_mode ?? 'item');
    $isRateOnly = (bool) old('is_rate_only', $quotation->is_rate_only ?? false);

    // Standard Terms templates (module: sales, sub_module: quotation)
    $standardTerms = $standardTerms ?? collect();
    $defaultStandardTerm = $defaultStandardTerm ?? null;

    // Breakup Templates (CRM → Quotation Breakup Templates)
    $breakupTemplates = $breakupTemplates ?? collect();
    $defaultBreakupTemplate = $defaultBreakupTemplate ?? null;

    $selectedStdTermId = old('standard_term_id', $quotation->standard_term_id ?? ($defaultStandardTerm?->id ?? ''));

    $termsTextValue = old('terms_text', $quotation->terms_text ?? (
        $selectedStdTermId
            ? (optional($standardTerms->firstWhere('id', (int) $selectedStdTermId))->content ?? '')
            : ($defaultStandardTerm?->content ?? '')
    ));

    $defaultCostHeads = config('crm.quotation_cost_heads', [
        ['code' => 'FAB_LAB',   'name' => 'Fabrication labour'],
        ['code' => 'CONS',      'name' => 'Consumables'],
        ['code' => 'PAINT_LAB', 'name' => 'Painting labour'],
        ['code' => 'PAINT_MAT', 'name' => 'Paint material'],
        ['code' => 'TRANSPORT', 'name' => 'Transport'],
        ['code' => 'OTHER',     'name' => 'Other'],
    ]);
@endphp

<form method="POST"
      action="{{ $isEdit ? route('crm.quotations.update', $quotation) : route('crm.leads.quotations.store', $lead) }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label">Quotation Code</label>
            <input type="text"
                   name="code"
                   class="form-control @error('code') is-invalid @enderror"
                   value="{{ old('code', $quotation->code ?? '') }}"
                   placeholder="Leave blank for auto"
                   maxlength="50">
            @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
                Leave blank to auto-generate (QTN-YYYY-####).
            </div>
        </div>

        <div class="col-md-5">
            <label class="form-label">Project Name <span class="text-danger">*</span></label>
            <input type="text"
                   name="project_name"
                   class="form-control @error('project_name') is-invalid @enderror"
                   value="{{ old('project_name', $quotation->project_name ?? ($lead->title ?? '')) }}"
                   required>
            @error('project_name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Client <span class="text-danger">*</span></label>
            <select name="party_id"
                    class="form-select @error('party_id') is-invalid @enderror"
                    required>
                <option value="">-- Select Client --</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}"
                        {{ (int) old('party_id', $quotation->party_id ?? $lead->party_id ?? 0) === $client->id ? 'selected' : '' }}>
                        {{ $client->code }} - {{ $client->name }}
                    </option>
                @endforeach
            </select>
            @error('party_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label">Valid Till</label>
            <input type="date"
                   name="valid_till"
                   class="form-control @error('valid_till') is-invalid @enderror"
                   value="{{ old('valid_till', isset($quotation->valid_till) ? $quotation->valid_till->format('Y-m-d') : '') }}">
            @error('valid_till')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label class="form-label">Quotation Type <span class="text-danger">*</span></label>
            <select name="quote_mode"
                    id="quote_mode"
                    class="form-select @error('quote_mode') is-invalid @enderror"
                    required>
                <option value="item" {{ $quoteMode === 'item' ? 'selected' : '' }}>
                    Item-wise (Tender / BOQ)
                </option>
                <option value="rate_per_kg" {{ $quoteMode === 'rate_per_kg' ? 'selected' : '' }}>
                    Rate per KG (Scope based)
                </option>
            </select>
            @error('quote_mode')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label class="form-label">Profit %</label>
            <input type="number"
                   name="profit_percent"
                   id="profit_percent"
                   class="form-control @error('profit_percent') is-invalid @enderror"
                   step="0.01"
                   min="0"
                   max="100"
                   value="{{ old('profit_percent', $quotation->profit_percent ?? 0) }}">
            @error('profit_percent')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
                Applied on direct cost (from cost breakup) to compute the selling rate.
            </div>
        </div>

        <div class="col-md-3" id="rate-only-wrapper" style="{{ $quoteMode === 'rate_per_kg' ? '' : 'display:none;' }}">
            <label class="form-label d-block">&nbsp;</label>
            <div class="form-check mt-1">
                <input class="form-check-input"
                       type="checkbox"
                       name="is_rate_only"
                       id="is_rate_only"
                       value="1"
                       {{ $isRateOnly ? 'checked' : '' }}>
                <label class="form-check-label" for="is_rate_only">
                    Rate-only (no totals)
                </label>
            </div>
            <div class="form-text">
                Use for per-kg offers where quantity is not final. PDF will hide totals.
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label class="form-label">Client PO Number</label>
            <input type="text"
                   name="client_po_number"
                   class="form-control @error('client_po_number') is-invalid @enderror"
                   maxlength="100"
                   value="{{ old('client_po_number', $quotation->client_po_number ?? '') }}">
            @error('client_po_number')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Payment Terms (Days)</label>
            <select name="payment_terms_days"
                    class="form-select @error('payment_terms_days') is-invalid @enderror">
                <option value="">--</option>
                @foreach(($paymentTermsDaysOptions ?? [7,10,15,30,45,60,90]) as $d)
                    <option value="{{ $d }}"
                        {{ (string) old('payment_terms_days', $quotation->payment_terms_days ?? '') === (string) $d ? 'selected' : '' }}>
                        {{ $d }} days
                    </option>
                @endforeach
            </select>
            @error('payment_terms_days')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Freight Terms</label>
            <input type="text"
                   name="freight_terms"
                   class="form-control @error('freight_terms') is-invalid @enderror"
                   maxlength="255"
                   value="{{ old('freight_terms', $quotation->freight_terms ?? '') }}">
            @error('freight_terms')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Scope of Work</label>
            <textarea name="scope_of_work"
                      rows="3"
                      class="form-control @error('scope_of_work') is-invalid @enderror"
                      placeholder="(Recommended for Rate per KG quotations)">{{ old('scope_of_work', $quotation->scope_of_work ?? '') }}</textarea>
            @error('scope_of_work')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
                Mention what is included in the rate (cutting, fabrication, welding, painting, loading, etc.).
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label">Exclusions</label>
            <textarea name="exclusions"
                      rows="3"
                      class="form-control @error('exclusions') is-invalid @enderror"
                      placeholder="(Optional)">{{ old('exclusions', $quotation->exclusions ?? '') }}</textarea>
            @error('exclusions')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Payment Terms</label>
            <textarea name="payment_terms"
                      rows="2"
                      class="form-control @error('payment_terms') is-invalid @enderror">{{ old('payment_terms', $quotation->payment_terms ?? '') }}</textarea>
            @error('payment_terms')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">Delivery Terms</label>
            <textarea name="delivery_terms"
                      rows="2"
                      class="form-control @error('delivery_terms') is-invalid @enderror">{{ old('delivery_terms', $quotation->delivery_terms ?? '') }}</textarea>
            @error('delivery_terms')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Other Terms</label>
            <textarea name="other_terms"
                      rows="2"
                      class="form-control @error('other_terms') is-invalid @enderror">{{ old('other_terms', $quotation->other_terms ?? '') }}</textarea>
            @error('other_terms')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">Project / Site Special Notes</label>
            <textarea name="project_special_notes"
                      rows="2"
                      class="form-control @error('project_special_notes') is-invalid @enderror">{{ old('project_special_notes', $quotation->project_special_notes ?? '') }}</textarea>
            @error('project_special_notes')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>


    {{-- TERMS & CONDITIONS (Printed in PDF) --}}
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Terms &amp; Conditions (Printed in PDF)</span>

            @if(\Illuminate\Support\Facades\Route::has('standard-terms.index'))
                <a href="{{ route('standard-terms.index', ['module' => 'sales', 'sub_module' => 'quotation']) }}"
                   class="btn btn-sm btn-outline-secondary"
                   target="_blank">
                    Manage Templates
                </a>
            @endif
        </div>

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">T&amp;C Template</label>
                    <select name="standard_term_id"
                            id="standard_term_id"
                            class="form-select @error('standard_term_id') is-invalid @enderror">
                        <option value="">-- Select --</option>
                        @foreach($standardTerms as $term)
                            <option value="{{ $term->id }}"
                                {{ (string) $selectedStdTermId === (string) $term->id ? 'selected' : '' }}>
                                {{ $term->name }}
                                @if($term->is_default)
                                    (Default)
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('standard_term_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror

                    <div class="form-text">
                        Select a template to auto-fill the Terms text (you will be asked before replacing existing text).
                    </div>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Terms &amp; Conditions Text</label>
                    <textarea name="terms_text"
                              id="terms_text"
                              rows="10"
                              class="form-control @error('terms_text') is-invalid @enderror"
                              placeholder="Type terms here (numbered list, bullet points, etc.).">{{ $termsTextValue }}</textarea>
                    @error('terms_text')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror

                    <div class="form-text">
                        New lines and spacing are preserved in the quotation PDF.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- LINE ITEMS --}}
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Line Items</span>

            <button type="button"
                    id="add-item-btn"
                    class="btn btn-sm btn-outline-primary">
                + Add Item
            </button>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 18%">Item</th>
                        <th>Description</th>
                        <th style="width: 9%">Qty</th>
                        <th style="width: 9%">UOM</th>
                        <th style="width: 12%">Unit Price ({{ $currencySymbol }})</th>
                        <th style="width: 12%" class="js-col-line-total">Line Total ({{ $currencySymbol }})</th>
                        <th style="width: 7%" class="text-center">Breakup</th>
                        <th style="width: 6%">Sort</th>
                        <th style="width: 6%"></th>
                    </tr>
                    </thead>
                    <tbody id="items-tbody">
                    @foreach($formItems as $idx => $line)
                        @php
                            $hasBreakup = !empty($line['cost_breakup_json']);
                        @endphp
                        <tr data-row-index="{{ $idx }}">
                            <td>
                                <select name="items[{{ $idx }}][item_id]"
                                        class="form-select form-select-sm">
                                    <option value="">-- Select --</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}"
                                            {{ (int) ($line['item_id'] ?? 0) === $item->id ? 'selected' : '' }}>
                                            {{ $item->code }} - {{ $item->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <textarea name="items[{{ $idx }}][description]"
                                          class="form-control form-control-sm"
                                          rows="3"
                                          style="min-width: 260px; resize: vertical;"
                                          required>{{ $line['description'] ?? '' }}</textarea>
                            </td>
                            <td>
                                <input type="number"
                                       name="items[{{ $idx }}][quantity]"
                                       class="form-control form-control-sm js-qty"
                                       step="0.001"
                                       min="0"
                                       value="{{ $line['quantity'] ?? '' }}">
                            </td>
                            <td>
                                <select name="items[{{ $idx }}][uom_id]"
                                        class="form-select form-select-sm">
                                    <option value="">--</option>
                                    @foreach($uoms as $uom)
                                        <option value="{{ $uom->id }}"
                                            {{ (int) ($line['uom_id'] ?? 0) === $uom->id ? 'selected' : '' }}>
                                            {{ $uom->code }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number"
                                       name="items[{{ $idx }}][unit_price]"
                                       class="form-control form-control-sm js-unit-price"
                                       step="0.01"
                                       min="0"
                                       value="{{ $line['unit_price'] ?? '' }}">
                            </td>
                            <td class="js-col-line-total">
                                <input type="number"
                                       name="items[{{ $idx }}][line_total]"
                                       class="form-control form-control-sm js-line-total"
                                       step="0.01"
                                       min="0"
                                       value="{{ $line['line_total'] ?? '' }}"
                                       readonly>
                            </td>

                            <td class="text-center">
                                <button type="button"
                                        class="btn btn-sm {{ $hasBreakup ? 'btn-outline-success' : 'btn-outline-secondary' }} js-breakup-btn"
                                        title="Cost breakup / rate analysis">
                                    {{ $hasBreakup ? 'Breakup ✓' : 'Breakup' }}
                                </button>

                                <input type="hidden"
                                       name="items[{{ $idx }}][cost_breakup_json]"
                                       class="js-cost-breakup-json"
                                       value="{{ e($line['cost_breakup_json'] ?? '') }}">
                            </td>

                            <td>
                                <input type="number"
                                       name="items[{{ $idx }}][sort_order]"
                                       class="form-control form-control-sm"
                                       step="1"
                                       value="{{ $line['sort_order'] ?? $idx }}">
                            </td>
                            <td class="text-center">
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger js-remove-row"
                                        title="Remove row">
                                    &times;
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr class="table-light" id="quotation-total-row">
                        <td colspan="5" class="text-end fw-semibold">
                            Total:
                        </td>
                        <td>
                            <input type="number"
                                   class="form-control form-control-sm"
                                   id="quotation-total-display"
                                   readonly>
                        </td>
                        <td colspan="3"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Quotation' : 'Create Quotation' }}
        </button>
    </div>
</form>

{{-- Cost Breakup Modal (single modal reused for all rows) --}}
<div class="modal fade" id="costBreakupModal" tabindex="-1" aria-labelledby="costBreakupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="costBreakupModalLabel">Cost Breakup / Rate Analysis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    Enter direct costs for this line item (per unit). The system will apply <b>Profit %</b> from the quotation header
                    to compute the final selling rate.
                    <br>
                    <span class="text-muted">
                        Basis: <b>Per Unit</b> (Rs per UOM), <b>Lumpsum</b> (total for this line item), <b>%</b> (percentage of base direct cost).
                    </span>
                </div>

                {{-- Breakup Template selector (CRM → Quotation Breakup Templates) --}}
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-8">
                        <label class="form-label small mb-1">Breakup Template</label>
                        <select class="form-select form-select-sm" id="cb-template-select">
                            <option value="">-- Select template --</option>
                            @foreach($breakupTemplates as $tpl)
                                <option value="{{ $tpl->id }}" @selected($defaultBreakupTemplate && $tpl->id === $defaultBreakupTemplate->id)>
                                    {{ $tpl->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Create/manage templates in <b>CRM &rarr; Breakup Templates</b>.
                            Use one component per line. Optional format: <code>Name|basis|rate</code>
                            (basis = per_unit / lumpsum / percent).
                        </div>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="cb-apply-template">
                            Apply
                        </button>
                        @can('crm.quotation.breakup_templates.manage')
                            @if(\Illuminate\Support\Facades\Route::has('crm.quotation-breakup-templates.index'))
                                <a class="btn btn-sm btn-outline-primary w-100"
                                   href="{{ route('crm.quotation-breakup-templates.index') }}"
                                   target="_blank">
                                    Manage
                                </a>
                            @endif
                        @endcan
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 45%;">Component</th>
                            <th style="width: 20%;">Basis</th>
                            <th style="width: 25%;" class="text-end">Rate</th>
                            <th style="width: 10%;"></th>
                        </tr>
                        </thead>
                        <tbody id="cb-tbody">
                        {{-- rows added by JS --}}
                        </tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="cb-add-line">
                        + Add Row
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-add-defaults">
                        Add Defaults
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="cb-clear">
                        Clear
                    </button>
                </div>

                <hr>

                <div class="row g-2 small">
                    <div class="col-md-4">
                        <div class="text-muted">Direct cost / unit</div>
                        <div class="fw-semibold">{{ $currencySymbol }} <span id="cb-direct">0.00</span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted">Profit / unit (<span id="cb-profit-pct">0</span>%)</div>
                        <div class="fw-semibold">{{ $currencySymbol }} <span id="cb-profit">0.00</span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted">Selling rate / unit</div>
                        <div class="fw-bold">{{ $currencySymbol }} <span id="cb-sell">0.00</span></div>
                    </div>
                </div>

                <div class="mt-2 small text-muted">
                    Tip: In <b>Rate per KG</b> quotations, keep UOM as KG. For <b>rate-only</b> offers, Qty can be 0.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Close
                </button>
                <button type="button" class="btn btn-primary" id="cb-save">
                    Save Breakup &amp; Apply Rate
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Row template for JS (line items) --}}
<template id="item-row-template">
    <tr data-row-index="__INDEX__">
        <td>
            <select name="items[__INDEX__][item_id]"
                    class="form-select form-select-sm">
                <option value="">-- Select --</option>
                @foreach($items as $item)
                    <option value="{{ $item->id }}">
                        {{ $item->code }} - {{ $item->name }}
                    </option>
                @endforeach
            </select>
        </td>
        <td>
            <textarea name="items[__INDEX__][description]"
                     class="form-control form-control-sm"
                     rows="3"
                     style="min-width: 260px; resize: vertical;"
                     required></textarea>
        </td>
        <td>
            <input type="number"
                   name="items[__INDEX__][quantity]"
                   class="form-control form-control-sm js-qty"
                   step="0.001"
                   min="0"
                   value="">
        </td>
        <td>
            <select name="items[__INDEX__][uom_id]"
                    class="form-select form-select-sm">
                <option value="">--</option>
                @foreach($uoms as $uom)
                    <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number"
                   name="items[__INDEX__][unit_price]"
                   class="form-control form-control-sm js-unit-price"
                   step="0.01"
                   min="0"
                   value="">
        </td>
        <td class="js-col-line-total">
            <input type="number"
                   name="items[__INDEX__][line_total]"
                   class="form-control form-control-sm js-line-total"
                   step="0.01"
                   min="0"
                   value=""
                   readonly>
        </td>
        <td class="text-center">
            <button type="button"
                    class="btn btn-sm btn-outline-secondary js-breakup-btn">
                Breakup
            </button>

            <input type="hidden"
                   name="items[__INDEX__][cost_breakup_json]"
                   class="js-cost-breakup-json"
                   value="">
        </td>
        <td>
            <input type="number"
                   name="items[__INDEX__][sort_order]"
                   class="form-control form-control-sm"
                   step="1"
                   value="__INDEX__">
        </td>
        <td class="text-center">
            <button type="button"
                    class="btn btn-sm btn-outline-danger js-remove-row"
                    title="Remove row">
                &times;
            </button>
        </td>
    </tr>
</template>

{{-- Row template for JS (cost breakup rows) --}}
<template id="cb-row-template">
    <tr>
        <td>
            <input type="text" class="form-control form-control-sm cb-name" placeholder="e.g. Fabrication labour">
            <input type="hidden" class="cb-code" value="">
        </td>
        <td>
            <select class="form-select form-select-sm cb-basis">
                <option value="per_unit">Per Unit</option>
                <option value="lumpsum">Lumpsum</option>
                <option value="percent">% of base</option>
            </select>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm cb-rate text-end" step="0.01" min="0" value="0">
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger cb-remove" title="Remove">
                &times;
            </button>
        </td>
    </tr>
</template>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tbody = document.getElementById('items-tbody');
        const tpl = document.getElementById('item-row-template');
        const addBtn = document.getElementById('add-item-btn');
        const totalDisplay = document.getElementById('quotation-total-display');
        const totalRow = document.getElementById('quotation-total-row');

        const quoteModeEl = document.getElementById('quote_mode');
        const rateOnlyWrapper = document.getElementById('rate-only-wrapper');
        const isRateOnlyEl = document.getElementById('is_rate_only');
        const profitPercentEl = document.getElementById('profit_percent');

        const defaultCostHeads = @json($defaultCostHeads);

        // Standard Terms templates (id => content)
        const termsMap = @json($standardTerms->pluck('content', 'id'));

        // Breakup templates (CRM → Quotation Breakup Templates)
        const breakupTemplatesMap = @json($breakupTemplates->pluck('content', 'id'));
        const defaultBreakupTemplateId = @json($defaultBreakupTemplate?->id);
        const cbTemplateSelect = document.getElementById('cb-template-select');
        const cbApplyTemplateBtn = document.getElementById('cb-apply-template');
        const stdTermSelect = document.getElementById('standard_term_id');
        const termsTextEl = document.getElementById('terms_text');
        let lastStdTermId = stdTermSelect ? (stdTermSelect.value || '') : '';

        if (stdTermSelect && termsTextEl) {
            stdTermSelect.addEventListener('change', function () {
                const id = this.value;

                if (!id) {
                    lastStdTermId = id;
                    return;
                }

                if (!termsMap || typeof termsMap[id] === 'undefined') {
                    lastStdTermId = id;
                    return;
                }

                const ok = confirm('Replace current Terms & Conditions text with this template?');
                if (!ok) {
                    this.value = lastStdTermId;
                    return;
                }

                termsTextEl.value = termsMap[id] || '';
                lastStdTermId = id;
            });
        }

        // Breakup modal elements
        const breakupModalEl = document.getElementById('costBreakupModal');
        const breakupModal = breakupModalEl ? new bootstrap.Modal(breakupModalEl) : null;
        const cbTbody = document.getElementById('cb-tbody');
        const cbRowTpl = document.getElementById('cb-row-template');

        const cbDirectEl = document.getElementById('cb-direct');
        const cbProfitPctEl = document.getElementById('cb-profit-pct');
        const cbProfitEl = document.getElementById('cb-profit');
        const cbSellEl = document.getElementById('cb-sell');

        const cbAddLineBtn = document.getElementById('cb-add-line');
        const cbAddDefaultsBtn = document.getElementById('cb-add-defaults');
        const cbClearBtn = document.getElementById('cb-clear');
        const cbSaveBtn = document.getElementById('cb-save');

        if (!tbody || !tpl || !addBtn) {
            return;
        }

        let currentIndex = tbody.querySelectorAll('tr').length;
        let activeRow = null;

        function getProfitPercent() {
            const v = parseFloat(profitPercentEl?.value || '0');
            return isNaN(v) ? 0 : Math.max(0, v);
        }

        function isRateOnlyMode() {
            const mode = quoteModeEl?.value || 'item';
            const ro = !!isRateOnlyEl?.checked;
            return mode === 'rate_per_kg' && ro;
        }

        function toggleRateOnlyUi() {
            const mode = quoteModeEl?.value || 'item';

            if (rateOnlyWrapper) {
                rateOnlyWrapper.style.display = (mode === 'rate_per_kg') ? '' : 'none';
            }

            if (mode !== 'rate_per_kg' && isRateOnlyEl) {
                isRateOnlyEl.checked = false;
            }

            if (totalRow) {
                totalRow.style.display = isRateOnlyMode() ? 'none' : '';
            }

            // Hide "Line Total" column in Rate-only mode (Rate per KG + Rate-only)
            const hideLineTotal = isRateOnlyMode();
            document.querySelectorAll('.js-col-line-total').forEach(function (el) {
                el.style.display = hideLineTotal ? 'none' : '';
            });

            tbody.querySelectorAll('tr').forEach(function (row) {
                recalcRow(row);
            });
            recalcGrandTotal();
        }

        function safeJsonParse(str) {
            try {
                return JSON.parse(str);
            } catch (e) {
                return null;
            }
        }

        function getComponentsFromRow(row) {
            const hidden = row.querySelector('.js-cost-breakup-json');
            const raw = hidden?.value || '';
            if (!raw) return [];
            const decoded = safeJsonParse(raw);
            return Array.isArray(decoded) ? decoded : [];
        }

        function setComponentsToRow(row, components) {
            const hidden = row.querySelector('.js-cost-breakup-json');
            if (hidden) {
                hidden.value = JSON.stringify(components || []);
            }

            const btn = row.querySelector('.js-breakup-btn');
            if (btn) {
                const has = Array.isArray(components) && components.length > 0;
                btn.classList.remove('btn-outline-secondary', 'btn-outline-success');
                btn.classList.add(has ? 'btn-outline-success' : 'btn-outline-secondary');
                btn.textContent = has ? 'Breakup ✓' : 'Breakup';
            }
        }

        function calculateFromBreakup(quantity, components, profitPercent) {
            quantity = Math.max(0, quantity || 0);
            profitPercent = Math.max(0, profitPercent || 0);

            const nonPercent = [];
            const percent = [];

            (components || []).forEach(function (c) {
                const basis = (c.basis || 'per_unit').toString();
                if (basis === 'percent') {
                    percent.push(c);
                } else {
                    nonPercent.push(c);
                }
            });

            let baseUnit = 0;

            nonPercent.forEach(function (c) {
                const basis = (c.basis || 'per_unit').toString();
                const rate = parseFloat(c.rate || '0') || 0;

                if (basis === 'lumpsum') {
                    const den = quantity > 0 ? quantity : 1;
                    baseUnit += rate / den;
                } else {
                    baseUnit += rate;
                }
            });

            let percentUnit = 0;
            percent.forEach(function (c) {
                const pct = parseFloat(c.rate || '0') || 0;
                percentUnit += (baseUnit * pct) / 100.0;
            });

            const directUnit = baseUnit + percentUnit;
            const profitUnit = (directUnit * profitPercent) / 100.0;
            const sellUnit = directUnit + profitUnit;

            return { directUnit, profitUnit, sellUnit };
        }

        function recalcRow(row) {
            const qtyInput = row.querySelector('.js-qty');
            const priceInput = row.querySelector('.js-unit-price');
            const totalInput = row.querySelector('.js-line-total');

            const qty = parseFloat(qtyInput?.value || '0') || 0;

            // If breakup exists, recompute unit price using breakup + profit%
            const components = getComponentsFromRow(row);
            if (Array.isArray(components) && components.length > 0 && priceInput) {
                const profitPercent = getProfitPercent();
                const calc = calculateFromBreakup(qty, components, profitPercent);
                priceInput.value = (calc.sellUnit || 0).toFixed(2);
            }

            const price = parseFloat(priceInput?.value || '0') || 0;

            if (totalInput) {
                if (isRateOnlyMode()) {
                    totalInput.value = '';
                } else {
                    totalInput.value = (qty * price).toFixed(2);
                }
            }
        }

        function recalcGrandTotal() {
            if (isRateOnlyMode()) {
                if (totalDisplay) totalDisplay.value = '';
                return;
            }

            let sum = 0;
            tbody.querySelectorAll('.js-line-total').forEach(function (input) {
                const val = parseFloat(input.value || '0') || 0;
                sum += val;
            });

            if (totalDisplay) {
                totalDisplay.value = sum.toFixed(2);
            }
        }

        function bindRowEvents(row) {
            row.querySelectorAll('.js-qty, .js-unit-price').forEach(function (input) {
                input.addEventListener('input', function () {
                    recalcRow(row);
                    recalcGrandTotal();

                    // If breakup modal is open for this row, update modal summary live.
                    if (activeRow === row && breakupModalEl && breakupModalEl.classList.contains('show')) {
                        updateModalSummary();
                    }
                });
            });

            const removeBtn = row.querySelector('.js-remove-row');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    row.remove();
                    recalcGrandTotal();
                });
            }

            const breakupBtn = row.querySelector('.js-breakup-btn');
            if (breakupBtn) {
                breakupBtn.addEventListener('click', function () {
                    openBreakupModalForRow(row);
                });
            }
        }

        function addItemRow() {
            const html = tpl.innerHTML.replace(/__INDEX__/g, String(currentIndex));
            const temp = document.createElement('tbody');
            temp.innerHTML = html.trim();
            const row = temp.firstElementChild;

            tbody.appendChild(row);
            bindRowEvents(row);

            currentIndex++;
            recalcRow(row);
            recalcGrandTotal();

            // Ensure Rate-only UI (if enabled) is applied to newly added rows
            toggleRateOnlyUi();
        }

        // ------- Breakup Modal helpers -------

        function normalizeBreakupBasis(basis) {
            basis = (basis || '').toString().trim().toLowerCase();

            if (!basis) return 'per_unit';

            if (basis === 'per_unit' || basis === 'perunit' || basis === 'unit' || basis === 'per' || basis === 'per unit') {
                return 'per_unit';
            }

            if (basis === 'lumpsum' || basis === 'lump' || basis === 'ls' || basis === 'lump_sum' || basis === 'lump sum') {
                return 'lumpsum';
            }

            if (basis === 'percent' || basis === '%' || basis === 'percentage' || basis === 'pct') {
                return 'percent';
            }

            return 'per_unit';
        }

        function parseBreakupTemplateContent(content) {
            const out = [];
            if (!content) return out;

            const lines = content.toString().split(/\r?\n/);

            lines.forEach(function (line) {
                let l = (line || '').toString().trim();
                if (!l) return;

                // allow comments
                if (l.startsWith('#') || l.startsWith('//')) return;

                // strip bullets / numbering
                l = l.replace(/^\s*[\-\*\u2022]\s*/, '');
                l = l.replace(/^\s*\d+[\)\.]\s*/, '');

                // Support: Name|basis|rate
                const parts = l.split('|').map(function (p) { return p.trim(); });

                const name = (parts[0] || '').trim();
                if (!name) return;

                const basis = normalizeBreakupBasis(parts[1] || 'per_unit');

                let rate = 0;
                if (parts.length >= 3) {
                    const parsed = parseFloat(parts[2]);
                    rate = isNaN(parsed) ? 0 : parsed;
                }

                out.push({
                    code: null,
                    name: name,
                    basis: basis,
                    rate: Math.max(0, rate),
                });
            });

            return out;
        }

        function applySelectedBreakupTemplate(forceReplace) {
            if (!activeRow) return;
            if (!cbTemplateSelect) return;

            const tplId = cbTemplateSelect.value || '';
            if (!tplId) {
                alert('Please select a breakup template first.');
                return;
            }

            if (!breakupTemplatesMap || typeof breakupTemplatesMap[tplId] === 'undefined') {
                alert('Template not found.');
                return;
            }

            const comps = parseBreakupTemplateContent(breakupTemplatesMap[tplId] || '');
            if (!comps.length) {
                alert('Selected template has no valid lines.');
                return;
            }

            const existing = getModalComponents().filter(function (c) {
                return (c.name || '').toString().trim() !== '';
            });

            if (!forceReplace && existing.length) {
                const ok = confirm('Replace current breakup rows with selected template?');
                if (!ok) return;
            }

            clearModalRows();
            comps.forEach(function (c) {
                addModalRow(c);
            });

            if (activeRow) {
                activeRow.dataset.breakupTemplateId = tplId;
            }

            updateModalSummary();
        }

        function clearModalRows() {
            if (cbTbody) cbTbody.innerHTML = '';
        }

        function addModalRow(data) {
            if (!cbTbody || !cbRowTpl) return;

            const temp = document.createElement('tbody');
            temp.innerHTML = cbRowTpl.innerHTML.trim();
            const tr = temp.firstElementChild;

            const nameEl = tr.querySelector('.cb-name');
            const codeEl = tr.querySelector('.cb-code');
            const basisEl = tr.querySelector('.cb-basis');
            const rateEl = tr.querySelector('.cb-rate');

            nameEl.value = (data && data.name) ? data.name : '';
            codeEl.value = (data && data.code) ? data.code : '';
            basisEl.value = (data && data.basis) ? data.basis : 'per_unit';
            rateEl.value = (data && (data.rate !== undefined)) ? data.rate : 0;

            // Remove
            tr.querySelector('.cb-remove').addEventListener('click', function () {
                tr.remove();
                updateModalSummary();
            });

            // Recalc on changes
            [nameEl, basisEl, rateEl].forEach(function (el) {
                el.addEventListener('input', updateModalSummary);
                el.addEventListener('change', updateModalSummary);
            });

            cbTbody.appendChild(tr);
        }

        function getModalComponents() {
            const rows = cbTbody ? cbTbody.querySelectorAll('tr') : [];
            const out = [];

            rows.forEach(function (tr) {
                const name = tr.querySelector('.cb-name')?.value?.trim() || '';
                const code = tr.querySelector('.cb-code')?.value?.trim() || '';
                const basis = tr.querySelector('.cb-basis')?.value || 'per_unit';
                const rate = parseFloat(tr.querySelector('.cb-rate')?.value || '0') || 0;

                if (!name) return;

                out.push({
                    code: code || null,
                    name: name,
                    basis: basis,
                    rate: Math.max(0, rate),
                });
            });

            return out;
        }

        function updateModalSummary() {
            if (!activeRow) return;

            const qty = parseFloat(activeRow.querySelector('.js-qty')?.value || '0') || 0;
            const profitPercent = getProfitPercent();
            const comps = getModalComponents();
            const calc = calculateFromBreakup(qty, comps, profitPercent);

            if (cbDirectEl) cbDirectEl.textContent = (calc.directUnit || 0).toFixed(2);
            if (cbProfitPctEl) cbProfitPctEl.textContent = profitPercent.toFixed(2);
            if (cbProfitEl) cbProfitEl.textContent = (calc.profitUnit || 0).toFixed(2);
            if (cbSellEl) cbSellEl.textContent = (calc.sellUnit || 0).toFixed(2);
        }

        function openBreakupModalForRow(row) {
            activeRow = row;
            clearModalRows();

            // Remember template selection per row (not saved to DB, only for UI)
            if (cbTemplateSelect) {
                const stored = row.dataset.breakupTemplateId || '';
                const def = defaultBreakupTemplateId ? String(defaultBreakupTemplateId) : '';
                const use = stored || def;

                cbTemplateSelect.value = use;
                row.dataset.breakupTemplateId = use;
            }

            const existing = getComponentsFromRow(row);

            if (existing && existing.length) {
                existing.forEach(function (c) {
                    addModalRow(c);
                });
            } else {
                // If no existing breakup, auto-load from selected/default template (if any)
                let compsFromTpl = [];
                if (cbTemplateSelect) {
                    const tplId = cbTemplateSelect.value || '';
                    if (tplId && breakupTemplatesMap && typeof breakupTemplatesMap[tplId] !== 'undefined') {
                        compsFromTpl = parseBreakupTemplateContent(breakupTemplatesMap[tplId] || '');
                    }
                }

                if (compsFromTpl.length) {
                    compsFromTpl.forEach(function (c) {
                        addModalRow(c);
                    });
                } else {
                    // start with 1 empty row for convenience
                    addModalRow({ name: '', basis: 'per_unit', rate: 0 });
                }
            }

            updateModalSummary();
            if (breakupModal) breakupModal.show();
        }

        if (cbAddLineBtn) {
            cbAddLineBtn.addEventListener('click', function () {
                addModalRow({ name: '', basis: 'per_unit', rate: 0 });
                updateModalSummary();
            });
        }

        if (cbAddDefaultsBtn) {
            cbAddDefaultsBtn.addEventListener('click', function () {
                defaultCostHeads.forEach(function (h) {
                    addModalRow({ code: h.code, name: h.name, basis: 'per_unit', rate: 0 });
                });
                updateModalSummary();
            });
        }

        if (cbClearBtn) {
            cbClearBtn.addEventListener('click', function () {
                clearModalRows();
                addModalRow({ name: '', basis: 'per_unit', rate: 0 });
                updateModalSummary();
            });
        }



        if (cbTemplateSelect) {
            cbTemplateSelect.addEventListener('change', function () {
                if (activeRow) {
                    activeRow.dataset.breakupTemplateId = (this.value || '');
                }
            });
        }

        if (cbApplyTemplateBtn) {
            cbApplyTemplateBtn.addEventListener('click', function () {
                applySelectedBreakupTemplate(false);
            });
        }
        if (cbSaveBtn) {
            cbSaveBtn.addEventListener('click', function () {
                if (!activeRow) return;

                const comps = getModalComponents();

                // remember template selection (UI only)
                if (cbTemplateSelect) {
                    activeRow.dataset.breakupTemplateId = (cbTemplateSelect.value || '');
                }

                setComponentsToRow(activeRow, comps);

                // Apply computed rate back to row
                recalcRow(activeRow);
                recalcGrandTotal();

                if (breakupModal) breakupModal.hide();
            });
        }

        // -------- init --------
        tbody.querySelectorAll('tr').forEach(function (row) {
            bindRowEvents(row);
            recalcRow(row);
        });
        recalcGrandTotal();

        if (tbody.querySelectorAll('tr').length === 0) {
            addItemRow();
        }

        addBtn.addEventListener('click', function () {
            addItemRow();
        });

        if (quoteModeEl) {
            quoteModeEl.addEventListener('change', toggleRateOnlyUi);
        }
        if (isRateOnlyEl) {
            isRateOnlyEl.addEventListener('change', toggleRateOnlyUi);
        }
        if (profitPercentEl) {
            profitPercentEl.addEventListener('input', function () {
                tbody.querySelectorAll('tr').forEach(function (row) {
                    recalcRow(row);
                });
                recalcGrandTotal();
                updateModalSummary();
            });
        }

        // apply initial visibility
        toggleRateOnlyUi();
    });
</script>
