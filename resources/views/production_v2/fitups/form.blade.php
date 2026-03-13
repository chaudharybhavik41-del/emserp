@extends('layouts.erp')

@section('title', 'Create Production V2 Fit-up')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Create Production V2 Fit-up</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end">
                @if($selectedDpr)
                    <input type="hidden" name="dpr_id" value="{{ $selectedDpr->id }}">
                @endif
                <div class="col-12 col-md-8">
                    <label class="form-label">Assembly</label>
                    <select name="assembly_id" class="form-select" data-erp-select onchange="this.form.submit()">
                        <option value="">Select Assembly</option>
                        @foreach($assemblies as $assembly)
                            <option value="{{ $assembly->id }}" @selected((int) request('assembly_id') === (int) $assembly->id)>
                                {{ $assembly->assembly_code }} - {{ $assembly->assembly_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100">Load Assembly</button>
                    <a href="{{ request()->url() }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    @if($selectedAssembly)
        <form method="POST" action="{{ route('projects.production-v2.fitups.store', ['project' => $project->id]) }}" data-pwa-sync="critical">
            @csrf
            <input type="hidden" name="dpr_id" value="{{ old('dpr_id', $selectedDpr?->id) }}">
            <input type="hidden" name="assembly_id" value="{{ $selectedAssembly->id }}">

            @if($selectedDpr)
                <div class="alert alert-primary mb-3">
                    Using DPR-{{ $selectedDpr->id }} from {{ $selectedDpr->dpr_date?->format('Y-m-d') ?: '-' }}.
                    {{ $selectedDpr->worker?->name ? 'Supervisor: ' . $selectedDpr->worker->name . '.' : '' }}
                    <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $selectedDpr->id]) }}" class="alert-link">Open DPR</a>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label">Fit-up Date</label>
                            <input type="date" name="fitup_date" class="form-control" value="{{ old('fitup_date', $selectedDpr?->dpr_date?->format('Y-m-d') ?: now()->toDateString()) }}" required>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Shift</label>
                            <input name="shift" class="form-control" value="{{ old('shift', $selectedDpr?->shift) }}">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Contractor</label>
                            <select name="contractor_party_id" class="form-select" data-erp-select data-placeholder="Contractor" data-allow-clear="1">
                                <option value="">Contractor</option>
                                @foreach($contractors as $contractor)
                                    <option value="{{ $contractor->id }}" @selected((string) old('contractor_party_id', $selectedDpr?->contractor_party_id) === (string) $contractor->id)>{{ $contractor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Supervisor</label>
                            <select name="supervisor_id" class="form-select" data-erp-select data-placeholder="Supervisor" data-allow-clear="1">
                                <option value="">Supervisor</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('supervisor_id', $selectedDpr?->worker_user_id) === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Inspector</label>
                            <select name="inspector_id" class="form-select" data-erp-select data-placeholder="Inspector" data-allow-clear="1">
                                <option value="">Inspector</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('inspector_id') === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-1">
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
                <div class="small text-body-secondary">Requirement rows stack vertically on phones. Select WIP, check dimension OK, and move to the next row without side scrolling.</div>
            </div>

            <div class="card">
                <div class="card-header">{{ $selectedAssembly->assembly_code }} Requirements And Matching WIP</div>
                <div class="px-3 pt-3 pb-2 border-bottom bg-body-tertiary">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary js-fitup-action" data-action="auto-qty">Auto Qty</button>
                        <button type="button" class="btn btn-sm btn-outline-success js-fitup-action" data-action="all-ok">Mark All OK</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-fitup-action" data-action="clear-ok">Clear OK</button>
                        <button type="button" class="btn btn-sm btn-outline-danger js-fitup-action" data-action="clear-qty">Clear Qty</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 mobile-entry-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Required Part</th>
                                    <th class="text-end">Required Qty</th>
                                    <th>Available WIP</th>
                                    <th class="text-end">Consume Qty</th>
                                    <th>Observed</th>
                                    <th>Specified</th>
                                    <th>Dim OK</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($requirementRows as $index => $rowData)
                                @php
                                    $requirement = $rowData['requirement'];
                                    $wipRows = $rowData['wip_rows'];
                                @endphp
                                <tr>
                                    <td data-label="Required Part">
                                        {{ $requirement->partDefinition?->part_code }}
                                        <div class="small text-body-secondary">{{ $requirement->partDefinition?->part_name }}</div>
                                        <input type="hidden" name="rows[{{ $index }}][part_definition_id]" value="{{ $requirement->part_definition_id }}">
                                        @if($requirement->is_mandatory)
                                            <div class="small text-danger">Mandatory</div>
                                        @endif
                                    </td>
                                    <td class="text-end" data-label="Required Qty">
                                        {{ number_format((float) $requirement->required_qty, 3) }} {{ $requirement->uom?->code ?: $requirement->partDefinition?->uom?->code }}
                                        <div class="small {{ $rowData['has_shortage'] ? 'text-danger' : 'text-body-secondary' }}">
                                            Available {{ number_format((float) $rowData['available_qty'], 3) }}
                                        </div>
                                    </td>
                                    <td data-label="Available WIP">
                                        <select name="rows[{{ $index }}][wip_item_id]" class="form-select form-select-sm" data-erp-select>
                                            <option value="">Select WIP</option>
                                            @foreach($wipRows as $wip)
                                                <option value="{{ $wip->id }}" @selected((string) $rowData['default_wip_id'] === (string) $wip->id)>
                                                    {{ $wip->piece_no ?: ($wip->lot_no ?: ('WIP#' . $wip->id)) }} / Qty {{ number_format((float) $wip->qty, 3) }} / Ref {{ $wip->plate_number ?: ($wip->motherStock?->section_profile ?? '-') }}{{ $wip->is_interchangeable ? ' / POOL' : ' / RESERVED' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="small mt-1 {{ $wipRows->isEmpty() ? 'text-danger' : 'text-body-secondary' }}">
                                            @if($wipRows->isEmpty())
                                                No available WIP for this part.
                                            @elseif($wipRows->count() === 1)
                                                Single matching WIP found and preselected.
                                            @else
                                                {{ $wipRows->count() }} matching WIP rows available.
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-end" data-label="Consume Qty"><input type="number" step="0.001" min="0.001" inputmode="decimal" name="rows[{{ $index }}][consumed_qty]" class="form-control form-control-sm text-end" value="{{ $rowData['default_consumed_qty'] }}"></td>
                                    <td data-label="Observed"><input name="rows[{{ $index }}][observed_dimension_text]" class="form-control form-control-sm" value="{{ $rowData['default_observed_dimension_text'] }}"></td>
                                    <td data-label="Specified"><input name="rows[{{ $index }}][specified_dimension_text]" class="form-control form-control-sm" value="{{ $rowData['default_specified_dimension_text'] }}"></td>
                                    <td class="text-center" data-label="Dim OK"><input type="checkbox" value="1" name="rows[{{ $index }}][dimension_ok]" @checked($rowData['default_dimension_ok'])></td>
                                    <td data-label="Remarks"><input name="rows[{{ $index }}][remarks]" class="form-control form-control-sm" value="{{ $rowData['default_remarks'] }}"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer border-top bg-light-subtle">
                    <small class="text-body-secondary">Fit-up now shows only WIP matching the selected assembly requirements. Mandatory parts must be linked to WIP before saving.</small>
                </div>
                <div class="sticky-bottom bg-body py-2 border-top">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('projects.production-v2.fitups.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Fit-up</button>
                    </div>
                </div>
            </div>
        </form>
    @else
        <div class="alert alert-info">Select an assembly first.</div>
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

    .mobile-entry-table td.text-end,
    .mobile-entry-table td.text-center {
        text-align: left !important;
    }

    .mobile-entry-table td[data-label="Dim OK"] input[type="checkbox"] {
        width: 1.2rem;
        height: 1.2rem;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('table tbody tr'));

    document.querySelectorAll('.js-fitup-action').forEach(function (button) {
        button.addEventListener('click', function () {
            const action = button.dataset.action;

            rows.forEach(function (row) {
                const qtyInput = row.querySelector('input[name$="[consumed_qty]"]');
                const okCheckbox = row.querySelector('input[type="checkbox"][name$="[dimension_ok]"]');

                if (action === 'all-ok' && okCheckbox) {
                    okCheckbox.checked = true;
                }

                if (action === 'clear-ok' && okCheckbox) {
                    okCheckbox.checked = false;
                }

                if (action === 'clear-qty' && qtyInput) {
                    qtyInput.value = '';
                }

                if (action === 'auto-qty' && qtyInput) {
                    const requiredCell = row.querySelector('td[data-label="Required Qty"]');
                    const wipSelect = row.querySelector('select[name$="[wip_item_id]"]');
                    const option = wipSelect?.selectedOptions?.[0];
                    const requiredMatch = requiredCell?.textContent?.match(/([\d,.]+)/);
                    const availableMatch = option?.textContent?.match(/Qty\s+([\d,.]+)/i);
                    const requiredQty = requiredMatch ? parseFloat(requiredMatch[1].replace(/,/g, '')) : null;
                    const availableQty = availableMatch ? parseFloat(availableMatch[1].replace(/,/g, '')) : null;

                    if (Number.isFinite(requiredQty) && Number.isFinite(availableQty)) {
                        qtyInput.value = Math.min(requiredQty, availableQty).toFixed(3).replace(/\.000$/, '');
                    }
                }
            });
        });
    });

    rows.forEach(function (row) {
        const wipSelect = row.querySelector('select[name$="[wip_item_id]"]');
        const qtyInput = row.querySelector('input[name$="[consumed_qty]"]');

        if (!wipSelect || !qtyInput) {
            return;
        }

        wipSelect.addEventListener('change', function () {
            if ((qtyInput.value || '').trim() !== '') {
                return;
            }

            const requiredCell = row.querySelector('td[data-label="Required Qty"]');
            const option = wipSelect.selectedOptions?.[0];
            const requiredMatch = requiredCell?.textContent?.match(/([\d,.]+)/);
            const availableMatch = option?.textContent?.match(/Qty\s+([\d,.]+)/i);
            const requiredQty = requiredMatch ? parseFloat(requiredMatch[1].replace(/,/g, '')) : null;
            const availableQty = availableMatch ? parseFloat(availableMatch[1].replace(/,/g, '')) : null;

            if (Number.isFinite(requiredQty) && Number.isFinite(availableQty)) {
                qtyInput.value = Math.min(requiredQty, availableQty).toFixed(3).replace(/\.000$/, '');
            }
        });
    });
});
</script>
@endpush
