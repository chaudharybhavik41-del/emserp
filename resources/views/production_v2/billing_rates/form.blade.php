@extends('layouts.erp')

@section('title', 'Production V2 Billing Rate')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $billingRate->exists ? 'Edit' : 'Create' }} Production V2 Billing Rate</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.billing-rates.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'billing'])

    <form method="post" action="{{ $billingRate->exists ? route('projects.production-v2.billing-rates.update', ['project' => $project->id, 'billingRate' => $billingRate->id]) : route('projects.production-v2.billing-rates.store', ['project' => $project->id]) }}" class="row g-3">
        @csrf
        @if($billingRate->exists)
            @method('PUT')
        @endif
        <div class="col-12 col-md-4">
            <label class="form-label">Contractor</label>
            <select name="contractor_party_id" class="form-select @error('contractor_party_id') is-invalid @enderror" required>
                <option value="">Select contractor</option>
                @foreach($contractors as $contractor)
                    <option value="{{ $contractor->id }}" @selected((int) old('contractor_party_id', $billingRate->contractor_party_id) === (int) $contractor->id)>{{ $contractor->name }}</option>
                @endforeach
            </select>
            @error('contractor_party_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Source Type</label>
            <select name="source_type" id="source_type" class="form-select @error('source_type') is-invalid @enderror" required>
                @foreach($sourceTypes as $value => $label)
                    <option value="{{ $value }}" @selected(old('source_type', $billingRate->source_type) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('source_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Operation</label>
            <select name="operation_master_id" class="form-select @error('operation_master_id') is-invalid @enderror">
                <option value="">Not Applicable</option>
                @foreach($operationMasters as $operation)
                    <option value="{{ $operation->id }}" @selected((int) old('operation_master_id', $billingRate->operation_master_id) === (int) $operation->id)>{{ $operation->name }} ({{ $operation->code }})</option>
                @endforeach
            </select>
            @error('operation_master_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Rate</label>
            <input type="number" step="0.01" min="0" name="rate" value="{{ old('rate', $billingRate->rate) }}" class="form-control @error('rate') is-invalid @enderror" required>
            @error('rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Qty Basis</label>
            <select name="qty_basis" id="qty_basis" class="form-select @error('qty_basis') is-invalid @enderror" required>
                @php
                    $selectedSource = old('source_type', $billingRate->source_type ?: 'cut_batch');
                    $basisRows = $qtyBasisOptions[$selectedSource] ?? [];
                @endphp
                @foreach($basisRows as $value => $label)
                    <option value="{{ $value }}" @selected(old('qty_basis', $billingRate->qty_basis) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('qty_basis')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Rate UOM</label>
            <select name="rate_uom_id" class="form-select @error('rate_uom_id') is-invalid @enderror">
                <option value="">Optional</option>
                @foreach($uoms as $uom)
                    <option value="{{ $uom->id }}" @selected((int) old('rate_uom_id', $billingRate->rate_uom_id) === (int) $uom->id)>{{ $uom->code }}</option>
                @endforeach
            </select>
            @error('rate_uom_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label">Description</label>
            <input type="text" name="description" value="{{ old('description', $billingRate->description) }}" class="form-control" placeholder="Shown on bill line">
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label">Remarks</label>
            <input type="text" name="remarks" value="{{ old('remarks', $billingRate->remarks) }}" class="form-control">
        </div>
        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked((bool) old('is_active', $billingRate->is_active))>
                <label class="form-check-label" for="is_active">Rate is active</label>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div class="small text-body-secondary">For operation billing, select source type `Operation` and choose the exact process. For cut, fit-up, and welding, one active rate per contractor/source is enough.</div>
                    <button type="submit" class="btn btn-primary">{{ $billingRate->exists ? 'Update Rate' : 'Create Rate' }}</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        (() => {
            const sourceEl = document.getElementById('source_type');
            const qtyBasisEl = document.getElementById('qty_basis');
            if (!sourceEl || !qtyBasisEl) {
                return;
            }

            const options = @json($qtyBasisOptions);
            const selectedValue = @json(old('qty_basis', $billingRate->qty_basis));

            const renderBasis = () => {
                const sourceType = sourceEl.value || 'cut_batch';
                const rows = options[sourceType] || {};
                const previous = qtyBasisEl.value || selectedValue || '';

                qtyBasisEl.innerHTML = '';
                Object.entries(rows).forEach(([value, label]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    if (value === previous) {
                        option.selected = true;
                    }
                    qtyBasisEl.appendChild(option);
                });

                if (!qtyBasisEl.value && qtyBasisEl.options.length > 0) {
                    qtyBasisEl.options[0].selected = true;
                }
            };

            sourceEl.addEventListener('change', renderBasis);
            renderBasis();
        })();
    </script>
@endsection
