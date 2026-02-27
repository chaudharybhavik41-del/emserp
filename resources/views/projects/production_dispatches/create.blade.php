@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-truck"></i> New Production Dispatch</h2>
            <div class="text-muted small">Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}</div>
        </div>
        <a href="{{ route('projects.production-dispatches.index', $project) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('projects.production-dispatches.store', $project) }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Dispatch Date <span class="text-danger">*</span></label>
                        <input type="date" name="dispatch_date" class="form-control"
                               value="{{ old('dispatch_date', now()->format('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Client</label>
                        <select name="client_party_id" class="form-select">
                            <option value="">(Use project client)</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}"
                                    {{ (string)old('client_party_id', $project->client_party_id) === (string)$c->id ? 'selected' : '' }}>
                                    {{ $c->name }} ({{ $c->code }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Default is Project Client.</div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Production Plan <span class="text-danger">*</span></label>
                        <select name="production_plan_id" id="production_plan_id" class="form-select" required>
                            <option value="">Select Plan...</option>
                            @foreach($plans as $p)
                                <option value="{{ $p->id }}"
                                    {{ (string)old('production_plan_id', request('production_plan_id')) === (string)$p->id ? 'selected' : '' }}>
                                    {{ $p->plan_number }} — {{ $p->plan_date?->format('Y-m-d') }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Selecting a plan will load completed, billable components.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Vehicle No</label>
                        <input type="text" name="vehicle_number" class="form-control" value="{{ old('vehicle_number') }}" maxlength="50">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">LR No</label>
                        <input type="text" name="lr_number" class="form-control" value="{{ old('lr_number') }}" maxlength="80">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Transporter</label>
                        <input type="text" name="transporter_name" class="form-control" value="{{ old('transporter_name') }}" maxlength="150">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <hr class="my-4">

                @if(!$selectedPlan)
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i>
                        Select a <strong>Production Plan</strong> to load components for dispatch.
                    </div>
                @else
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-muted small">
                            Loaded Plan: <span class="fw-semibold">{{ $selectedPlan->plan_number }}</span>
                            · Showing <strong>Completed (Done)</strong> assemblies marked <strong>Billable</strong> in BOM.
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save"></i> Create Dispatch (Draft)
                        </button>
                    </div>

                    @if($items->isEmpty())
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            No completed billable assemblies found to dispatch for this plan.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle" id="dispatch_items_table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 120px;">Assembly Mark</th>
                                        <th style="width: 120px;">Type</th>
                                        <th>Description</th>
                                        <th class="text-end" style="width: 110px;">Planned</th>
                                        <th class="text-end" style="width: 110px;">Dispatched</th>
                                        <th class="text-end" style="width: 110px;">Balance</th>
                                        <th class="text-end" style="width: 120px;">Unit Wt (kg)</th>
                                        <th class="text-end" style="width: 140px;">Dispatch Qty</th>
                                        <th class="text-end" style="width: 140px;">Dispatch Wt (kg)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $pi)
                                        @php
                                            $planned = (float)($pi->planned_qty ?? 0);
                                            $done = (float)($dispatchedQtyMap[$pi->id] ?? 0);
                                            $bal = max(0, $planned - $done);
                                            $unitW = 0.0;
                                            if ($pi->bom_item_id && isset($unitBillableWeightMap[$pi->bom_item_id])) {
                                                $unitW = (float) $unitBillableWeightMap[$pi->bom_item_id];
                                            }
                                            if ($unitW <= 0 && $planned > 0) {
                                                $unitW = (float)($pi->planned_weight_kg ?? 0) / $planned;
                                            }
                                        @endphp

                                        <tr data-unit-wt="{{ $unitW }}">
                                            <td class="fw-semibold">{{ $pi->assembly_mark }}</td>
                                            <td>{{ $pi->assembly_type }}</td>
                                            <td>{{ $pi->description }}</td>
                                            <td class="text-end">{{ number_format($planned, 3) }}</td>
                                            <td class="text-end">{{ number_format($done, 3) }}</td>
                                            <td class="text-end">{{ number_format($bal, 3) }}</td>
                                            <td class="text-end">{{ number_format($unitW, 3) }}</td>
                                            <td class="text-end">
                                                <input type="number"
                                                       name="lines[{{ $pi->id }}][qty]"
                                                       class="form-control form-control-sm text-end dispatch-qty"
                                                       step="0.001"
                                                       min="0" max="{{ $bal }}"
                                                       value="{{ old('lines.'.$pi->id.'.qty', 0) }}"
                                                       {{ $bal <= 0 ? 'disabled' : '' }}>
                                            </td>
                                            <td class="text-end">
                                                <span class="dispatch-wt">0.000</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <th colspan="8" class="text-end">Total Dispatch Weight (kg)</th>
                                        <th class="text-end"><span id="total_dispatch_wt">0.000</span></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="form-text">
                            Weight is calculated using BOM <strong>Billable</strong> flags (billable weight per assembly). Cancelled dispatches do not reduce balance.
                        </div>
                    @endif
                @endif
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const planSel = document.getElementById('production_plan_id');
    if (planSel) {
        planSel.addEventListener('change', function () {
            const pid = this.value;
            const url = new URL(window.location.href);
            if (pid) {
                url.searchParams.set('production_plan_id', pid);
            } else {
                url.searchParams.delete('production_plan_id');
            }
            window.location.href = url.toString();
        });
    }

    function recalc() {
        let total = 0;
        document.querySelectorAll('#dispatch_items_table tbody tr').forEach(tr => {
            const unit = parseFloat(tr.getAttribute('data-unit-wt') || '0') || 0;
            const qtyInput = tr.querySelector('.dispatch-qty');
            const qty = qtyInput ? (parseFloat(qtyInput.value || '0') || 0) : 0;
            const wt = unit * qty;
            const wtEl = tr.querySelector('.dispatch-wt');
            if (wtEl) wtEl.textContent = wt.toFixed(3);
            total += wt;
        });
        const totEl = document.getElementById('total_dispatch_wt');
        if (totEl) totEl.textContent = total.toFixed(3);
    }

    document.querySelectorAll('.dispatch-qty').forEach(inp => {
        inp.addEventListener('input', recalc);
    });

    recalc();
});
</script>
@endsection
