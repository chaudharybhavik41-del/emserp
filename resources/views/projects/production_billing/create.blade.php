@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-plus-circle"></i> Generate Production Bill</h2>
            <div class="text-muted small">Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}</div>
        </div>
        <a href="{{ route('projects.production-billing.index', $project) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="alert alert-info">
        <div><b>How billing qty is calculated</b></div>
        <ul class="mb-0">
            <li>Pulls <b>Approved DPR lines</b> within the period for selected contractor and generates a <b>Draft Bill</b>.</li>
            <li>Already-billed DPR lines are excluded.</li>
            <li>If the route rate is <b>per KG / MT</b>, system converts DPR <b>Nos/PCS</b> to weight using <b>Production Plan item planned weight</b>.</li>
            <li><b>GST</b> is applied only if contractor has <b>GSTIN</b> saved in Parties. Otherwise GST is forced to <b>0</b>.</li>
        </ul>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('projects.production-billing.store', $project) }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Contractor <span class="text-danger">*</span></label>
                        <select name="contractor_party_id" class="form-select @error('contractor_party_id') is-invalid @enderror" required>
                            <option value="">— select —</option>
                            @foreach($contractors as $c)
                                <option value="{{ $c->id }}" {{ old('contractor_party_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('contractor_party_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Period From <span class="text-danger">*</span></label>
                        <input type="date" name="period_from" value="{{ old('period_from', $defaultFrom) }}"
                               class="form-control @error('period_from') is-invalid @enderror" required>
                        @error('period_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Period To <span class="text-danger">*</span></label>
                        <input type="date" name="period_to" value="{{ old('period_to', $defaultTo) }}"
                               class="form-control @error('period_to') is-invalid @enderror" required>
                        @error('period_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Bill Date</label>
                        <input type="date" name="bill_date" value="{{ old('bill_date', now()->format('Y-m-d')) }}" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">GST Type <span class="text-danger">*</span></label>
                        <select name="gst_type" class="form-select @error('gst_type') is-invalid @enderror" required>
                            <option value="cgst_sgst" {{ old('gst_type', 'cgst_sgst') === 'cgst_sgst' ? 'selected' : '' }}>CGST + SGST</option>
                            <option value="igst" {{ old('gst_type') === 'igst' ? 'selected' : '' }}>IGST</option>
                        </select>
                        @error('gst_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">GST Rate (%) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="gst_rate" value="{{ old('gst_rate', 18) }}"
                               class="form-control @error('gst_rate') is-invalid @enderror" required>
                        @error('gst_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Enter 0 if tax not applicable. If contractor has no GSTIN, system will auto-set GST to 0.</div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Remarks</label>
                        <input type="text" name="remarks" value="{{ old('remarks') }}" class="form-control" placeholder="optional">
                    </div>
                </div>

                <div class="mt-4">
                    <button class="btn btn-primary">
                        <i class="bi bi-gear"></i> Generate Bill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
