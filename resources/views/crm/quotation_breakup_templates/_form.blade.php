@php
    /** @var \App\Models\CrmQuotationBreakupTemplate $template */
    $isEdit = !empty($template?->id);

    $basisOptions = [
        'per_unit' => 'Per Unit',
        'lumpsum'  => 'Lumpsum',
        'percent'  => '%',
    ];

    $normalizeBasis = function ($basis) {
        $b = strtolower(trim((string) $basis));

        if ($b === '' || in_array($b, ['per_unit','perunit','unit','per','per unit'], true)) {
            return 'per_unit';
        }
        if (in_array($b, ['lumpsum','lump','ls','lump_sum','lump sum'], true)) {
            return 'lumpsum';
        }
        if (in_array($b, ['percent','%','percentage','pct'], true)) {
            return 'percent';
        }
        return 'per_unit';
    };

    // Prefer old() (validation failure) so user input is not lost.
    $breakupLines = old('breakup_lines');

    if (!is_array($breakupLines)) {
        $breakupLines = [];

        $raw = (string) old('content', $template->content ?? '');
        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];

        foreach ($lines as $line) {
            $l = trim((string) $line);
            if ($l === '') continue;

            // allow comments
            if (str_starts_with($l, '#') || str_starts_with($l, '//')) continue;

            // strip bullets / numbering
            $l = preg_replace('/^\s*[\-\*\x{2022}]\s*/u', '', $l);
            $l = preg_replace('/^\s*\d+[\)\.]\s*/u', '', $l);

            $parts = array_map('trim', explode('|', $l));
            $name  = (string) ($parts[0] ?? '');
            if (trim($name) === '') continue;

            $basis = $normalizeBasis($parts[1] ?? 'per_unit');
            $rate  = isset($parts[2]) && is_numeric($parts[2]) ? $parts[2] : 0;

            $breakupLines[] = [
                'name'  => $name,
                'basis' => $basis,
                'rate'  => $rate,
            ];
        }
    }

    if (empty($breakupLines)) {
        $breakupLines = [[ 'name' => '', 'basis' => 'per_unit', 'rate' => 0 ]];
    }
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Code</label>
        <input type="text"
               name="code"
               class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code', $template->code ?? '') }}"
               placeholder="Ex: FAB_STD"
               maxlength="100" {{ $isEdit ? 'readonly' : '' }}>
        @error('code')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Unique identifier (cannot be changed after creation).</div>
    </div>

    <div class="col-md-8">
        <label class="form-label">Name</label>
        <input type="text"
               name="name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $template->name ?? '') }}"
               placeholder="Ex: Standard fabrication + painting">
        @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Sort Order</label>
        <input type="number"
               name="sort_order"
               class="form-control @error('sort_order') is-invalid @enderror"
               value="{{ old('sort_order', $template->sort_order ?? 0) }}"
               min="0" step="1">
        @error('sort_order')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-9 d-flex align-items-end gap-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_default" value="1"
                   id="is_default" @checked(old('is_default', $template->is_default ?? false))>
            <label class="form-check-label" for="is_default">Set as default</label>
        </div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                   id="is_active" @checked(old('is_active', $template->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Template Components</label>

        <div class="alert alert-info small">
            Add one component per row. This template is used inside the quotation <b>Cost Breakup / Rate Analysis</b> modal.
            <br>
            <span class="text-muted">
                Basis: <b>Per Unit</b> (Rs per UOM), <b>Lumpsum</b> (total for the line item), <b>%</b> (percentage of base direct cost).
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                <tr>
                    <th style="width: 45%;">Component</th>
                    <th style="width: 20%;">Basis</th>
                    <th style="width: 25%;" class="text-end">Rate</th>
                    <th style="width: 10%;"></th>
                </tr>
                </thead>
                <tbody id="breakup-lines-body">
                @foreach($breakupLines as $i => $line)
                    <tr>
                        <td>
                            <input type="text"
                                   name="breakup_lines[{{ $i }}][name]"
                                   class="form-control form-control-sm @error("breakup_lines.$i.name") is-invalid @enderror"
                                   value="{{ old("breakup_lines.$i.name", $line['name'] ?? '') }}"
                                   placeholder="Ex: Fabrication labour">
                            @error("breakup_lines.$i.name")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </td>
                        <td>
                            <select name="breakup_lines[{{ $i }}][basis]"
                                    class="form-select form-select-sm @error("breakup_lines.$i.basis") is-invalid @enderror">
                                @foreach($basisOptions as $val => $label)
                                    <option value="{{ $val }}"
                                        @selected(old("breakup_lines.$i.basis", $line['basis'] ?? 'per_unit') === $val)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error("breakup_lines.$i.basis")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </td>
                        <td>
                            <input type="number"
                                   name="breakup_lines[{{ $i }}][rate]"
                                   class="form-control form-control-sm text-end @error("breakup_lines.$i.rate") is-invalid @enderror"
                                   value="{{ old("breakup_lines.$i.rate", $line['rate'] ?? 0) }}"
                                   step="0.01"
                                   min="0">
                            @error("breakup_lines.$i.rate")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger js-remove-breakup-line">
                                Remove
                            </button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @error('breakup_lines')
        <div class="text-danger small">{{ $message }}</div>
        @enderror

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-breakup-line-btn">
                + Add Row
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="add-breakup-defaults-btn">
                Add Defaults
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="clear-breakup-lines-btn">
                Clear
            </button>
        </div>

        <details class="mt-2">
            <summary class="small text-muted">Show raw format</summary>
            <div class="small text-muted mt-1">
                System stores the template in this format (one per line): <code>Name|basis|rate</code>
                (basis = <code>per_unit</code> / <code>lumpsum</code> / <code>percent</code>).
            </div>
            <pre class="small mb-0">{{ old('content', $template->content ?? '') }}</pre>
        </details>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary" type="submit">
        {{ $isEdit ? 'Update Template' : 'Create Template' }}
    </button>
    <a href="{{ route('crm.quotation-breakup-templates.index') }}" class="btn btn-outline-secondary">
        Cancel
    </a>
</div>

<template id="breakup-line-template">
    <tr>
        <td>
            <input type="text"
                   name="breakup_lines[__INDEX__][name]"
                   class="form-control form-control-sm"
                   value=""
                   placeholder="Ex: Fabrication labour">
        </td>
        <td>
            <select name="breakup_lines[__INDEX__][basis]" class="form-select form-select-sm">
                @foreach($basisOptions as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number"
                   name="breakup_lines[__INDEX__][rate]"
                   class="form-control form-control-sm text-end"
                   value="0"
                   step="0.01"
                   min="0">
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger js-remove-breakup-line">Remove</button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.getElementById('breakup-lines-body');
    const rowTpl = document.getElementById('breakup-line-template');

    const addBtn = document.getElementById('add-breakup-line-btn');
    const addDefaultsBtn = document.getElementById('add-breakup-defaults-btn');
    const clearBtn = document.getElementById('clear-breakup-lines-btn');

    if (!tbody || !rowTpl) return;

    let nextIndex = (function () {
        const inputs = tbody.querySelectorAll('input[name^="breakup_lines["]');
        let max = -1;
        inputs.forEach(function (inp) {
            const m = inp.name.match(/breakup_lines\[(\d+)\]/);
            if (m) max = Math.max(max, parseInt(m[1], 10));
        });
        return max + 1;
    })();

    function addRow(data) {
        data = data || {};
        const html = rowTpl.innerHTML.replace(/__INDEX__/g, String(nextIndex));
        const temp = document.createElement('tbody');
        temp.innerHTML = html.trim();
        const row = temp.firstElementChild;

        if (!row) return;

        const nameInp = row.querySelector('input[name$="[name]"]');
        const basisSel = row.querySelector('select[name$="[basis]"]');
        const rateInp = row.querySelector('input[name$="[rate]"]');

        if (nameInp) nameInp.value = data.name || '';
        if (basisSel && data.basis) basisSel.value = data.basis;
        if (rateInp) rateInp.value = (typeof data.rate !== 'undefined') ? data.rate : 0;

        tbody.appendChild(row);
        nextIndex++;
    }

    function clearRows() {
        tbody.innerHTML = '';
        nextIndex = 0;
        addRow();
    }

    tbody.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-remove-breakup-line');
        if (!btn) return;

        const row = btn.closest('tr');
        if (row) row.remove();

        // Keep at least one row
        if (tbody.querySelectorAll('tr').length === 0) {
            addRow();
        }
    });

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            addRow();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            const ok = confirm('Clear all rows?');
            if (!ok) return;
            clearRows();
        });
    }

    if (addDefaultsBtn) {
        addDefaultsBtn.addEventListener('click', function () {
            const defaults = [
                { name: 'Fabrication labour', basis: 'per_unit', rate: 0 },
                { name: 'Consumables',        basis: 'per_unit', rate: 0 },
                { name: 'Painting labour',    basis: 'per_unit', rate: 0 },
                { name: 'Paint material',     basis: 'per_unit', rate: 0 },
                { name: 'Transport',          basis: 'lumpsum',  rate: 0 },
                { name: 'Other',              basis: 'per_unit', rate: 0 },
            ];

            // If only one empty row exists, replace it.
            const rows = tbody.querySelectorAll('tr');
            if (rows.length === 1) {
                const firstName = rows[0].querySelector('input[name$="[name]"]')?.value || '';
                const firstRate = rows[0].querySelector('input[name$="[rate]"]')?.value || '';
                if (firstName.trim() === '' && (firstRate === '' || parseFloat(firstRate || '0') === 0)) {
                    tbody.innerHTML = '';
                    nextIndex = 0;
                }
            }

            defaults.forEach(function (d) { addRow(d); });
        });
    }
});
</script>
@endpush
