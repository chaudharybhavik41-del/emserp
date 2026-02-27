@extends('layouts.erp')

@section('title', 'Edit Stock Adjustment ' . ($adjustment->reference_number ?? ''))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            Edit Stock Adjustment
            @if($adjustment->reference_number)
                - {{ $adjustment->reference_number }}
            @endif
        </h1>
        <div class="d-flex gap-2">
            <a href="{{ route('store-stock-adjustments.show', $adjustment) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        </div>
    </div>

    @if ($errors->has('general'))
        <div class="alert alert-danger">{{ $errors->first('general') }}</div>
    @endif

    <form method="POST" action="{{ route('store-stock-adjustments.update', $adjustment) }}">
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Adjustment Date</label>
                        <input type="date" name="adjustment_date" class="form-control form-control-sm"
                               value="{{ old('adjustment_date', optional($adjustment->adjustment_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Project</label>
                        <select name="project_id" class="form-select form-select-sm">
                            <option value="">-- None --</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" @selected(old('project_id', $adjustment->project_id) == $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control form-control-sm"
                               value="{{ old('reason', $adjustment->reason) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ old('remarks', $adjustment->remarks) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Opening Lines</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddLine">Add line</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="openingTable">
                        <thead>
                        <tr>
                            <th style="width: 26%;">Item</th>
                            <th style="width: 20%;">Brand</th>
                            <th style="width: 10%;">UOM</th>
                            <th class="text-end" style="width: 12%;">Qty</th>
                            <th class="text-end" style="width: 12%;">Unit Rate</th>
                            <th class="text-end" style="width: 12%;">Amount</th>
                            <th>Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php $idx = 0; @endphp
                        @foreach($adjustment->lines as $l)
                            @php
                                $rate = old("opening_lines.$idx.unit_rate", $l->unit_rate ?? $l->stockItem?->opening_unit_rate);
                                $qty  = old("opening_lines.$idx.quantity", $l->quantity);
                                $amt  = ($rate && $qty) ? ((float)$rate * (float)$qty) : null;
                            @endphp
                            <tr>
                                <td>
                                    <input type="hidden" name="opening_lines[{{ $idx }}][line_id]" value="{{ $l->id }}">
                                    <input type="hidden" name="opening_lines[{{ $idx }}][stock_item_id]" value="{{ $l->store_stock_item_id }}">
                                    <input type="hidden" name="opening_lines[{{ $idx }}][item_id]" value="{{ $l->item_id }}">
                                    @php
                                        $itObj = $l->stockItem?->item;
                                        $itemLabel = $itObj
                                            ? (($itObj->code ? ($itObj->code . ' - ') : '') . $itObj->name)
                                            : ('Item #' . $l->item_id);
                                    @endphp
                                    <input type="text" class="form-control form-control-sm" value="{{ $itemLabel }}" readonly>
                                </td>
                                <td>
                                    <select name="opening_lines[{{ $idx }}][brand]" class="form-select form-select-sm opening-brand-select">
                                        @php $b = old("opening_lines.$idx.brand", $l->brand ?? $l->stockItem?->brand); @endphp
                                        @if($b)
                                            <option value="{{ $b }}" selected>{{ $b }}</option>
                                        @endif
                                    </select>
                                </td>
                                <td>
                                    <select name="opening_lines[{{ $idx }}][uom_id]" class="form-select form-select-sm">
                                        @foreach($uoms as $u)
                                            <option value="{{ $u->id }}" @selected(old("opening_lines.$idx.uom_id", $l->uom_id) == $u->id)>{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-end">
                                    <input type="number" step="0.001" min="0"
                                           name="opening_lines[{{ $idx }}][quantity]"
                                           class="form-control form-control-sm text-end opening-qty"
                                           value="{{ $qty }}">
                                </td>
                                <td class="text-end">
                                    <input type="number" step="0.01" min="0"
                                           name="opening_lines[{{ $idx }}][unit_rate]"
                                           class="form-control form-control-sm text-end opening-rate"
                                           value="{{ $rate }}">
                                </td>
                                <td class="text-end">
                                    <input type="text" class="form-control form-control-sm text-end opening-amount" value="{{ $amt !== null ? number_format((float)$amt, 2, '.', '') : '' }}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="opening_lines[{{ $idx }}][remarks]" class="form-control form-control-sm"
                                           value="{{ old("opening_lines.$idx.remarks", $l->remarks) }}">
                                </td>
                            </tr>
                            @php $idx++; @endphp
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary">Update</button>
            <a href="{{ route('store-stock-adjustments.show', $adjustment) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    <script>
        window.__itemMeta = {!! $itemMetaJson !!};

        function initBrandSelect(el, itemId) {
            // Select2 tags dropdown (if available)
            if (window.jQuery && jQuery.fn.select2) {
                const brands = (window.__itemMeta[itemId] && window.__itemMeta[itemId].brands) ? window.__itemMeta[itemId].brands : [];
                const data = brands.map(b => ({id: b, text: b}));
                jQuery(el).empty();
                data.forEach(o => jQuery(el).append(new Option(o.text, o.id, false, false)));
                jQuery(el).select2({ tags: true, width: '100%' });
            }
        }

        function recalcRow(tr) {
            const qty = parseFloat(tr.querySelector('.opening-qty')?.value || '0') || 0;
            const rate = parseFloat(tr.querySelector('.opening-rate')?.value || '0') || 0;
            const amt = (qty > 0 && rate > 0) ? (qty * rate) : 0;
            const amtEl = tr.querySelector('.opening-amount');
            if (amtEl) amtEl.value = amt > 0 ? amt.toFixed(2) : '';
        }

        document.querySelectorAll('#openingTable tbody tr').forEach(tr => {
            tr.addEventListener('input', () => recalcRow(tr));
            const itemId = tr.querySelector('input[name*="[item_id]"]')?.value;
            const brandSel = tr.querySelector('.opening-brand-select');
            if (brandSel && itemId) initBrandSelect(brandSel, itemId);
            recalcRow(tr);
        });

        // Add new lines (same behavior as create: user selects item, then brand suggestions load)
        let nextIndex = {{ $idx }};
        document.getElementById('btnAddLine')?.addEventListener('click', function () {
            const tbody = document.querySelector('#openingTable tbody');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select name="opening_lines[${nextIndex}][item_id]" class="form-select form-select-sm opening-item">
                        <option value="">-- select --</option>
                        @foreach($items as $it)
                            <option value="{{ $it->id }}">{{ $it->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <select name="opening_lines[${nextIndex}][brand]" class="form-select form-select-sm opening-brand-select"></select>
                </td>
                <td>
                    <select name="opening_lines[${nextIndex}][uom_id]" class="form-select form-select-sm">
                        @foreach($uoms as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="text-end">
                    <input type="number" step="0.001" min="0" name="opening_lines[${nextIndex}][quantity]" class="form-control form-control-sm text-end opening-qty" value="0">
                </td>
                <td class="text-end">
                    <input type="number" step="0.01" min="0" name="opening_lines[${nextIndex}][unit_rate]" class="form-control form-control-sm text-end opening-rate" value="">
                </td>
                <td class="text-end">
                    <input type="text" class="form-control form-control-sm text-end opening-amount" value="" readonly>
                </td>
                <td>
                    <input type="text" name="opening_lines[${nextIndex}][remarks]" class="form-control form-control-sm" value="">
                </td>
            `;
            tbody.appendChild(tr);

            tr.addEventListener('input', () => recalcRow(tr));
            const itemSel = tr.querySelector('.opening-item');
            const brandSel = tr.querySelector('.opening-brand-select');
            if (itemSel) {
                itemSel.addEventListener('change', function () {
                    const itemId = this.value;
                    initBrandSelect(brandSel, itemId);
                    // auto uom from meta if available
                    const meta = window.__itemMeta[itemId];
                    if (meta && meta.uom_id) {
                        const uomSel = tr.querySelector('select[name*="[uom_id]"]');
                        if (uomSel) uomSel.value = meta.uom_id;
                    }
                });
            }

            nextIndex++;
        });
    </script>
@endsection
