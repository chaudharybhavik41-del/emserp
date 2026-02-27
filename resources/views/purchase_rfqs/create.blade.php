@extends('layouts.erp')

@section('title', 'Create RFQ')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Create Purchase RFQ</h1>
            <div class="small text-muted">Indent-linked rows are locked (readonly) but still submitted.</div>
        </div>
        <a href="{{ route('purchase-rfqs.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
            <button type="button" class="btn btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('purchase-rfqs.store') }}" method="POST" id="rfq-form">
        @csrf

        {{-- Header --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Indent (optional)</label>
                        <select name="purchase_indent_id" id="purchase_indent_id" class="form-select form-select-sm">
                            <option value="">-- No Indent (Direct RFQ) --</option>
                            @foreach($indents as $ind)
                                <option value="{{ $ind->id }}" @selected((string) old('purchase_indent_id', optional($preloadedIndent)->id) === (string) $ind->id)>
                                    {{ $ind->code }} @if($ind->project) — {{ $ind->project->code }} @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text small">Change indent → page reloads to preload items.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Project (optional)</label>
                        <select name="project_id" class="form-select form-select-sm">
                            <option value="">-- None --</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" @selected((string) old('project_id') === (string) $p->id)>
                                    {{ $p->code }} - {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Department</label>
                        <select name="department_id" class="form-select form-select-sm">
                            <option value="">-- Auto from Indent --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}" @selected((string) old('department_id') === (string) $d->id)>
                                    {{ $d->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text small">Required only if RFQ is not linked to an indent.</div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label form-label-sm">RFQ Date</label>
                        <input type="date" name="rfq_date" class="form-control form-control-sm"
                               value="{{ old('rfq_date', now()->toDateString()) }}" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label form-label-sm">Due Date</label>
                        <input type="date" name="due_date" class="form-control form-control-sm"
                               value="{{ old('due_date') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label form-label-sm">Payment (days)</label>
                        <input type="number" name="payment_terms_days" class="form-control form-control-sm" min="0"
                               value="{{ old('payment_terms_days') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label form-label-sm">Delivery (days)</label>
                        <input type="number" name="delivery_terms_days" class="form-control form-control-sm" min="0"
                               value="{{ old('delivery_terms_days') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Freight Terms</label>
                        <input type="text" name="freight_terms" class="form-control form-control-sm"
                               value="{{ old('freight_terms') }}">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label form-label-sm">Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control form-control-sm">{{ old('remarks') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Items --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Items</strong>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" id="add-item-btn">
                        <i class="bi bi-plus-circle"></i> Add Item
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="load-indent-items-btn">
                        <i class="bi bi-download"></i> Load Indent Items
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="items-table">
                        <thead class="table-light">
                        <tr>
                            <th style="width:3%">#</th>
                            <th style="width:20%">Item *</th>
                            <th style="width:10%">Indent Line</th>
                            <th style="width:12%">Brand</th>
                            <th style="width:10%">Grade</th>
                            <th style="width:6%">T</th>
                            <th style="width:6%">W</th>
                            <th style="width:6%">L</th>
                            <th style="width:6%">Pcs</th>
                            <th style="width:10%">Qty *</th>
                            <th style="width:6%">UOM</th>
                            <th style="width:3%"></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Vendors --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Vendors</strong>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-vendor-btn">
                    <i class="bi bi-plus-circle"></i> Add Vendor
                </button>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="vendors-table">
                        <thead class="table-light">
                        <tr>
                            <th style="width:3%">#</th>
                            <th style="width:30%">Vendor *</th>
                            <th style="width:22%">Email</th>
                            <th style="width:20%">Contact Name</th>
                            <th style="width:20%">Contact Phone</th>
                            <th style="width:3%"></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('purchase-rfqs.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" id="submit-btn">Save RFQ</button>
        </div>
    </form>

@php
    $vendorsMeta = $vendors->map(function ($v) {
        $first = ($v->contacts && $v->contacts->count()) ? $v->contacts->first() : null;
        return [
            'id'            => $v->id,
            'name'          => $v->name,
            'email'         => $first->email ?? ($v->primary_email ?? ''),
            'contact_name'  => $first->name ?? '',
            'contact_phone' => $first->phone ?? '',
        ];
    })->values()->toArray();

    $preloadedItemsMeta = $preloadedItems->map(function ($pi) {
        return [
            'purchase_indent_item_id' => $pi->id,
            'item_id'                 => $pi->item_id,
            'brand'                   => $pi->brand ?? '',
            'grade'                   => $pi->grade ?? '',
            'thickness_mm'            => $pi->thickness_mm,
            'width_mm'                => $pi->width_mm,
            'length_mm'               => $pi->length_mm,
            'qty_pcs'                 => $pi->qty_pcs,
            'quantity'                => $pi->order_qty,
            'allocated_indent_qty'    => $pi->order_qty,
            'uom_id'                  => $pi->uom_id,
        ];
    })->values()->toArray();
@endphp

<script>
    const ITEM_META = {!! $itemMetaJson !!};
    const VENDORS_META = @json($vendorsMeta);
    const PRELOADED_ITEMS = @json($preloadedItemsMeta);

    let itemRowIndex = 0;
    let vendorRowIndex = 0;

    function findItemMeta(id){ return ITEM_META.find(x => String(x.id) === String(id)); }
    function findVendorMeta(id){ return VENDORS_META.find(x => String(x.id) === String(id)); }

    function ensureSelect2(el, opts){
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery(el).select2(Object.assign({ width:'100%', allowClear:true }, opts || {}));
        }
    }

    function itemOptions(selected){
        return ITEM_META.map(it => {
            const sel = String(it.id) === String(selected) ? 'selected' : '';
            return `<option value="${it.id}" ${sel}>${it.name}</option>`;
        }).join('');
    }

    function vendorOptions(selected){
        return VENDORS_META.map(v => {
            const sel = String(v.id) === String(selected) ? 'selected' : '';
            return `<option value="${v.id}" ${sel}>${v.name}</option>`;
        }).join('');
    }

    function rebuildBrandOptions(tr, selectedValue){
        const itemId = tr.querySelector('.item-id').value;
        const meta = findItemMeta(itemId);
        const sel = tr.querySelector('.brand');

        sel.innerHTML = '<option value=""></option>';
        const brands = meta && Array.isArray(meta.brands) ? meta.brands : [];
        brands.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b; opt.textContent = b;
            sel.appendChild(opt);
        });

        ensureSelect2(sel, { tags:true, tokenSeparators:[','], placeholder:'Brand' });

        if (selectedValue) {
            const exists = Array.from(sel.options).some(o => o.value === selectedValue);
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = selectedValue; opt.textContent = selectedValue; opt.selected = true;
                sel.appendChild(opt);
            }
            sel.value = selectedValue;
        }
    }

    function applyUom(tr){
        const itemId = tr.querySelector('.item-id').value;
        const meta = findItemMeta(itemId);

        const uomId = tr.querySelector('.uom-id');
        const badge = tr.querySelector('.uom-badge');

        if (!meta) {
            if (uomId) uomId.value = '';
            if (badge) badge.textContent = '-';
            return;
        }

        if (uomId) uomId.value = meta.uom_id ? meta.uom_id : '';
        const code = meta.uom_code ? meta.uom_code : (meta.uom_name ? meta.uom_name : '-');
        if (badge) badge.textContent = code;
    }

    function lockIndentRow(tr, locked){
        const itemSel = tr.querySelector('.item-id');
        const brandSel = tr.querySelector('.brand');

        if (itemSel) itemSel.setAttribute('data-locked', locked ? '1' : '0');
        if (brandSel) brandSel.setAttribute('data-locked', locked ? '1' : '0');

        const inputs = tr.querySelectorAll('.grade, .t, .w, .l, .pcs, .qty');
        inputs.forEach(el => {
            el.readOnly = !!locked;
            if (locked) el.classList.add('bg-light'); else el.classList.remove('bg-light');
        });

        if (locked) tr.classList.add('table-warning');
        else tr.classList.remove('table-warning');
    }

    function addItemRow(data){
        data = data || {};
        const idx = itemRowIndex++;
        const locked = !!data.purchase_indent_item_id;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="text-muted">${idx+1}</td>
            <td>
                <select name="items[${idx}][item_id]" class="form-select form-select-sm item-id" required>
                    <option value="">-- Select --</option>
                    ${itemOptions(data.item_id || '')}
                </select>
            </td>
            <td>
                <input type="number" name="items[${idx}][purchase_indent_item_id]" class="form-control form-control-sm indent-line"
                       value="${data.purchase_indent_item_id || ''}" placeholder="indent line">
                <input type="number" step="0.001" name="items[${idx}][allocated_indent_qty]" class="form-control form-control-sm mt-1 alloc"
                       value="${data.allocated_indent_qty || ''}" placeholder="alloc qty">
            </td>
            <td>
                <select name="items[${idx}][brand]" class="form-select form-select-sm brand">
                    <option value=""></option>
                </select>
            </td>
            <td><input type="text" name="items[${idx}][grade]" class="form-control form-control-sm grade" value="${data.grade || ''}"></td>
            <td><input type="number" step="0.01" name="items[${idx}][thickness_mm]" class="form-control form-control-sm t" value="${data.thickness_mm || ''}"></td>
            <td><input type="number" step="0.01" name="items[${idx}][width_mm]" class="form-control form-control-sm w" value="${data.width_mm || ''}"></td>
            <td><input type="number" step="0.01" name="items[${idx}][length_mm]" class="form-control form-control-sm l" value="${data.length_mm || ''}"></td>
            <td><input type="number" step="0.001" name="items[${idx}][qty_pcs]" class="form-control form-control-sm pcs" value="${data.qty_pcs || ''}"></td>
            <td><input type="number" step="0.001" name="items[${idx}][quantity]" class="form-control form-control-sm qty" value="${data.quantity || ''}" required></td>
            <td>
                <input type="hidden" name="items[${idx}][uom_id]" class="uom-id" value="${data.uom_id || ''}">
                <span class="badge bg-light text-dark border uom-badge">-</span>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-item">&times;</button>
            </td>
        `;
        document.querySelector('#items-table tbody').appendChild(tr);

        const itemSel = tr.querySelector('.item-id');
        ensureSelect2(itemSel, { placeholder:'Item' });

        rebuildBrandOptions(tr, data.brand || '');
        applyUom(tr);
        lockIndentRow(tr, locked);

        // Change handler for item select
        itemSel.addEventListener('change', function () {
            if (itemSel.getAttribute('data-locked') === '1') return;
            rebuildBrandOptions(tr, '');
            applyUom(tr);
        });

        return tr;
    }

    function clearItemRows(){
        document.querySelector('#items-table tbody').innerHTML = '';
        itemRowIndex = 0;
    }

    function applyVendorDefaults(tr, vendorId){
        const meta = findVendorMeta(vendorId);
        if (!meta) return;

        const email = tr.querySelector('.vendor-email');
        const cname = tr.querySelector('.vendor-contact-name');
        const cphone = tr.querySelector('.vendor-contact-phone');

        if (email) email.value = meta.email || '';
        if (cname) cname.value = meta.contact_name || '';
        if (cphone) cphone.value = meta.contact_phone || '';
    }

    function addVendorRow(data){
        data = data || {};
        const idx = vendorRowIndex++;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="text-muted">${idx+1}</td>
            <td>
                <select name="vendors[${idx}][party_id]" class="form-select form-select-sm vendor-id" required>
                    <option value="">-- Select --</option>
                    ${vendorOptions(data.party_id || '')}
                </select>
            </td>
            <td><input type="email" name="vendors[${idx}][email]" class="form-control form-control-sm vendor-email" value="${data.email || ''}"></td>
            <td><input type="text" name="vendors[${idx}][contact_name]" class="form-control form-control-sm vendor-contact-name" value="${data.contact_name || ''}"></td>
            <td><input type="text" name="vendors[${idx}][contact_phone]" class="form-control form-control-sm vendor-contact-phone" value="${data.contact_phone || ''}"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-vendor">&times;</button></td>
        `;
        document.querySelector('#vendors-table tbody').appendChild(tr);

        const vendorSelect = tr.querySelector('.vendor-id');
        ensureSelect2(vendorSelect, { placeholder:'Vendor' });

        // Bind per row (your proven fix)
        vendorSelect.addEventListener('change', function() {
            applyVendorDefaults(tr, vendorSelect.value);
        });
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery(vendorSelect).on('select2:select', function() {
                applyVendorDefaults(tr, vendorSelect.value);
            });
        }

        if (data.party_id) applyVendorDefaults(tr, data.party_id);
        return tr;
    }

    // Buttons
    document.addEventListener('click', function(e){
        if (e.target.id === 'add-item-btn') addItemRow();
        if (e.target.id === 'load-indent-items-btn') {
            if (!PRELOADED_ITEMS || !PRELOADED_ITEMS.length) {
                alert('No indent items preloaded. Select an indent and reload the page.');
                return;
            }
            clearItemRows();
            PRELOADED_ITEMS.forEach(x => addItemRow(x));
        }

        if (e.target.id === 'add-vendor-btn') addVendorRow();

        if (e.target.classList.contains('remove-item')) e.target.closest('tr').remove();
        if (e.target.classList.contains('remove-vendor')) e.target.closest('tr').remove();
    });

    // Indent reload
    document.addEventListener('change', function(e){
        if (e.target.id === 'purchase_indent_id') {
            const val = e.target.value;
            const url = new URL(window.location.href);
            if (val) url.searchParams.set('purchase_indent_id', val);
            else url.searchParams.delete('purchase_indent_id');
            window.location.href = url.toString();
        }
    });

    // Initial render
    if (PRELOADED_ITEMS && PRELOADED_ITEMS.length) {
        clearItemRows();
        PRELOADED_ITEMS.forEach(x => addItemRow(x));
    } else {
        addItemRow();
    }
    addVendorRow();
</script>
</div>
@endsection
