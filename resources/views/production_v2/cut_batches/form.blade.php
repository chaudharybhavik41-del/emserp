@extends('layouts.erp')

@section('title', 'Create Production V2 Cut Batch')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Create Production V2 Cut Batch</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @php
        $draftPlanCount = (int) ($planStatusCounts['draft'] ?? 0);
        $approvedPlanCount = (int) ($planStatusCounts['approved'] ?? 0);
        $releasedPlanCount = (int) ($planStatusCounts['released'] ?? 0);
        $hasUnreleasedPlans = ($draftPlanCount + $approvedPlanCount) > 0;
    @endphp

    @if($releasedPlanCount === 0 && $hasUnreleasedPlans)
        <div class="alert alert-warning">
            No released cutting plans are available for production yet.
            Current plan status in this project:
            <strong>{{ $draftPlanCount }}</strong> draft,
            <strong>{{ $approvedPlanCount }}</strong> approved,
            <strong>{{ $releasedPlanCount }}</strong> released.
            Release the required cutting plans from Design V2 before creating the cut batch.
            <div class="mt-2 d-flex flex-wrap gap-2">
                <a href="{{ route('projects.production-v2.cutting-plans.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">Open Cutting Plans</a>
                <a href="{{ route('projects.production-v2.design-releases.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-dark">Open Design Releases</a>
            </div>
        </div>
    @elseif($releasedPlanCount > 0 && $hasUnreleasedPlans)
        <div class="alert alert-light border">
            Only released cutting plans are shown here.
            This project still has <strong>{{ $draftPlanCount }}</strong> draft and <strong>{{ $approvedPlanCount }}</strong> approved plans waiting for release.
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end">
                @if($selectedDpr)
                    <input type="hidden" name="dpr_id" value="{{ $selectedDpr->id }}">
                @endif
                <div class="col-12 col-md-8">
                    <label class="form-label">Cutting Plan</label>
                    <select name="cutting_plan_id" class="form-select" data-erp-select onchange="this.form.submit()">
                        <option value="">Select Cutting Plan</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((int) request('cutting_plan_id') === (int) $plan->id)>
                                {{ $plan->plan_number }} / {{ $plan->grade ?: '-' }} / {{ $plan->thickness_mm ?: '-' }}mm
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Production cutting can load only released plans from this project.
                    </div>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100">Load Plan</button>
                    <a href="{{ request()->url() }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    @if($selectedPlan)
        @php
            $selectedPlanCategory = $selectedPlan->allocations
                ->pluck('partDefinition.material_category')
                ->filter()
                ->map(fn ($value) => strtolower((string) $value))
                ->first();
            $isSectionPlan = $selectedPlanCategory === 'steel_section';
            $motherStockLabel = $isSectionPlan ? 'Mother Section' : 'Mother Stock';
            $motherStockPlaceholder = $isSectionPlan ? 'Select Mother Section' : 'Select Mother Stock';
            $cutSizeLabel = $isSectionPlan ? 'Section Length' : 'Cut Size';
            $sourceUnitLabel = $isSectionPlan ? 'section stock' : 'mother stock';
            $remnantWidthLabel = $isSectionPlan ? null : 'Width (mm)';
            $remnantLengthLabel = $isSectionPlan ? 'Section Length (mm)' : 'Length (mm)';
        @endphp
        <form method="POST" action="{{ route('projects.production-v2.cut-batches.store', ['project' => $project->id]) }}" data-pwa-sync="critical">
            @csrf
            <input type="hidden" name="dpr_id" value="{{ old('dpr_id', $selectedDpr?->id) }}">
            <input type="hidden" name="cutting_plan_id" value="{{ $selectedPlan->id }}">

            @if($selectedDpr)
                <div class="alert alert-primary mb-3">
                    Using DPR-{{ $selectedDpr->id }} from {{ $selectedDpr->dpr_date?->format('Y-m-d') ?: '-' }}.
                    {{ $selectedDpr->worker?->name ? 'Worker: ' . $selectedDpr->worker->name . '.' : '' }}
                    {{ $selectedDpr->machine?->name ? 'Machine: ' . $selectedDpr->machine->name . '.' : '' }}
                    <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $selectedDpr->id]) }}" class="alert-link">Open DPR</a>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label">Cut Date</label>
                            <input type="date" name="cut_date" class="form-control" value="{{ old('cut_date', $selectedDpr?->dpr_date?->format('Y-m-d') ?: now()->toDateString()) }}" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">{{ $motherStockLabel }}</label>
                            <select name="mother_stock_item_id" class="form-select" data-erp-select required>
                                <option value="">{{ $motherStockPlaceholder }}</option>
                                @foreach($stockItems as $stock)
                                    <option value="{{ $stock->id }}" @selected((string) old('mother_stock_item_id') === (string) $stock->id)>
                                        {{ $stock->plate_number ?: ($stock->section_profile ?: ('Stock #' . $stock->id)) }} / {{ $stock->heat_number ?: '-' }} / {{ $stock->grade ?: '-' }} / {{ $stock->thickness_mm ?: '-' }}mm{{ $stock->is_remnant ? ' / REMNANT' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Choose matching {{ $sourceUnitLabel }} for this plan’s grade and thickness.
                                @if($selectedPlanProfile)
                                    Showing {{ $stockItems->count() }} matching stock row(s) from current store availability.
                                @endif
                            </div>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Machine</label>
                            <select name="machine_id" class="form-select" data-erp-select data-allow-clear="1" data-placeholder="Machine">
                                <option value="">Machine</option>
                                @foreach($machines as $machine)
                                    <option value="{{ $machine->id }}" @selected((string) old('machine_id', $selectedDpr?->machine_id) === (string) $machine->id)>{{ $machine->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Contractor</label>
                            <select name="contractor_party_id" class="form-select" data-erp-select data-allow-clear="1" data-placeholder="Contractor">
                                <option value="">Contractor</option>
                                @foreach($contractors as $contractor)
                                    <option value="{{ $contractor->id }}" @selected((string) old('contractor_party_id', $selectedDpr?->contractor_party_id) === (string) $contractor->id)>{{ $contractor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Operator</label>
                            <select name="operator_id" class="form-select" data-erp-select data-allow-clear="1" data-placeholder="Operator">
                                <option value="">Operator</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('operator_id', $selectedDpr?->worker_user_id) === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Shift</label>
                            <input name="shift" class="form-control" value="{{ old('shift', $selectedDpr?->shift) }}">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" data-erp-select data-hide-search="true">
                                <option value="draft" @selected(old('status', 'approved') === 'draft')>Draft</option>
                                <option value="approved" @selected(old('status', 'approved') === 'approved')>Approved</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" rows="2" class="form-control">{{ old('remarks') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-light border mb-3 d-md-none">
                <div class="fw-semibold small mb-1">Mobile Entry</div>
                <div class="small text-body-secondary">Rows stack as cards on phones. Tap a field, use the quick buttons, and submit without horizontal scrolling.</div>
            </div>

            <div class="card">
                <div class="card-header">Batch Output Rows</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 mobile-entry-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Part</th>
                                    <th class="text-end">Planned Qty</th>
                                    <th>{{ $cutSizeLabel }}</th>
                                    <th class="text-end">Produced Qty</th>
                                    <th>Mode</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($selectedPlan->allocations as $index => $allocation)
                                <tr>
                                    <td data-label="Part">
                                        {{ $allocation->partDefinition?->part_code }}
                                        <div class="small text-body-secondary">{{ $allocation->partDefinition?->part_name }}</div>
                                        <input type="hidden" name="rows[{{ $index }}][allocation_id]" value="{{ $allocation->id }}">
                                    </td>
                                    <td class="text-end" data-label="Planned Qty">{{ number_format((float) $allocation->planned_qty, 3) }}</td>
                                    <td data-label="{{ $cutSizeLabel }}">{{ $allocation->cut_size_text ?: '-' }}</td>
                                    <td class="text-end" data-label="Produced Qty">
                                        <input type="number" step="0.001" min="0.001" inputmode="decimal" name="rows[{{ $index }}][produced_qty]" class="form-control form-control-sm text-end" value="{{ old('rows.' . $index . '.produced_qty', $allocation->planned_qty) }}">
                                    </td>
                                    <td data-label="Mode">
                                        <select name="rows[{{ $index }}][mode]" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                                            <option value="piece" @selected(old('rows.' . $index . '.mode', ((float)$allocation->planned_qty <= 1 ? 'piece' : 'lot')) === 'piece')>Piece</option>
                                            <option value="lot" @selected(old('rows.' . $index . '.mode', ((float)$allocation->planned_qty <= 1 ? 'piece' : 'lot')) === 'lot')>Lot</option>
                                        </select>
                                    </td>
                                    <td data-label="Remarks"><input name="rows[{{ $index }}][remarks]" class="form-control form-control-sm" value="{{ old('rows.' . $index . '.remarks') }}"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @php
                    $oldRemnants = old('batch_remnants', []);
                    $remnants = count($oldRemnants) > 0
                        ? $oldRemnants
                        : [
                            ['width_mm' => '', 'length_mm' => '', 'weight_kg' => '', 'is_usable' => '1', 'remarks' => ''],
                            ['width_mm' => '', 'length_mm' => '', 'weight_kg' => '', 'is_usable' => '1', 'remarks' => ''],
                            ['width_mm' => '', 'length_mm' => '', 'weight_kg' => '', 'is_usable' => '0', 'remarks' => ''],
                        ];
                @endphp
                <div class="card-header border-top">Remnants / Scrap</div>
                <div class="card-body p-0">
                    <div class="px-3 pt-3 pb-2 border-bottom bg-body-tertiary">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary js-cut-batch-action" data-action="planned">Use Planned Qty</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-cut-batch-action" data-action="piece">All Piece</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-cut-batch-action" data-action="lot">All Lot</button>
                            <button type="button" class="btn btn-sm btn-outline-danger js-cut-batch-action" data-action="clear-remnants">Clear Remnants</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 mobile-entry-table">
                            <thead class="table-light">
                                <tr>
                                    @if(!$isSectionPlan)
                                        <th>{{ $remnantWidthLabel }}</th>
                                    @endif
                                    <th>{{ $remnantLengthLabel }}</th>
                                    <th>Weight (kg)</th>
                                    <th>Usable</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($remnants as $index => $row)
                                <tr>
                                    @if(!$isSectionPlan)
                                        <td data-label="{{ $remnantWidthLabel }}"><input type="number" min="0" inputmode="decimal" name="batch_remnants[{{ $index }}][width_mm]" class="form-control form-control-sm" value="{{ $row['width_mm'] ?? '' }}"></td>
                                    @endif
                                    <td data-label="{{ $remnantLengthLabel }}">
                                        @if($isSectionPlan)
                                            <input type="hidden" name="batch_remnants[{{ $index }}][width_mm]" value="">
                                        @endif
                                        <input type="number" min="0" inputmode="decimal" name="batch_remnants[{{ $index }}][length_mm]" class="form-control form-control-sm" value="{{ $row['length_mm'] ?? '' }}">
                                    </td>
                                    <td data-label="Weight (kg)"><input type="number" step="0.001" min="0" inputmode="decimal" name="batch_remnants[{{ $index }}][weight_kg]" class="form-control form-control-sm" value="{{ $row['weight_kg'] ?? '' }}"></td>
                                    <td data-label="Usable">
                                        <select name="batch_remnants[{{ $index }}][is_usable]" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                                            <option value="1" @selected((string) ($row['is_usable'] ?? '1') === '1')>Yes</option>
                                            <option value="0" @selected((string) ($row['is_usable'] ?? '1') === '0')>No</option>
                                        </select>
                                    </td>
                                    <td data-label="Remarks"><input name="batch_remnants[{{ $index }}][remarks]" class="form-control form-control-sm" value="{{ $row['remarks'] ?? '' }}"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-3 pb-3">
                        <div class="form-text">
                            @if($isSectionPlan)
                                Section remnants are tracked by usable length and weight. Width is hidden because it is not a shop-floor input for section stock.
                            @else
                                Plate remnants are tracked by width, length, and weight so reusable offcuts can return to stock accurately.
                            @endif
                        </div>
                    </div>
                </div>
                <div class="sticky-bottom bg-body py-2 border-top">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('projects.production-v2.cut-batches.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Cut Batch</button>
                    </div>
                </div>
            </div>
        </form>
    @else
        <div class="alert alert-info">
            @if($releasedPlanCount > 0)
                Select a released cutting plan first.
            @else
                No released cutting plans are available for this project yet.
            @endif
        </div>
    @endif
@endsection

@push('styles')
<style>
@media (max-width: 767.98px) {
    .mobile-entry-table thead {
        display: none;
    }

    .mobile-entry-table,
    .mobile-entry-table tbody,
    .mobile-entry-table tr,
    .mobile-entry-table td {
        display: block;
        width: 100%;
    }

    .mobile-entry-table tr {
        border-bottom: 1px solid var(--bs-border-color);
        padding: 0.75rem 0;
    }

    .mobile-entry-table td {
        border: 0;
        padding: 0.35rem 0.75rem;
    }

    .mobile-entry-table td::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
    }

    .mobile-entry-table td.text-end {
        text-align: left !important;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-cut-batch-action').forEach(function (button) {
        button.addEventListener('click', function () {
            const action = button.dataset.action;

            if (action === 'planned') {
                document.querySelectorAll('input[name^="rows["][name$="[produced_qty]"]').forEach(function (input) {
                    const row = input.closest('tr');
                    const plannedCell = row?.querySelector('td:nth-child(2)');
                    const plannedValue = plannedCell ? parseFloat((plannedCell.textContent || '').replace(/,/g, '').trim()) : null;
                    if (Number.isFinite(plannedValue)) {
                        input.value = plannedValue.toFixed(3).replace(/\.000$/, '');
                    }
                });
            }

            if (action === 'piece' || action === 'lot') {
                document.querySelectorAll('select[name^="rows["][name$="[mode]"]').forEach(function (select) {
                    select.value = action;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }

            if (action === 'planned') {
                document.querySelectorAll('select[name^="rows["][name$="[mode]"]').forEach(function (select) {
                    const row = select.closest('tr');
                    const qtyInput = row?.querySelector('input[name^="rows["][name$="[produced_qty]"]');
                    const numericQty = qtyInput ? parseFloat(qtyInput.value || '0') : 0;
                    select.value = numericQty <= 1 ? 'piece' : 'lot';
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }

            if (action === 'clear-remnants') {
                document.querySelectorAll('input[name^="batch_remnants["]').forEach(function (input) {
                    if (input.type !== 'hidden') {
                        input.value = '';
                    }
                });
                document.querySelectorAll('select[name^="batch_remnants["][name$="[is_usable]"]').forEach(function (select) {
                    select.value = '0';
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
        });
    });
});
</script>
@endpush
