@extends('layouts.erp')

@section('title', 'Edit Store Requisition ' . ($requisition->requisition_number ?? ('SR-' . $requisition->id)))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                Edit Store Requisition
                <span class="text-muted">({{ $requisition->requisition_number ?? ('SR-' . $requisition->id) }})</span>
            </h1>
            <div class="small text-muted">
                Status: {{ ucfirst($requisition->status) }}
            </div>
        </div>
        <div>
            <a href="{{ route('store-requisitions.show', $requisition) }}" class="btn btn-sm btn-secondary">
                Back to Requisition
            </a>
        </div>
    </div>

    @if($errors->has('general'))
        <div class="alert alert-danger">
            {{ $errors->first('general') }}
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('store-requisitions.update', $requisition) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Requisition Date</label>
                        <input type="date"
                               name="requisition_date"
                               value="{{ old('requisition_date', optional($requisition->requisition_date)->format('Y-m-d')) }}"
                               class="form-control form-control-sm @error('requisition_date') is-invalid @enderror"
                               required>
                        @error('requisition_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Project</label>
                        <select name="project_id" id="project-id"
                                class="form-select form-select-sm @error('project_id') is-invalid @enderror"
                                required>
                            <option value="">-- Select Project --</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}"
                                    {{ (int) old('project_id', $requisition->project_id) === $project->id ? 'selected' : '' }}>
                                    {{ $project->code }} - {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('project_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Contractor (optional)</label>
                        <select name="contractor_party_id"
                                class="form-select form-select-sm @error('contractor_party_id') is-invalid @enderror">
                            <option value="">-- Select Contractor --</option>
                            @foreach($contractors as $party)
                                <option value="{{ $party->id }}"
                                    {{ (int) old('contractor_party_id', $requisition->contractor_party_id) === $party->id ? 'selected' : '' }}>
                                    {{ $party->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('contractor_party_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">Contractor / Person Name</label>
                        <input type="text"
                               name="contractor_person_name"
                               value="{{ old('contractor_person_name', $requisition->contractor_person_name) }}"
                               class="form-control form-control-sm @error('contractor_person_name') is-invalid @enderror"
                               maxlength="100">
                        @error('contractor_person_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks"
                                  rows="2"
                                  class="form-control form-control-sm @error('remarks') is-invalid @enderror">{{ old('remarks', $requisition->remarks) }}</textarea>
                        @error('remarks')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Lines --}}
        <div class="card">
            <div class="card-header py-2">
                <h5 class="card-title mb-0 h6">Requisition Lines</h5>
                <div class="small text-muted">
                    For now you can edit quantities and text fields only. Items and UOM are fixed.
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle" id="lines-table">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 35%">Item</th>
                            <th style="width: 10%">UOM</th>
                            <th style="width: 15%">Required Qty</th>
                            <th style="width: 20%">Description</th>
                            <th style="width: 10%">Brand</th>
                            <th style="width: 10%">Segment Ref</th>
                            <th style="width: 10%">Line Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            $oldLines = old('lines');
                        @endphp
                        @foreach($requisition->lines as $index => $line)
                            @php
                                $rowKey = $index;
                            @endphp
                            <tr>
                                <td>
                                    {{ $line->item->name ?? ('Item #' . $line->item_id) }}
                                    <div class="small text-muted">
                                        Code: {{ $line->item->code ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    {{ $line->uom->name ?? '-' }}
                                </td>
                                <td>
                                    <input type="hidden"
                                           name="lines[{{ $rowKey }}][id]"
                                           value="{{ $line->id }}">
                                    <input type="number"
                                           step="0.001"
                                           min="0.001"
                                           name="lines[{{ $rowKey }}][required_qty]"
                                           class="form-control form-control-sm text-end @error("lines.$rowKey.required_qty") is-invalid @enderror"
                                           value="{{ old("lines.$rowKey.required_qty", $line->required_qty) }}">
                                    @error("lines.$rowKey.required_qty")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td>
                                    <input type="text"
                                           name="lines[{{ $rowKey }}][description]"
                                           class="form-control form-control-sm @error("lines.$rowKey.description") is-invalid @enderror"
                                           value="{{ old("lines.$rowKey.description", $line->description) }}">
                                    @error("lines.$rowKey.description")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td>
                                    <select name="lines[{{ $rowKey }}][preferred_make]"
                                            class="form-select form-select-sm brand-select"
                                            data-item-id="{{ $line->item_id }}">
                                        <option value="">-- Any Brand --</option>
                                        @php
                                            $currentBrand = old("lines.$rowKey.preferred_make", $line->preferred_make);
                                        @endphp
                                        @if($currentBrand)
                                            <option value="{{ $currentBrand }}" selected>{{ $currentBrand }}</option>
                                        @endif
                                    </select>
                                    <div class="small text-muted brand-help" style="line-height:1.1;"></div>
                                    @error("lines.$rowKey.preferred_make")
                                    <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td>
                                    <input type="text"
                                           name="lines[{{ $rowKey }}][segment_reference]"
                                           class="form-control form-control-sm @error("lines.$rowKey.segment_reference") is-invalid @enderror"
                                           value="{{ old("lines.$rowKey.segment_reference", $line->segment_reference) }}">
                                    @error("lines.$rowKey.segment_reference")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td>
                                    <input type="text"
                                           name="lines[{{ $rowKey }}][remarks]"
                                           class="form-control form-control-sm @error("lines.$rowKey.remarks") is-invalid @enderror"
                                           value="{{ old("lines.$rowKey.remarks", $line->remarks) }}">
                                    @error("lines.$rowKey.remarks")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex justify-content-between">
            <a href="{{ route('store-requisitions.show', $requisition) }}"
               class="btn btn-sm btn-outline-secondary">
                Cancel
            </a>
            <button type="submit" class="btn btn-sm btn-primary">
                Save Changes
            </button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const BRAND_OPTIONS_URL = '{{ route('ajax.store-requisitions.available-brands') }}';

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function getProjectId() {
                const el = document.getElementById('project-id');
                return el ? (el.value || '') : '';
            }

            async function refreshBrandSelect(selectEl) {
                const itemId = selectEl.dataset.itemId;
                const helpEl = selectEl.closest('td')?.querySelector('.brand-help');

                if (!itemId) return;

                const current = selectEl.value || '';
                selectEl.innerHTML = '<option value="">Loading…</option>';
                selectEl.disabled = true;
                if (helpEl) helpEl.textContent = '';

                const projectId = getProjectId();

                const url = BRAND_OPTIONS_URL
                    + '?item_id=' + encodeURIComponent(itemId)
                    + (projectId ? ('&project_id=' + encodeURIComponent(projectId)) : '');

                try {
                    const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await resp.json();
                    const brands = Array.isArray(data.brands) ? data.brands : [];

                    let html = '<option value="">-- Any Brand --</option>';

                    // Keep current value even if it no longer exists in available stock
                    const normalized = brands.map(b => String(b).trim().toLowerCase());
                    const curNorm = String(current).trim().toLowerCase();
                    if (current && !normalized.includes(curNorm)) {
                        html += '<option value="' + escapeHtml(current) + '">' + escapeHtml(current) + ' (saved)</option>';
                    }

                    brands.forEach(function (b) {
                        html += '<option value="' + escapeHtml(b) + '">' + escapeHtml(b) + '</option>';
                    });

                    selectEl.innerHTML = html;
                    selectEl.disabled = false;

                    if (current) {
                        selectEl.value = current;
                    }

                    if (brands.length === 0 && helpEl) {
                        helpEl.textContent = 'No available stock brands found for this item.';
                    }
                } catch (e) {
                    selectEl.innerHTML = '<option value="">-- Any Brand --</option>';
                    if (current) {
                        selectEl.innerHTML += '<option value="' + escapeHtml(current) + '" selected>' + escapeHtml(current) + ' (saved)</option>';
                    }
                    selectEl.disabled = false;
                    if (helpEl) helpEl.textContent = 'Failed to load brands.';
                }
            }

            async function refreshAllBrandSelects() {
                const selects = document.querySelectorAll('.brand-select');
                for (const s of selects) {
                    await refreshBrandSelect(s);
                }
            }

            // Initial load
            refreshAllBrandSelects();

            // When project changes, refresh brand options (project-scoped stock)
            const projectEl = document.getElementById('project-id');
            if (projectEl) {
                projectEl.addEventListener('change', function () {
                    refreshAllBrandSelects();
                });
            }
        });
    </script>
@endsection
