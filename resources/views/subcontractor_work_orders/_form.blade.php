@php
    $editing = isset($subcontractorWorkOrder) && $subcontractorWorkOrder->exists;
    $action = $editing
        ? route('accounting.subcontractor-work-orders.update', $subcontractorWorkOrder)
        : route('accounting.subcontractor-work-orders.store');
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if($editing)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Work Order Number</label>
            <input type="text" name="work_order_number" class="form-control form-control-sm @error('work_order_number') is-invalid @enderror"
                   value="{{ old('work_order_number', $subcontractorWorkOrder->work_order_number ?? ($nextWorkOrderNumber ?? '')) }}" required>
            @error('work_order_number')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Work Order Date</label>
            <input type="date" name="work_order_date" class="form-control form-control-sm @error('work_order_date') is-invalid @enderror"
                   value="{{ old('work_order_date', optional($subcontractorWorkOrder->work_order_date ?? null)->format('Y-m-d') ?? now()->toDateString()) }}" required>
            @error('work_order_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Subcontractor</label>
            <select name="subcontractor_id" class="form-select form-select-sm @error('subcontractor_id') is-invalid @enderror" required>
                <option value="">-- Select --</option>
                @foreach($subcontractors as $subcontractor)
                    <option value="{{ $subcontractor->id }}" @selected((string) old('subcontractor_id', $subcontractorWorkOrder->subcontractor_id ?? '') === (string) $subcontractor->id)>
                        {{ $subcontractor->name }}
                    </option>
                @endforeach
            </select>
            @error('subcontractor_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Project</label>
            <select name="project_id" class="form-select form-select-sm @error('project_id') is-invalid @enderror" required>
                <option value="">-- Select --</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected((string) old('project_id', $subcontractorWorkOrder->project_id ?? '') === (string) $project->id)>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
            @error('project_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control form-control-sm @error('start_date') is-invalid @enderror"
                   value="{{ old('start_date', optional($subcontractorWorkOrder->start_date ?? null)->format('Y-m-d')) }}">
            @error('start_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control form-control-sm @error('end_date') is-invalid @enderror"
                   value="{{ old('end_date', optional($subcontractorWorkOrder->end_date ?? null)->format('Y-m-d')) }}">
            @error('end_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-2">
            <label class="form-label">Payment Terms (Days)</label>
            <input type="number" min="0" name="payment_terms_days" class="form-control form-control-sm @error('payment_terms_days') is-invalid @enderror"
                   value="{{ old('payment_terms_days', $subcontractorWorkOrder->payment_terms_days ?? '') }}">
            @error('payment_terms_days')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-2">
            <label class="form-label">Retention %</label>
            <input type="number" step="0.0001" min="0" name="retention_percent" class="form-control form-control-sm @error('retention_percent') is-invalid @enderror"
                   value="{{ old('retention_percent', $subcontractorWorkOrder->retention_percent ?? 0) }}">
            @error('retention_percent')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-2">
            <label class="form-label">Security Deposit %</label>
            <input type="number" step="0.0001" min="0" name="security_deposit_percent" class="form-control form-control-sm @error('security_deposit_percent') is-invalid @enderror"
                   value="{{ old('security_deposit_percent', $subcontractorWorkOrder->security_deposit_percent ?? 0) }}">
            @error('security_deposit_percent')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm @error('status') is-invalid @enderror" required>
                @foreach(['draft' => 'Draft', 'active' => 'Active', 'closed' => 'Closed', 'cancelled' => 'Cancelled'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $subcontractorWorkOrder->status ?? 'active') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-12">
            <label class="form-label">Other Terms</label>
            <textarea name="other_terms" rows="3" class="form-control form-control-sm @error('other_terms') is-invalid @enderror">{{ old('other_terms', $subcontractorWorkOrder->other_terms ?? '') }}</textarea>
            @error('other_terms')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-12">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" rows="2" class="form-control form-control-sm @error('remarks') is-invalid @enderror">{{ old('remarks', $subcontractorWorkOrder->remarks ?? '') }}</textarea>
            @error('remarks')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary btn-sm">{{ $editing ? 'Update Work Order' : 'Create Work Order' }}</button>
        <a href="{{ route('accounting.subcontractor-work-orders.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
    </div>
</form>
