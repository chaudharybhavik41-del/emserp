@extends('layouts.erp')

@section('title', $plan->exists ? 'Edit Production V2 Cutting Plan' : 'Create Production V2 Cutting Plan')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $plan->exists ? 'Edit Production V2 Cutting Plan' : 'Create Production V2 Cutting Plan' }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.design_nav', ['project' => $project, 'active' => 'cutting_plans'])

    @php
        $oldPlates = old('planned_plates');
        if ($oldPlates === null) {
            if ($plan->exists && $plan->plannedPlates->isNotEmpty()) {
                $oldPlates = $plan->plannedPlates->map(fn ($plate) => [
                    'plate_ref' => $plate->plate_ref,
                    'planned_width_mm' => $plate->planned_width_mm,
                    'planned_length_mm' => $plate->planned_length_mm,
                    'planned_qty' => $plate->planned_qty,
                    'remarks' => $plate->remarks,
                    'allocations' => $plate->allocations->map(fn ($allocation) => [
                        'part_definition_id' => $allocation->part_definition_id,
                        'planned_qty' => $allocation->planned_qty,
                        'remarks' => $allocation->remarks,
                    ])->all(),
                ])->all();
            } else {
                $oldPlates = [[
                    'plate_ref' => '',
                    'planned_width_mm' => '',
                    'planned_length_mm' => '',
                    'remarks' => '',
                    'allocations' => [[
                        'part_definition_id' => '',
                        'planned_qty' => '',
                        'remarks' => '',
                    ]],
                ]];
            }
        }
    @endphp

    <div class="alert alert-info">
        Design cutting plan now works as `planned plate -> part allocation`.
        Define only planned plate size and qty here. Actual stock plate selection happens later in production cutting.
    </div>

    <form method="POST" action="{{ $plan->exists ? route('projects.production-v2.cutting-plans.update', ['project' => $project->id, 'cuttingPlan' => $plan->id]) : route('projects.production-v2.cutting-plans.store', ['project' => $project->id]) }}">
        @csrf
        @if($plan->exists)
            @method('PUT')
        @endif
        <input type="hidden" name="revision_no" value="{{ old('revision_no', $plan->revision_no ?: 1) }}">
        <input type="hidden" name="source_mode" value="fresh_plate">

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-2">
                        <label class="form-label">Revision</label>
                        <input class="form-control" value="R{{ old('revision_no', $plan->revision_no ?: 1) }}" readonly>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Plan Number</label>
                        <input class="form-control" value="{{ $plan->exists ? $plan->plan_number : 'Auto-generated per planned plate on save' }}" readonly>
                        <div class="form-text">Each planned plate will become its own cutting plan with a short series like `P36-001`.</div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Plan Date</label>
                        <input type="date" name="plan_date" class="form-control" value="{{ old('plan_date', $plan->plan_date?->format('Y-m-d') ?: now()->toDateString()) }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Material Item</label>
                        <select id="plan_material_item_id" name="material_item_id" class="form-select @error('material_item_id') is-invalid @enderror" data-erp-select data-placeholder="Select raw material plate item" required>
                            <option value="">Select material item</option>
                            @foreach($materialItems as $item)
                                <option value="{{ $item->id }}"
                                    data-thickness="{{ $item->thickness }}"
                                    data-grade="{{ $item->grade }}"
                                    @selected((string) old('material_item_id', $plan->material_item_id) === (string) $item->id)>
                                    {{ $item->code }} - {{ $item->name }} @if($item->thickness) ({{ rtrim(rtrim(number_format((float) $item->thickness, 3, '.', ''), '0'), '.') }} mm) @endif
                                </option>
                            @endforeach
                        </select>
                        @error('material_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Parts below are filtered by the selected raw material item.</div>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Grade</label>
                        <input id="plan_material_grade" class="form-control" value="{{ old('grade', $plan->grade) }}" readonly>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Thickness (mm)</label>
                        <input id="plan_thickness_display" class="form-control" value="{{ old('thickness_mm', $plan->thickness_mm) }}" readonly>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" data-erp-select data-hide-search="true">
                            @foreach(['draft', 'reviewed', 'approved', 'cancelled'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $plan->status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control">{{ old('remarks', $plan->remarks) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-grid gap-3" id="planned-plates-root">
            @foreach($oldPlates as $plateIndex => $plate)
                <div class="card planned-plate-card" data-plate-index="{{ $plateIndex }}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-semibold">Planned Plate <span class="plate-seq">{{ $plateIndex + 1 }}</span></span>
                            <span class="small text-body-secondary ms-2 auto-plate-ref">{{ $plate['plate_ref'] ?: 'Auto ref on save' }}</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary add-allocation-row">Add Part</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary duplicate-plate">Duplicate Plate</button>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-plate">Remove Plate</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-3">
                                <label class="form-label">Plate Ref</label>
                                <input type="text" class="form-control plate-ref" name="planned_plates[{{ $plateIndex }}][plate_ref]" value="{{ $plate['plate_ref'] ?? '' }}" placeholder="Optional, auto if blank">
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="form-label">Plate W (mm)</label>
                                <input type="number" step="0.001" min="0.001" class="form-control plate-width" name="planned_plates[{{ $plateIndex }}][planned_width_mm]" value="{{ $plate['planned_width_mm'] ?? '' }}" required>
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="form-label">Plate L (mm)</label>
                                <input type="number" step="0.001" min="0.001" class="form-control plate-length" name="planned_plates[{{ $plateIndex }}][planned_length_mm]" value="{{ $plate['planned_length_mm'] ?? '' }}" required>
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label">Plate Remarks</label>
                                <input type="text" class="form-control" name="planned_plates[{{ $plateIndex }}][remarks]" value="{{ $plate['remarks'] ?? '' }}">
                            </div>
                        </div>

                        <div class="small text-body-secondary mb-2 plate-area-summary">Allocated area will be checked against planned plate area.</div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 allocation-table">
                                <thead>
                                    <tr>
                                        <th style="width: 36%">Part</th>
                                        <th style="width: 12%">Qty</th>
                                        <th style="width: 16%">Part Size</th>
                                        <th style="width: 16%">Part Area</th>
                                        <th>Remarks</th>
                                        <th style="width: 5%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach(($plate['allocations'] ?? []) as $allocationIndex => $allocation)
                                    <tr class="allocation-row" data-allocation-index="{{ $allocationIndex }}">
                                        <td>
                                            <select name="planned_plates[{{ $plateIndex }}][allocations][{{ $allocationIndex }}][part_definition_id]" class="form-select allocation-part" data-erp-select data-placeholder="Select part">
                                                <option value="">Select Part</option>
                                                @foreach($partDefinitions as $part)
                                                    <option value="{{ $part->id }}"
                                                        data-material-item-id="{{ $part->material_item_id }}"
                                                        data-grade="{{ $part->material_grade }}"
                                                        data-thickness="{{ $part->thickness_mm }}"
                                                        data-width="{{ $part->width_mm }}"
                                                        data-length="{{ $part->length_mm }}"
                                                        data-required-qty="{{ $part->required_qty }}"
                                                        data-remaining-base="{{ $part->remaining_qty_base }}"
                                                        data-label-base="{{ $part->part_code }} - {{ $part->part_name }}"
                                                        @selected((string)($allocation['part_definition_id'] ?? '') === (string)$part->id)>
                                                        {{ $part->part_code }} - {{ $part->part_name }}, {{ rtrim(rtrim(number_format((float) $part->width_mm, 3, '.', ''), '0'), '.') }} x {{ rtrim(rtrim(number_format((float) $part->length_mm, 3, '.', ''), '0'), '.') }} mm = {{ rtrim(rtrim(number_format((float) $part->remaining_qty_base, 3, '.', ''), '0'), '.') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input type="number" step="0.001" min="0.001" class="form-control allocation-qty" name="planned_plates[{{ $plateIndex }}][allocations][{{ $allocationIndex }}][planned_qty]" value="{{ $allocation['planned_qty'] ?? '' }}" required></td>
                                        <td class="allocation-size text-body-secondary">-</td>
                                        <td class="allocation-area text-body-secondary">-</td>
                                        <td><input type="text" class="form-control" name="planned_plates[{{ $plateIndex }}][allocations][{{ $allocationIndex }}][remarks]" value="{{ $allocation['remarks'] ?? '' }}"></td>
                                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-allocation">&times;</button></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card mt-3">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-2">
                <button type="button" class="btn btn-outline-primary" id="add-plate-row">
                    <i class="bi bi-plus-circle me-1"></i>Add Planned Plate
                </button>
                <div class="d-flex gap-2">
                    <a href="{{ route('projects.production-v2.cutting-plans.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">{{ $plan->exists ? 'Update Cutting Plan' : 'Create Cutting Plan' }}</button>
                </div>
            </div>
        </div>
    </form>

    <template id="planned-plate-template">
        <div class="card planned-plate-card" data-plate-index="__PLATE_INDEX__">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold">Planned Plate <span class="plate-seq">__PLATE_SEQ__</span></span>
                    <span class="small text-body-secondary ms-2 auto-plate-ref">Auto ref on save</span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary add-allocation-row">Add Part</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary duplicate-plate">Duplicate Plate</button>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-plate">Remove Plate</button>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-3"><label class="form-label">Plate Ref</label><input type="text" class="form-control plate-ref" name="planned_plates[__PLATE_INDEX__][plate_ref]" placeholder="Optional, auto if blank"></div>
                    <div class="col-12 col-md-2"><label class="form-label">Plate W (mm)</label><input type="number" step="0.001" min="0.001" class="form-control plate-width" name="planned_plates[__PLATE_INDEX__][planned_width_mm]" required></div>
                    <div class="col-12 col-md-2"><label class="form-label">Plate L (mm)</label><input type="number" step="0.001" min="0.001" class="form-control plate-length" name="planned_plates[__PLATE_INDEX__][planned_length_mm]" required></div>
                    <div class="col-12 col-md-5"><label class="form-label">Plate Remarks</label><input type="text" class="form-control" name="planned_plates[__PLATE_INDEX__][remarks]"></div>
                </div>
                <div class="small text-body-secondary mb-2 plate-area-summary">Allocated area will be checked against planned plate area.</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 allocation-table">
                        <thead>
                            <tr>
                                <th style="width: 36%">Part</th>
                                <th style="width: 12%">Qty</th>
                                <th style="width: 16%">Part Size</th>
                                <th style="width: 16%">Part Area</th>
                                <th>Remarks</th>
                                <th style="width: 5%"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </template>

    <template id="allocation-row-template">
        <tr class="allocation-row" data-allocation-index="__ALLOC_INDEX__">
            <td>
                <select class="form-select allocation-part" data-erp-select data-placeholder="Select part">
                    <option value="">Select Part</option>
                    @foreach($partDefinitions as $part)
                        <option value="{{ $part->id }}"
                            data-material-item-id="{{ $part->material_item_id }}"
                            data-grade="{{ $part->material_grade }}"
                            data-thickness="{{ $part->thickness_mm }}"
                            data-width="{{ $part->width_mm }}"
                            data-length="{{ $part->length_mm }}"
                            data-required-qty="{{ $part->required_qty }}"
                            data-remaining-base="{{ $part->remaining_qty_base }}"
                            data-label-base="{{ $part->part_code }} - {{ $part->part_name }}">
                            {{ $part->part_code }} - {{ $part->part_name }}, {{ rtrim(rtrim(number_format((float) $part->width_mm, 3, '.', ''), '0'), '.') }} x {{ rtrim(rtrim(number_format((float) $part->length_mm, 3, '.', ''), '0'), '.') }} mm = {{ rtrim(rtrim(number_format((float) $part->remaining_qty_base, 3, '.', ''), '0'), '.') }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" step="0.001" min="0.001" class="form-control allocation-qty" required></td>
            <td class="allocation-size text-body-secondary">-</td>
            <td class="allocation-area text-body-secondary">-</td>
            <td><input type="text" class="form-control allocation-remarks"></td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-allocation">&times;</button></td>
        </tr>
    </template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('planned-plates-root');
    const plateTemplate = document.getElementById('planned-plate-template');
    const allocationTemplate = document.getElementById('allocation-row-template');
    const addPlateButton = document.getElementById('add-plate-row');
    const materialItemSelect = document.getElementById('plan_material_item_id');
    const materialGradeInput = document.getElementById('plan_material_grade');
    const thicknessDisplayInput = document.getElementById('plan_thickness_display');
    const $ = window.jQuery;

    const formatNumber = (value) => {
        const n = parseFloat(value);
        return Number.isFinite(n) ? n.toFixed(3).replace(/\.?0+$/, '') : '-';
    };

    const sameThickness = (left, right) => {
        const a = parseFloat(left || '0');
        const b = parseFloat(right || '0');
        if (!Number.isFinite(a) || !Number.isFinite(b) || a <= 0 || b <= 0) {
            return false;
        }
        return Math.abs(a - b) < 0.0001;
    };

    const normalizedText = (value) => String(value || '').trim().toUpperCase();

    const selectedMaterialOption = () => materialItemSelect?.options[materialItemSelect.selectedIndex] || null;

    const currentMaterialProfile = () => {
        const option = selectedMaterialOption();
        return {
            itemId: option?.value || '',
            grade: option?.dataset.grade || '',
            thickness: option?.dataset.thickness || '',
        };
    };

    const syncMaterialDisplay = () => {
        const profile = currentMaterialProfile();
        if (materialGradeInput) {
            materialGradeInput.value = profile.grade || '';
        }
        if (thicknessDisplayInput) {
            thicknessDisplayInput.value = profile.thickness || '';
        }
    };

    const reinitEnhancedSelect = (select) => {
        if (!select || !window.ERPUI || typeof window.ERPUI.initSelect2 !== 'function' || !$ || !$.fn?.select2) {
            return;
        }

        const $select = $(select);
        if ($select.data('select2')) {
            $select.select2('destroy');
        }

        window.ERPUI.initSelect2(select.parentElement || select);
    };

    const filterAllocationSelect = (select) => {
        if (!select) return;

        const profile = currentMaterialProfile();
        const currentValue = select.value;
        let hasCurrent = false;

        [...select.options].forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const sameItem = profile.itemId !== '' && String(option.dataset.materialItemId || '') === String(profile.itemId);
            const sameGrade = normalizedText(option.dataset.grade) !== '' && normalizedText(profile.grade) !== ''
                ? normalizedText(option.dataset.grade) === normalizedText(profile.grade)
                : true;
            const allowed = profile.itemId === ''
                ? true
                : sameItem || (sameThickness(option.dataset.thickness, profile.thickness) && sameGrade);

            option.hidden = !allowed;
            option.disabled = !allowed;

            if (allowed && option.value === currentValue) {
                hasCurrent = true;
            }
        });

        if (currentValue && !hasCurrent) {
            select.value = '';
        }

        reinitEnhancedSelect(select);
    };

    const filterAllAllocationSelects = () => {
        root.querySelectorAll('.allocation-part').forEach((select) => {
            filterAllocationSelect(select);
        });
    };

    const allocatedQtyInForm = () => {
        const totals = {};

        root.querySelectorAll('.allocation-row').forEach((row) => {
            const select = row.querySelector('.allocation-part');
            const partId = select?.value;
            const qty = parseFloat(row.querySelector('.allocation-qty')?.value || '0');

            if (!partId || !Number.isFinite(qty) || qty <= 0) {
                return;
            }

            totals[partId] = (totals[partId] || 0) + qty;
        });

        return totals;
    };

    const refreshOptionLabels = () => {
        const inFormTotals = allocatedQtyInForm();

        root.querySelectorAll('.allocation-row').forEach((row) => {
            const currentPartId = row.querySelector('.allocation-part')?.value || '';
            const currentQty = parseFloat(row.querySelector('.allocation-qty')?.value || '0');

            row.querySelectorAll('.allocation-part option').forEach((option) => {
                if (!option.value) {
                    return;
                }

                const labelBase = option.dataset.labelBase || option.textContent;
                const baseRemaining = parseFloat(option.dataset.remainingBase || '0');
                const totalInForm = parseFloat(inFormTotals[option.value] || '0');
                const rowContribution = option.value === currentPartId && Number.isFinite(currentQty) ? currentQty : 0;
                const remaining = Math.max(baseRemaining - (totalInForm - rowContribution), 0);
                const width = parseFloat(option.dataset.width || '0');
                const length = parseFloat(option.dataset.length || '0');
                const sizeLabel = width && length ? `${formatNumber(width)} x ${formatNumber(length)} mm` : '';

                option.textContent = sizeLabel !== ''
                    ? `${labelBase}, ${sizeLabel} = ${formatNumber(remaining)}`
                    : `${labelBase} = ${formatNumber(remaining)}`;
            });

            reinitEnhancedSelect(row.querySelector('.allocation-part'));
        });
    };

    const renumber = () => {
        [...root.querySelectorAll('.planned-plate-card')].forEach((card, plateIndex) => {
            card.dataset.plateIndex = plateIndex;
            card.querySelector('.plate-seq').textContent = plateIndex + 1;
            card.querySelectorAll('input, select').forEach((field) => {
                const name = field.getAttribute('name');
                if (!name) return;
                field.setAttribute('name', name.replace(/planned_plates\[\d+]/, `planned_plates[${plateIndex}]`).replace(/allocations\[\d+]/, () => {
                    const row = field.closest('.allocation-row');
                    const allocIndex = row ? [...row.parentElement.children].indexOf(row) : 0;
                    return `allocations[${allocIndex}]`;
                }));
            });
            updatePlateSummary(card);
        });
        filterAllAllocationSelects();
        refreshOptionLabels();
    };

    const selectedPartOption = (row) => row.querySelector('.allocation-part option:checked');

    const updateRowMeta = (row) => {
        const option = selectedPartOption(row);
        const qty = parseFloat(row.querySelector('.allocation-qty')?.value || '0');
        const width = parseFloat(option?.dataset.width || '0');
        const length = parseFloat(option?.dataset.length || '0');
        const area = width * length * qty;
        row.querySelector('.allocation-size').textContent = width && length ? `${formatNumber(width)} x ${formatNumber(length)} mm` : '-';
        row.querySelector('.allocation-area').textContent = area ? `${formatNumber(area / 1000000)} m2` : '-';
    };

    const updatePlateSummary = (card) => {
        const width = parseFloat(card.querySelector('.plate-width')?.value || '0');
        const length = parseFloat(card.querySelector('.plate-length')?.value || '0');
        const plateArea = width * length;
        let allocatedArea = 0;

        card.querySelectorAll('.allocation-row').forEach((row) => {
            updateRowMeta(row);
            const option = selectedPartOption(row);
            const partWidth = parseFloat(option?.dataset.width || '0');
            const partLength = parseFloat(option?.dataset.length || '0');
            const partQty = parseFloat(row.querySelector('.allocation-qty')?.value || '0');
            allocatedArea += partWidth * partLength * partQty;
        });

        const summary = card.querySelector('.plate-area-summary');
        if (summary) {
            summary.textContent = `Planned area: ${formatNumber(plateArea / 1000000)} m2 | Allocated area: ${formatNumber(allocatedArea / 1000000)} m2`;
            summary.classList.toggle('text-danger', allocatedArea > plateArea + 0.0001);
        }

        const plateRef = card.querySelector('.plate-ref')?.value?.trim();
        const autoRef = card.querySelector('.auto-plate-ref');
        if (autoRef) {
            autoRef.textContent = plateRef !== '' ? plateRef : 'Auto ref on save';
        }
    };

    const addAllocationRow = (card) => {
        const tbody = card.querySelector('.allocation-table tbody');
        const allocIndex = tbody.querySelectorAll('.allocation-row').length;
        const plateIndex = card.dataset.plateIndex || 0;
        const html = allocationTemplate.innerHTML.replaceAll('__ALLOC_INDEX__', allocIndex);
        const temp = document.createElement('tbody');
        temp.innerHTML = html.trim();
        const row = temp.firstElementChild;
        row.querySelector('.allocation-part').setAttribute('name', `planned_plates[${plateIndex}][allocations][${allocIndex}][part_definition_id]`);
        row.querySelector('.allocation-qty').setAttribute('name', `planned_plates[${plateIndex}][allocations][${allocIndex}][planned_qty]`);
        row.querySelector('.allocation-remarks').setAttribute('name', `planned_plates[${plateIndex}][allocations][${allocIndex}][remarks]`);
        tbody.appendChild(row);
        filterAllocationSelect(row.querySelector('.allocation-part'));
        window.ERPUI?.initSelect2?.(row);
        renumber();
    };

    const duplicatePlateCard = (card) => {
        const plateIndex = root.querySelectorAll('.planned-plate-card').length;
        const html = plateTemplate.innerHTML
            .replaceAll('__PLATE_INDEX__', plateIndex)
            .replaceAll('__PLATE_SEQ__', plateIndex + 1);
        const temp = document.createElement('div');
        temp.innerHTML = html.trim();
        const cloneCard = temp.firstElementChild;

        cloneCard.querySelector('.plate-ref').value = '';
        cloneCard.querySelector('.plate-width').value = card.querySelector('.plate-width')?.value || '';
        cloneCard.querySelector('.plate-length').value = card.querySelector('.plate-length')?.value || '';
        const sourcePlateRemarks = card.querySelector('.row.g-3.mb-3 input[type="text"]:not(.plate-ref)');
        const targetPlateRemarks = cloneCard.querySelector('.row.g-3.mb-3 input[type="text"]:not(.plate-ref)');
        if (targetPlateRemarks) {
            targetPlateRemarks.value = sourcePlateRemarks?.value || '';
        }

        root.appendChild(cloneCard);

        const sourceRows = [...card.querySelectorAll('.allocation-row')];
        sourceRows.forEach((sourceRow, index) => {
            if (index === 0) {
                addAllocationRow(cloneCard);
            } else {
                addAllocationRow(cloneCard);
            }
            const targetRow = cloneCard.querySelectorAll('.allocation-row')[index];
            const sourceSelect = sourceRow.querySelector('.allocation-part');
            const targetSelect = targetRow.querySelector('.allocation-part');
            targetSelect.value = sourceSelect?.value || '';
            targetRow.querySelector('.allocation-qty').value = sourceRow.querySelector('.allocation-qty')?.value || '';
            targetRow.querySelector('.allocation-remarks').value = sourceRow.querySelector('.form-control:not(.allocation-qty)')?.value || '';
            updateRowMeta(targetRow);
        });

        renumber();
    };

    addPlateButton?.addEventListener('click', () => {
        const plateIndex = root.querySelectorAll('.planned-plate-card').length;
        const html = plateTemplate.innerHTML
            .replaceAll('__PLATE_INDEX__', plateIndex)
            .replaceAll('__PLATE_SEQ__', plateIndex + 1);
        const temp = document.createElement('div');
        temp.innerHTML = html.trim();
        const card = temp.firstElementChild;
        root.appendChild(card);
        addAllocationRow(card);
        renumber();
    });

    root.addEventListener('click', (event) => {
        const plateCard = event.target.closest('.planned-plate-card');
        if (!plateCard) return;

        if (event.target.closest('.add-allocation-row')) {
            addAllocationRow(plateCard);
            return;
        }

        if (event.target.closest('.duplicate-plate')) {
            duplicatePlateCard(plateCard);
            return;
        }

        if (event.target.closest('.remove-allocation')) {
            const rows = plateCard.querySelectorAll('.allocation-row');
            if (rows.length > 1) {
                event.target.closest('.allocation-row')?.remove();
                renumber();
            }
            return;
        }

        if (event.target.closest('.remove-plate')) {
            const cards = root.querySelectorAll('.planned-plate-card');
            if (cards.length > 1) {
                plateCard.remove();
                renumber();
            }
        }
    });

    root.addEventListener('input', (event) => {
        const card = event.target.closest('.planned-plate-card');
        if (card) updatePlateSummary(card);
    });

    root.addEventListener('change', (event) => {
        if (event.target.matches('.allocation-part')) {
            const option = event.target.options[event.target.selectedIndex];
            const profile = currentMaterialProfile();
            const sameItem = profile.itemId !== '' && String(option?.dataset.materialItemId || '') === String(profile.itemId);
            const sameGrade = normalizedText(option?.dataset.grade) !== '' && normalizedText(profile.grade) !== ''
                ? normalizedText(option?.dataset.grade) === normalizedText(profile.grade)
                : true;

            if (option?.value && !sameItem && (!sameThickness(option?.dataset.thickness, profile.thickness) || !sameGrade)) {
                alert('Selected part does not match the selected material item.');
                event.target.value = '';
            }

            const card = event.target.closest('.planned-plate-card');
            if (card) updatePlateSummary(card);
        }
    });

    materialItemSelect?.addEventListener('change', () => {
        syncMaterialDisplay();
        filterAllAllocationSelects();
        refreshOptionLabels();
        root.querySelectorAll('.planned-plate-card').forEach((card) => updatePlateSummary(card));
    });

    syncMaterialDisplay();
    renumber();
});
</script>
@endpush
