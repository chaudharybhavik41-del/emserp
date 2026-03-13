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

    <div class="alert alert-info">
        Cutting plans are design-controlled nesting inputs. Group only compatible grade and thickness parts here, then release the plan for production cutting without mixing shop-floor execution data.
    </div>

    <form method="POST" action="{{ $plan->exists ? route('projects.production-v2.cutting-plans.update', ['project' => $project->id, 'cuttingPlan' => $plan->id]) : route('projects.production-v2.cutting-plans.store', ['project' => $project->id]) }}">
        @csrf
        @if($plan->exists)
            @method('PUT')
        @endif
        <input type="hidden" name="revision_no" value="{{ old('revision_no', $plan->revision_no ?: 1) }}">

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-2">
                        <label class="form-label">Revision</label>
                        <input class="form-control" value="R{{ old('revision_no', $plan->revision_no ?: 1) }}" readonly>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Plan Number</label>
                        <input name="plan_number" class="form-control @error('plan_number') is-invalid @enderror" value="{{ old('plan_number', $plan->plan_number) }}" required>
                        @error('plan_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Plan Date</label>
                        <input type="date" name="plan_date" class="form-control" value="{{ old('plan_date', $plan->plan_date?->format('Y-m-d') ?: now()->toDateString()) }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Grade</label>
                        <input id="plan_grade" name="grade" class="form-control" value="{{ old('grade', $plan->grade) }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Thickness (mm)</label>
                        <input id="plan_thickness_mm" type="number" step="0.001" min="0" name="thickness_mm" class="form-control" value="{{ old('thickness_mm', $plan->thickness_mm) }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Source Mode</label>
                        <select name="source_mode" class="form-select" data-erp-select data-hide-search="true">
                            @foreach(['fresh_plate', 'remnant', 'mixed'] as $mode)
                                <option value="{{ $mode }}" @selected(old('source_mode', $plan->source_mode) === $mode)>{{ $mode }}</option>
                            @endforeach
                        </select>
                        <div class="mt-2 d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-dark source-mode-quick" data-source-mode="fresh_plate">Fresh</button>
                            <button type="button" class="btn btn-sm btn-outline-dark source-mode-quick" data-source-mode="remnant">Remnant</button>
                            <button type="button" class="btn btn-sm btn-outline-dark source-mode-quick" data-source-mode="mixed">Mixed</button>
                        </div>
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
                    <div class="col-12">
                        <div class="form-text" id="cutting-plan-material-help">
                            Plan grade and thickness will default from the selected raw parts. One cutting plan cannot mix different raw categories, grades, or thicknesses.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Plan Allocations</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-allocation-row">
                    <i class="bi bi-plus-circle me-1"></i>Add Allocation
                </button>
            </div>
            <div class="card-body">
                <div class="small text-body-secondary mb-3">
                    Add allocations thickness-wise. This screen defines planned raw blank dimensions only; actual stock selection will happen later in production cut batches.
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="allocation-table">
                        <thead>
                            <tr>
                                <th style="width: 24%">Part</th>
                                <th style="width: 10%">Qty</th>
                                <th id="mother-stock-header" style="width: 14%">Planned Blank Ref</th>
                                <th id="planned-blank-width-header" style="width: 8%">Blank W</th>
                                <th id="planned-blank-length-header" style="width: 8%">Blank L</th>
                                <th id="cut-size-header" style="width: 12%">Cut Size</th>
                                <th id="cut-width-header" style="width: 8%">W</th>
                                <th id="cut-length-header" style="width: 8%">L</th>
                                <th style="width: 8%">T</th>
                                <th style="width: 12%">Group</th>
                                <th>Remarks</th>
                                <th style="width: 4%"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @php
                            $rows = old('allocations');
                            if ($rows === null) {
                                $rows = $plan->exists
                                    ? $plan->allocations->map(fn ($row) => [
                                        'part_definition_id' => $row->part_definition_id,
                                        'planned_qty' => $row->planned_qty,
                                        'planned_blank_ref' => $row->planned_blank_ref ?: $row->allocation_group,
                                        'planned_blank_width_mm' => $row->planned_blank_width_mm,
                                        'planned_blank_length_mm' => $row->planned_blank_length_mm,
                                        'cut_size_text' => $row->cut_size_text,
                                        'cut_width_mm' => $row->cut_width_mm,
                                        'cut_length_mm' => $row->cut_length_mm,
                                        'thickness_mm' => $row->thickness_mm,
                                        'allocation_group' => $row->allocation_group,
                                        'remarks' => $row->remarks,
                                    ])->all()
                                    : [['part_definition_id' => '', 'planned_qty' => '', 'planned_blank_ref' => '', 'planned_blank_width_mm' => '', 'planned_blank_length_mm' => '', 'cut_size_text' => '', 'cut_width_mm' => '', 'cut_length_mm' => '', 'thickness_mm' => '', 'allocation_group' => '', 'remarks' => '']];
                            }
                        @endphp
                        @foreach($rows as $index => $row)
                            <tr>
                                <td>
                                    <select name="allocations[{{ $index }}][part_definition_id]" class="form-select" data-erp-select data-placeholder="Select part">
                                        <option value="">Select Part</option>
                                        @foreach($partDefinitions as $part)
                                            <option
                                                value="{{ $part->id }}"
                                                data-part-type="{{ $part->part_type }}"
                                                data-material-category="{{ $part->material_category }}"
                                                data-material-grade="{{ $part->material_grade }}"
                                                data-thickness="{{ $part->thickness_mm }}"
                                                data-width="{{ $part->width_mm }}"
                                                data-length="{{ $part->length_mm }}"
                                                data-is-cuttable="{{ $part->is_cuttable ? '1' : '0' }}"
                                                @selected((string)($row['part_definition_id'] ?? '') === (string)$part->id)
                                            >
                                                {{ $part->part_code }} - {{ $part->part_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="0.001" min="0.001" name="allocations[{{ $index }}][planned_qty]" class="form-control" value="{{ $row['planned_qty'] ?? '' }}"></td>
                                <td><input name="allocations[{{ $index }}][planned_blank_ref]" class="form-control" value="{{ $row['planned_blank_ref'] ?? ($row['allocation_group'] ?? '') }}" placeholder="Plate / section ref"></td>
                                <td class="planned-blank-width-cell"><input type="number" step="0.001" min="0" name="allocations[{{ $index }}][planned_blank_width_mm]" class="form-control" value="{{ $row['planned_blank_width_mm'] ?? '' }}"></td>
                                <td><input type="number" step="0.001" min="0" name="allocations[{{ $index }}][planned_blank_length_mm]" class="form-control" value="{{ $row['planned_blank_length_mm'] ?? '' }}"></td>
                                <td><input name="allocations[{{ $index }}][cut_size_text]" class="form-control" value="{{ $row['cut_size_text'] ?? '' }}"></td>
                                <td class="cut-width-cell"><input type="number" step="0.001" min="0" name="allocations[{{ $index }}][cut_width_mm]" class="form-control" value="{{ $row['cut_width_mm'] ?? '' }}"></td>
                                <td class="cut-length-cell"><input type="number" step="0.001" min="0" name="allocations[{{ $index }}][cut_length_mm]" class="form-control" value="{{ $row['cut_length_mm'] ?? '' }}"></td>
                                <td><input type="number" step="0.001" min="0" name="allocations[{{ $index }}][thickness_mm]" class="form-control" value="{{ $row['thickness_mm'] ?? '' }}"></td>
                                <td><input name="allocations[{{ $index }}][allocation_group]" class="form-control" value="{{ $row['allocation_group'] ?? ($row['planned_blank_ref'] ?? '') }}" placeholder="Nest group"></td>
                                <td><input name="allocations[{{ $index }}][remarks]" class="form-control" value="{{ $row['remarks'] ?? '' }}"></td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex gap-2 position-sticky bottom-0 bg-body">
                <button type="submit" class="btn btn-primary">{{ $plan->exists ? 'Update Cutting Plan' : 'Create Cutting Plan' }}</button>
                <a href="{{ route('projects.production-v2.cutting-plans.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>

    <template id="allocation-row-template">
        <tr>
            <td>
                <select class="form-select allocation-part" data-erp-select data-placeholder="Select part">
                    <option value="">Select Part</option>
                    @foreach($partDefinitions as $part)
                        <option
                            value="{{ $part->id }}"
                            data-part-type="{{ $part->part_type }}"
                            data-material-category="{{ $part->material_category }}"
                            data-material-grade="{{ $part->material_grade }}"
                            data-thickness="{{ $part->thickness_mm }}"
                            data-width="{{ $part->width_mm }}"
                            data-length="{{ $part->length_mm }}"
                            data-is-cuttable="{{ $part->is_cuttable ? '1' : '0' }}"
                        >{{ $part->part_code }} - {{ $part->part_name }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" step="0.001" min="0.001" class="form-control allocation-qty"></td>
            <td><input class="form-control allocation-blank-ref" placeholder="Plate / section ref"></td>
            <td class="planned-blank-width-cell"><input type="number" step="0.001" min="0" class="form-control allocation-blank-width"></td>
            <td><input type="number" step="0.001" min="0" class="form-control allocation-blank-length"></td>
            <td><input class="form-control allocation-size"></td>
            <td class="cut-width-cell"><input type="number" step="0.001" min="0" class="form-control allocation-width"></td>
            <td class="cut-length-cell"><input type="number" step="0.001" min="0" class="form-control allocation-length"></td>
            <td><input type="number" step="0.001" min="0" class="form-control allocation-thickness"></td>
            <td><input class="form-control allocation-group"></td>
            <td><input class="form-control allocation-remarks"></td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
        </tr>
    </template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.querySelector('#allocation-table tbody');
    const template = document.querySelector('#allocation-row-template');
    const addButton = document.querySelector('#add-allocation-row');
    const sourceModeQuickButtons = document.querySelectorAll('.source-mode-quick');
    const planGrade = document.getElementById('plan_grade');
    const planThickness = document.getElementById('plan_thickness_mm');
    const sourceMode = document.querySelector('select[name="source_mode"]');
    const materialHelp = document.getElementById('cutting-plan-material-help');
    const motherStockHeader = document.getElementById('mother-stock-header');
    const plannedBlankWidthHeader = document.getElementById('planned-blank-width-header');
    const plannedBlankLengthHeader = document.getElementById('planned-blank-length-header');
    const cutSizeHeader = document.getElementById('cut-size-header');
    const cutWidthHeader = document.getElementById('cut-width-header');
    const cutLengthHeader = document.getElementById('cut-length-header');

    const numberOrNull = (value) => {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const normalizeText = (value) => (value || '').trim().toUpperCase();

    const termForCategory = (category) => {
        if (category === 'steel_plate') {
            return {
                stockLabel: 'Planned Plate Ref',
                blankWidthLabel: 'Plate W',
                blankLengthLabel: 'Plate L',
                cutSizeLabel: 'Cut Size',
                widthLabel: 'W',
                lengthLabel: 'L',
                planningNoun: 'plate blank',
            };
        }

        if (category === 'steel_section') {
            return {
                stockLabel: 'Planned Section Ref',
                blankWidthLabel: '-',
                blankLengthLabel: 'Section L',
                cutSizeLabel: 'Section Length',
                widthLabel: '-',
                lengthLabel: 'Length',
                planningNoun: 'section blank',
            };
        }

        return {
            stockLabel: 'Planned Blank Ref',
            blankWidthLabel: 'Blank W',
            blankLengthLabel: 'Blank L',
            cutSizeLabel: 'Cut Size / Length',
            widthLabel: 'W',
            lengthLabel: 'L',
            planningNoun: 'blank',
        };
    };

    const currentPlanCategory = () => {
        const categories = [...tableBody.querySelectorAll('tr')]
            .map((row) => {
                const select = row.querySelector('select[name*="[part_definition_id]"], .allocation-part');
                const option = select?.options?.[select.selectedIndex];
                if (!option || !option.value || option.dataset.isCuttable !== '1') {
                    return null;
                }

                const category = option.dataset.materialCategory || '';
                return ['steel_plate', 'steel_section'].includes(category) ? category : null;
            })
            .filter(Boolean);

        if (!categories.length) {
            return '';
        }

        return categories.every((category) => category === categories[0]) ? categories[0] : '';
    };

    const updateMaterialTerminology = () => {
        const planCategory = currentPlanCategory();
        const terms = termForCategory(planCategory);

        if (motherStockHeader) {
            motherStockHeader.textContent = terms.stockLabel;
        }

        if (plannedBlankWidthHeader) {
            plannedBlankWidthHeader.textContent = terms.blankWidthLabel;
        }

        if (plannedBlankLengthHeader) {
            plannedBlankLengthHeader.textContent = terms.blankLengthLabel;
        }

        if (cutSizeHeader) {
            cutSizeHeader.textContent = terms.cutSizeLabel;
        }

        if (cutWidthHeader) {
            cutWidthHeader.textContent = terms.widthLabel;
        }

        if (cutLengthHeader) {
            cutLengthHeader.textContent = terms.lengthLabel;
        }

        const hideWidth = planCategory === 'steel_section';
        [...tableBody.querySelectorAll('.planned-blank-width-cell')].forEach((cell) => {
            cell.style.display = hideWidth ? 'none' : '';
        });
        if (plannedBlankWidthHeader) {
            plannedBlankWidthHeader.style.display = hideWidth ? 'none' : '';
        }
        if (cutWidthHeader) {
            cutWidthHeader.style.display = hideWidth ? 'none' : '';
        }

        [...tableBody.querySelectorAll('.cut-width-cell')].forEach((cell) => {
            cell.style.display = hideWidth ? 'none' : '';
        });
    };

    const syncRowFromPart = (row) => {
        const select = row.querySelector('select[name*="[part_definition_id]"], .allocation-part');
        const option = select?.options?.[select.selectedIndex];
        if (!option || !option.value) {
            updateMaterialTerminology();
            return;
        }

        const thicknessInput = row.querySelector('input[name*="[thickness_mm]"], .allocation-thickness');
        const widthInput = row.querySelector('input[name*="[cut_width_mm]"], .allocation-width');
        const lengthInput = row.querySelector('input[name*="[cut_length_mm]"], .allocation-length');
        const sizeInput = row.querySelector('input[name*="[cut_size_text]"], .allocation-size');

        if (option.dataset.thickness && thicknessInput && !thicknessInput.value) {
            thicknessInput.value = option.dataset.thickness;
        }

        if (option.dataset.materialCategory === 'steel_plate') {
            if (option.dataset.width && widthInput && !widthInput.value) {
                widthInput.value = option.dataset.width;
            }
            if (option.dataset.length && lengthInput && !lengthInput.value) {
                lengthInput.value = option.dataset.length;
            }
            if (!sizeInput?.value && option.dataset.width && option.dataset.length) {
                sizeInput.value = `${option.dataset.width} x ${option.dataset.length} mm`;
            }
        } else if (option.dataset.materialCategory === 'steel_section') {
            if (widthInput) {
                widthInput.value = '';
            }
            if (option.dataset.length && lengthInput && !lengthInput.value) {
                lengthInput.value = option.dataset.length;
            }
            if (!sizeInput?.value && option.dataset.length) {
                sizeInput.value = `${option.dataset.length} mm`;
            }
        }

        updateMaterialTerminology();
    };

    const syncPlanMaterialFromRows = () => {
        const profiles = [...tableBody.querySelectorAll('tr')]
            .map((row) => {
                const select = row.querySelector('select[name*="[part_definition_id]"], .allocation-part');
                const option = select?.options?.[select.selectedIndex];
                if (!option || !option.value) {
                    return null;
                }

                const thicknessInput = row.querySelector('input[name*="[thickness_mm]"], .allocation-thickness');

                return {
                    category: option.dataset.materialCategory || '',
                    grade: option.dataset.materialGrade || '',
                    thickness: thicknessInput?.value || option.dataset.thickness || '',
                    isCuttable: option.dataset.isCuttable === '1',
                };
            })
            .filter((profile) => profile && profile.isCuttable && ['steel_plate', 'steel_section'].includes(profile.category));

        if (!profiles.length) {
            if (materialHelp) {
                materialHelp.textContent = 'Plan grade and thickness will default from the selected raw parts. One cutting plan cannot mix different raw categories, grades, or thicknesses.';
            }
            return;
        }

        const first = profiles[0];
        const mixedCategory = profiles.some((profile) => profile.category !== first.category);
        const mixedGrade = profiles.some((profile) => normalizeText(profile.grade) !== normalizeText(first.grade));
        const mixedThickness = profiles.some((profile) => normalizeText(profile.thickness) !== normalizeText(first.thickness));

        if (!mixedCategory && !mixedGrade && !mixedThickness) {
            if (planGrade) {
                planGrade.value = first.grade;
            }
            if (planThickness) {
                planThickness.value = first.thickness;
            }
            if (materialHelp) {
                materialHelp.textContent = `Plan material derived from selected ${first.category === 'steel_plate' ? 'plate' : 'section'} parts.`;
            }
        } else if (materialHelp) {
            materialHelp.textContent = 'Selected rows currently mix raw material category, grade, or thickness. Save will be blocked until they are consistent.';
        }
    };

    const refreshNames = () => {
        [...tableBody.querySelectorAll('tr')].forEach((row, index) => {
            row.querySelector('.allocation-part, select[name*="[part_definition_id]"]')?.setAttribute('name', `allocations[${index}][part_definition_id]`);
            row.querySelector('.allocation-qty, input[name*="[planned_qty]"]')?.setAttribute('name', `allocations[${index}][planned_qty]`);
            row.querySelector('.allocation-blank-ref, input[name*="[planned_blank_ref]"]')?.setAttribute('name', `allocations[${index}][planned_blank_ref]`);
            row.querySelector('.allocation-blank-width, input[name*="[planned_blank_width_mm]"]')?.setAttribute('name', `allocations[${index}][planned_blank_width_mm]`);
            row.querySelector('.allocation-blank-length, input[name*="[planned_blank_length_mm]"]')?.setAttribute('name', `allocations[${index}][planned_blank_length_mm]`);
            row.querySelector('.allocation-size, input[name*="[cut_size_text]"]')?.setAttribute('name', `allocations[${index}][cut_size_text]`);
            row.querySelector('.allocation-width, input[name*="[cut_width_mm]"]')?.setAttribute('name', `allocations[${index}][cut_width_mm]`);
            row.querySelector('.allocation-length, input[name*="[cut_length_mm]"]')?.setAttribute('name', `allocations[${index}][cut_length_mm]`);
            row.querySelector('.allocation-thickness, input[name*="[thickness_mm]"]')?.setAttribute('name', `allocations[${index}][thickness_mm]`);
            row.querySelector('.allocation-group, input[name*="[allocation_group]"]')?.setAttribute('name', `allocations[${index}][allocation_group]`);
            row.querySelector('.allocation-remarks, input[name*="[remarks]"]')?.setAttribute('name', `allocations[${index}][remarks]`);
        });
    };

    addButton?.addEventListener('click', () => {
        tableBody.appendChild(template.content.cloneNode(true));
        refreshNames();
    });

    sourceModeQuickButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!sourceMode) {
                return;
            }

            sourceMode.value = button.dataset.sourceMode || '';
            sourceMode.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    tableBody?.addEventListener('change', (event) => {
        const row = event.target.closest('tr');
        if (!row) return;

        if (event.target.matches('select[name*="[part_definition_id]"], .allocation-part')) {
            syncRowFromPart(row);
            syncPlanMaterialFromRows();
        }

        if (event.target.matches('input[name*="[thickness_mm]"], .allocation-thickness')) {
            syncPlanMaterialFromRows();
        }
    });

    tableBody?.addEventListener('click', (event) => {
        const btn = event.target.closest('.remove-row');
        if (!btn) return;

        const rows = tableBody.querySelectorAll('tr');
        if (rows.length <= 1) {
            rows[0].querySelectorAll('input').forEach((input) => input.value = '');
            rows[0].querySelectorAll('select').forEach((select) => select.value = '');
            return;
        }

        btn.closest('tr')?.remove();
        refreshNames();
        updateMaterialTerminology();
        syncPlanMaterialFromRows();
    });

    sourceMode?.addEventListener('change', () => {
        const mode = sourceMode.value;
        if (!materialHelp) {
            return;
        }

        if (mode === 'fresh_plate') {
            materialHelp.textContent = 'Fresh plate mode means this nesting is intended for new raw plates, but actual stock will be chosen later in production cutting.';
        } else if (mode === 'remnant') {
            materialHelp.textContent = 'Remnant mode means this nesting is intended for reusable remnants, but no live stock is linked at design stage.';
        } else {
            syncPlanMaterialFromRows();
        }
    });

    refreshNames();
    updateMaterialTerminology();
    [...tableBody.querySelectorAll('tr')].forEach((row) => {
        syncRowFromPart(row);
    });
    syncPlanMaterialFromRows();
});
</script>
@endpush
