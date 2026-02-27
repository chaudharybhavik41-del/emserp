@php
/** @var \App\Models\Party|null $party */
$isEdit = isset($party) && $party->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('parties.update', $party) : route('parties.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

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

    {{-- ================= TAX DETAILS ================= --}}
    <div class="card mb-3">
        <div class="card-header fw-semibold">Tax Details</div>
        <div class="card-body">

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">GSTIN</label>
                    <input type="text" id="gstin" name="gstin"
                           class="form-control"
                           value="{{ old('gstin', $party->gstin ?? '') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">PAN</label>
                    <input type="text" id="pan" name="pan"
                           class="form-control"
                           value="{{ old('pan', $party->pan ?? '') }}">
                </div>
            </div>

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
                    <input type="text" name="address_line1"
                           class="form-control"
                           value="{{ old('address_line1', $party->address_line1 ?? '') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Address Line 2</label>
                    <input type="text" name="address_line2"
                           class="form-control"
                           value="{{ old('address_line2', $party->address_line2 ?? '') }}">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">City</label>
                    <input type="text" name="city"
                           class="form-control"
                           value="{{ old('city', $party->city ?? '') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">State</label>
                    <input type="text" name="state"
                           class="form-control"
                           value="{{ old('state', $party->state ?? '') }}">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Pincode</label>
                    <input type="text" name="pincode"
                           class="form-control"
                           value="{{ old('pincode', $party->pincode ?? '') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Country</label>
                    <input type="text" name="country"
                           class="form-control"
                           value="{{ old('country', $party->country ?? 'India') }}">
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    const gst = document.getElementById('gstin');
    const pan = document.getElementById('pan');

    if (!gst || !pan) return;

    gst.addEventListener('input', function () {
        const value = gst.value.toUpperCase().replace(/\s+/g, '');
        gst.value = value;

        if (value.length === 15) {
            const extractedPan = value.substring(2, 12);
            const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]$/;
            if (panRegex.test(extractedPan)) {
                pan.value = extractedPan;
            }
        }
    });
});
</script>
@endpush
