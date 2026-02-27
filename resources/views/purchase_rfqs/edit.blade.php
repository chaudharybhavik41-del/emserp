@extends('layouts.erp')

@section('title', 'Edit RFQ ' . $rfq->code)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Edit Purchase RFQ {{ $rfq->code }}</h1>
            <div class="small text-muted">Status: {{ ucfirst($rfq->status) }}</div>
        </div>
        <a href="{{ route('purchase-rfqs.show', $rfq) }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    <form action="{{ route('purchase-rfqs.update', $rfq) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- keep your header fields same --}}
        {{-- ... --}}

        {{-- Vendors --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Vendors</strong>
                @if(!$readonly)
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-vendor-btn">
                        <i class="bi bi-plus-circle"></i> Add Vendor
                    </button>
                @endif
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
            <a href="{{ route('purchase-rfqs.show', $rfq) }}" class="btn btn-secondary">Back</a>
            @if(!$readonly)
                <button type="submit" class="btn btn-primary">Update RFQ</button>
            @endif
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

    $existingVendors = $rfq->vendors->map(function ($rv) {
        return [
            'party_id'      => $rv->vendor_party_id,
            'email'         => $rv->email ?? '',
            'contact_name'  => $rv->contact_name ?? '',
            'contact_phone' => $rv->contact_phone ?? '',
        ];
    })->values()->toArray();
@endphp

<script>
    const VENDORS_META = @json($vendorsMeta);
    const EXISTING_VENDORS = @json($existingVendors);
    const READONLY = @json((bool) $readonly);

    let vendorRowIndex = 0;

    function findVendorMeta(id){ return VENDORS_META.find(x => String(x.id) === String(id)); }

    function ensureSelect2(el, opts){
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery(el).select2(Object.assign({ width:'100%', allowClear:true }, opts || {}));
        }
    }

    function vendorOptions(selected){
        return VENDORS_META.map(v => {
            const sel = String(v.id) === String(selected) ? 'selected' : '';
            return `<option value="${v.id}" ${sel}>${v.name}</option>`;
        }).join('');
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
                <select name="vendors[${idx}][party_id]" class="form-select form-select-sm vendor-id" required ${READONLY ? 'disabled' : ''}>
                    <option value="">-- Select --</option>
                    ${vendorOptions(data.party_id || '')}
                </select>
            </td>
            <td><input type="email" name="vendors[${idx}][email]" class="form-control form-control-sm vendor-email" value="${data.email || ''}" ${READONLY ? 'disabled' : ''}></td>
            <td><input type="text" name="vendors[${idx}][contact_name]" class="form-control form-control-sm vendor-contact-name" value="${data.contact_name || ''}" ${READONLY ? 'disabled' : ''}></td>
            <td><input type="text" name="vendors[${idx}][contact_phone]" class="form-control form-control-sm vendor-contact-phone" value="${data.contact_phone || ''}" ${READONLY ? 'disabled' : ''}></td>
            <td class="text-center">
                ${READONLY ? '' : `<button type="button" class="btn btn-sm btn-outline-danger remove-vendor">&times;</button>`}
            </td>
        `;
        document.querySelector('#vendors-table tbody').appendChild(tr);

        const vendorSelect = tr.querySelector('.vendor-id');
        if (!READONLY) {
            ensureSelect2(vendorSelect, { placeholder:'Vendor' });

            vendorSelect.addEventListener('change', function() {
                applyVendorDefaults(tr, vendorSelect.value);
            });

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                window.jQuery(vendorSelect).on('select2:select', function() {
                    applyVendorDefaults(tr, vendorSelect.value);
                });
            }
        }

        if (data.party_id) applyVendorDefaults(tr, data.party_id);
        return tr;
    }

    document.addEventListener('click', function(e){
        if (READONLY) return;
        if (e.target.id === 'add-vendor-btn') addVendorRow();
        if (e.target.classList.contains('remove-vendor')) e.target.closest('tr').remove();
    });

    // initial render
    if (EXISTING_VENDORS && EXISTING_VENDORS.length) {
        EXISTING_VENDORS.forEach(v => addVendorRow(v));
    } else {
        addVendorRow();
    }
</script>
</div>
@endsection
