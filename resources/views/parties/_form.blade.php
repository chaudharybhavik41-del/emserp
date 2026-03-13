@php
/** @var \App\Models\Party|null $party */
$isEdit = isset($party) && $party->exists;
@endphp
{{-- ================= GSTIN QUESTION MODAL ================= --}}
<div class="modal fade" id="gstinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">GST Details</h5>
            </div>
            <div class="modal-body text-center">
                <p>Do you have a GSTIN?</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-primary" id="gstYesBtn">Yes</button>
                    <button type="button" class="btn btn-secondary" id="gstNoBtn">No</button>
                </div>
            </div>
        </div>
    </div>
</div>
<form method="POST"
      action="{{ $isEdit ? route('parties.update', $party) : route('parties.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif
  {{-- ================= TAX DETAILS ================= --}}
<div class="card mb-3">
    <div class="card-header fw-semibold">Tax Details</div>
    <div class="card-body">

        <!-- GSTIN & PAN Fields -->
        <div class="row mb-3">
            <!-- GSTIN: initially hidden -->
        <div class="col-md-6" id="gstinWrapper"
     style="{{ old('has_gstin', $party->has_gstin ?? 0) ? '' : 'display:none;' }}">

    <label class="form-label">GSTIN</label>

    <input type="hidden" name="has_gstin" id="has_gstin"
           value="{{ old('has_gstin', $party->has_gstin ?? '') }}">

    <div class="input-group">

        <input type="text"
               id="gstin"
               name="gstin"
               class="form-control"
               value="{{ old('gstin', $party->gstin ?? '') }}"
               placeholder="Enter GSTIN"
               {{ $isEdit ? 'readonly' : '' }}>

        <button type="button"
                class="btn btn-primary"
                id="searchGST"
                {{ $isEdit ? 'disabled' : '' }}>
            Search
        </button>

    </div>

</div>
            <!-- PAN: always visible -->
            <div class="col-md-6">
                <label class="form-label">PAN</label>
                <input type="text" id="pan" name="pan"
                       class="form-control @error('pan') is-invalid @enderror"
                       value="{{ old('pan', $party->pan ?? '') }}">
                @error('pan') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <!-- MSME & Active -->
        <div class="row">
            <div class="col-md-6">
                <label class="form-label">MSME No</label>
                <input type="text" name="msme_no"
                       class="form-control"
                       value="{{ old('msme_no', $party->msme_no ?? '') }}">
            </div>

            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input"
                           type="checkbox"
                           name="is_active"
                           value="1"
                           {{ old('is_active', $party->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label">Active</label>
                </div>
            </div>
        </div>

    </div>
</div>



    {{-- ================= BASIC DETAILS ================= --}}
    <div class="card mb-3">
        <div class="card-header fw-semibold">Basic Details</div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    @if($isEdit)
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code"
                               class="form-control @error('code') is-invalid @enderror"
                               value="{{ old('code', $party->code) }}" required>
                        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @else
                        <label class="form-label">Code</label>
                        <div class="form-control-plaintext text-muted">
                            Auto-generated on save
                        </div>
                    @endif
                </div>

                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $party->name ?? '') }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Legal Name</label>
                    <input type="text" name="legal_name"
                           class="form-control"
                           value="{{ old('legal_name', $party->legal_name ?? '') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label d-block">Party Type</label>
                    @foreach(['supplier' => 'Supplier', 'contractor' => 'Contractor', 'client' => 'Client'] as $key => $label)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="is_{{ $key }}"
                                   value="1"
                                   {{ old("is_$key", $party->{"is_$key"} ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

 

    {{-- ================= CONTACT DETAILS ================= --}}
    <div class="card mb-3">
        <div class="card-header fw-semibold">Contact Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Primary Phone</label>
                    <input type="text" name="primary_phone"
                           class="form-control"
                           value="{{ old('primary_phone', $party->primary_phone ?? '') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Primary Email</label>
                    <input type="email" name="primary_email"
                           class="form-control"
                           value="{{ old('primary_email', $party->primary_email ?? '') }}">
                </div>
            </div>
        </div>
    </div>

    {{-- ================= ADDRESS ================= --}}
  <div class="card mb-4">
    <div class="card-header fw-semibold">Address</div>

    <div class="card-body">

        <div class="row mb-3">

            <div class="col-md-6">
                <label class="form-label">Address Line 1</label>

                <input type="text"
                       name="address_line1"
                       class="form-control"
                       value="{{ old('address_line1', $party->address_line1 ?? '') }}"
                       {{ $isEdit ? 'readonly' : '' }}>
            </div>

            <div class="col-md-6">
                <label class="form-label">Address Line 2</label>

                <input type="text"
                       name="address_line2"
                       class="form-control"
                       value="{{ old('address_line2', $party->address_line2 ?? '') }}"
                       {{ $isEdit ? 'readonly' : '' }}>
            </div>

        </div>

        <div class="row mb-3">

            <div class="col-md-6">
                <label class="form-label">City</label>

                <input type="text"
                       name="city"
                       class="form-control"
                       value="{{ old('city', $party->city ?? '') }}"
                       {{ $isEdit ? 'readonly' : '' }}>
            </div>

            <div class="col-md-6">
                <label class="form-label">State</label>

                <input type="text"
                       name="state"
                       class="form-control"
                       value="{{ old('state', $party->state ?? '') }}"
                       {{ $isEdit ? 'readonly' : '' }}>
            </div>

        </div>

        <div class="row">

            <div class="col-md-6">
                <label class="form-label">Pincode</label>

                <input type="text"
                       name="pincode"
                       class="form-control"
                       value="{{ old('pincode', $party->pincode ?? '') }}"
                       {{ $isEdit ? 'readonly' : '' }}>
            </div>

            <div class="col-md-6">
                <label class="form-label">Country</label>

                <input type="text"
                       name="country"
                       class="form-control"
                       value="{{ old('country', $party->country ?? 'India') }}"
                       {{ $isEdit ? 'readonly' : '' }}>
            </div>

        </div>

    </div>
</div>

    {{-- ================= ACTIONS ================= --}}
    <div class="d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update' : 'Create' }}
        </button>
        <a href="{{ route('parties.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>
</form>
@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function () {

    $('#searchGST').click(function () {

        let gstin = $('#gstin').val();

        if (gstin === '') {
            alert('Please enter GSTIN');
            return;
        }

        $.ajax({
            url: "{{ route('parties.gstin.search') }}",
            type: "GET",
            data: { gstin: gstin },

            success: function (response) {

                console.log(response);

                $('input[name="name"]').val(response.lgnm);
                $('input[name="legal_name"]').val(response.tradeNam);

                $('input[name="address_line1"]').val(response.pradr.addr.bnm);
                $('input[name="address_line2"]').val(response.pradr.addr.st);

                $('input[name="city"]').val(response.pradr.addr.loc);
                $('input[name="state"]').val(response.pradr.addr.stcd);
                $('input[name="pincode"]').val(response.pradr.addr.pncd);

                let pan = response.gstin.substring(2,12);
                $('#pan').val(pan);
            },

            error: function () {
                alert('GST Data not found');
            }

        });

    });

});
</script>
  <script>
document.addEventListener('DOMContentLoaded', function () {

    const gstinModal = new bootstrap.Modal(document.getElementById('gstinModal'));
    const gstinWrapper = document.getElementById('gstinWrapper');
    const hasGstin = document.getElementById('has_gstin');

    let isEdit = {{ $isEdit ? 'true' : 'false' }};

    // Only show popup on CREATE
    if (!isEdit) {
        gstinModal.show();
    }

    // YES
    document.getElementById('gstYesBtn').addEventListener('click', function () {

        gstinWrapper.style.display = '';
        hasGstin.value = 1;

        gstinModal.hide();
    });

    // NO
    document.getElementById('gstNoBtn').addEventListener('click', function () {

        gstinWrapper.style.display = 'none';
        hasGstin.value = 0;

        gstinModal.hide();
    });

});
</script>
@endpush