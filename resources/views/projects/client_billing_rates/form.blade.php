@extends('layouts.erp')

@section('title', 'Project Client Billing Rate')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $clientBillingRate->exists ? 'Edit' : 'Create' }} Client Billing Rate</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.client-billing-rates.index', $project) }}" class="btn btn-sm btn-outline-secondary">Back</a>
@endsection

@section('content')
    <form method="post" action="{{ $clientBillingRate->exists ? route('projects.client-billing-rates.update', [$project, $clientBillingRate]) : route('projects.client-billing-rates.store', $project) }}" class="row g-3">
        @csrf
        @if($clientBillingRate->exists)
            @method('PUT')
        @endif

        <div class="col-12 col-md-3">
            <label class="form-label">Scope</label>
            <select name="line_type" id="line_type" class="form-select @error('line_type') is-invalid @enderror" required>
                @foreach($lineTypeOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('line_type', $clientBillingRate->line_type ?: \App\Models\ProjectClientBillingRate::LINE_TYPE_GENERIC) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('line_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Source Key</label>
            <input type="text" name="source_key" id="source_key" value="{{ old('source_key', $clientBillingRate->source_key) }}" class="form-control @error('source_key') is-invalid @enderror" placeholder="Assembly / BOQ code">
            @error('source_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Rate</label>
            <input type="number" step="0.01" min="0" name="rate" value="{{ old('rate', $clientBillingRate->rate) }}" class="form-control @error('rate') is-invalid @enderror" required>
            @error('rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Rate UOM</label>
            <select name="uom_id" class="form-select @error('uom_id') is-invalid @enderror">
                <option value="">Optional</option>
                @foreach($uoms as $uom)
                    <option value="{{ $uom->id }}" @selected((int) old('uom_id', $clientBillingRate->uom_id) === (int) $uom->id)>{{ $uom->code }}</option>
                @endforeach
            </select>
            @error('uom_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 col-md-5">
            <label class="form-label">Description</label>
            <input type="text" name="description" value="{{ old('description', $clientBillingRate->description) }}" class="form-control @error('description') is-invalid @enderror" placeholder="Shown on client bill line if useful">
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Revenue Account</label>
            <select name="revenue_account_id" class="form-select @error('revenue_account_id') is-invalid @enderror">
                <option value="">Optional</option>
                @foreach($revenueAccounts as $account)
                    <option value="{{ $account->id }}" @selected((int) old('revenue_account_id', $clientBillingRate->revenue_account_id) === (int) $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                @endforeach
            </select>
            @error('revenue_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">HSN / SAC</label>
            <input type="text" name="sac_hsn_code" value="{{ old('sac_hsn_code', $clientBillingRate->sac_hsn_code) }}" class="form-control @error('sac_hsn_code') is-invalid @enderror">
            @error('sac_hsn_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 col-md-3">
            <label class="form-label">Effective From</label>
            <input type="date" name="effective_from" value="{{ old('effective_from', optional($clientBillingRate->effective_from)->format('Y-m-d')) }}" class="form-control @error('effective_from') is-invalid @enderror">
            @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Effective To</label>
            <input type="date" name="effective_to" value="{{ old('effective_to', optional($clientBillingRate->effective_to)->format('Y-m-d')) }}" class="form-control @error('effective_to') is-invalid @enderror">
            @error('effective_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Remarks</label>
            <input type="text" name="remarks" value="{{ old('remarks', $clientBillingRate->remarks) }}" class="form-control @error('remarks') is-invalid @enderror">
            @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end">
            <div class="form-check mb-2">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked((bool) old('is_active', $clientBillingRate->is_active))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div class="small text-body-secondary">
                        Use assembly-code rates for dispatch-based material billing, BOQ-code rates for service billing, scrap for scrap sale defaults, and generic for project-wide fallback.
                    </div>
                    <button type="submit" class="btn btn-primary">{{ $clientBillingRate->exists ? 'Update Rate' : 'Create Rate' }}</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        (() => {
            const lineType = document.getElementById('line_type');
            const sourceKey = document.getElementById('source_key');
            if (!lineType || !sourceKey) return;

            const syncSource = () => {
                const value = lineType.value;
                const disabled = value === 'generic' || value === 'scrap';
                sourceKey.disabled = disabled;
                if (disabled) {
                    sourceKey.value = '';
                }
                sourceKey.placeholder = value === 'assembly_code'
                    ? 'e.g. ASM-001'
                    : (value === 'boq_item_code' ? 'e.g. BOQ-01' : 'Not required');
            };

            lineType.addEventListener('change', syncSource);
            syncSource();
        })();
    </script>
@endsection
