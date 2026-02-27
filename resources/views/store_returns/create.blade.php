@extends('layouts.erp')

@section('title', 'Create Store Return')

@section('content')
<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Create Store Return</h4>
        <a href="{{ route('store-returns.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('store-returns.store') }}" method="POST">
        @csrf

        @php
            $selectedIssue = old('store_issue_id', $selectedIssueId);
        @endphp

        {{-- Return Details --}}
        <div class="card mb-3">
            <div class="card-header">
                <strong>Return Details</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">
                            Return Date <span class="text-danger">*</span>
                        </label>
                        <input type="date"
                               name="return_date"
                               class="form-control form-control-sm"
                               value="{{ old('return_date', now()->format('Y-m-d')) }}"
                               required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Store Issue (optional)</label>
                        <select name="store_issue_id" id="store_issue_id" class="form-select form-select-sm">
                            <option value="">-- None --</option>
                            @foreach($issues as $iss)
                                <option value="{{ $iss->id }}" @selected((string)$selectedIssue === (string)$iss->id)>
                                    {{ $iss->issue_number }} @if($iss->project) ({{ $iss->project->code }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Select an issue to return against specific issue lines (recommended for traceability).
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label form-label-sm">Project (optional)</label>
                        <select name="project_id" class="form-select form-select-sm">
                            <option value="">-- Auto (from issue) --</option>
                            @foreach($projects as $proj)
                                <option value="{{ $proj->id }}" @selected((string)old('project_id') === (string)$proj->id)>
                                    {{ $proj->code }} - {{ $proj->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Contractor (optional)</label>
                        <select name="contractor_party_id" class="form-select form-select-sm">
                            <option value="">-- Auto (from issue) --</option>
                            @foreach($contractors as $c)
                                <option value="{{ $c->id }}" @selected((string)old('contractor_party_id') === (string)$c->id)>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Contractor Person (optional)</label>
                        <input type="text"
                               name="contractor_person_name"
                               class="form-control form-control-sm"
                               value="{{ old('contractor_person_name') }}"
                               maxlength="100">
                    </div>

                    <div class="col-md-5">
                        <label class="form-label form-label-sm">Reason (optional)</label>
                        <input type="text"
                               name="reason"
                               class="form-control form-control-sm"
                               value="{{ old('reason') }}"
                               maxlength="255">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label form-label-sm">Remarks (optional)</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                @if($issue)
                    <div class="alert alert-secondary py-2 mt-3 mb-0">
                        <small>
                            <strong>Selected Issue:</strong> {{ $issue->issue_number }}
                            @if($issue->project)
                                &nbsp;|&nbsp;<strong>Project:</strong> {{ $issue->project->code }}
                            @endif
                            @if($issue->contractor)
                                &nbsp;|&nbsp;<strong>Contractor:</strong> {{ $issue->contractor->name }}
                            @endif
                        </small>
                    </div>
                @endif
            </div>
        </div>

        {{-- Return Lines --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Return Lines</strong>

                @if(!$issue)
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-line-btn">
                        + Add Line
                    </button>
                @endif
            </div>

            <div class="card-body">
                @if($issue && $issueLineSummaries->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 10%">Issue Line</th>
                                    <th style="width: 10%">Stock</th>
                                    <th>Item</th>
                                    <th class="text-end" style="width: 10%">Issued</th>
                                    <th class="text-end" style="width: 10%">Returned</th>
                                    <th class="text-end" style="width: 10%">Balance</th>
                                    <th class="text-end" style="width: 12%">Return Now</th>
                                    <th style="width: 8%">UOM</th>
                                    <th style="width: 20%">Line Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($issueLineSummaries as $idx => $l)
                                    @php
                                        $balance = (float) ($l['balance_qty'] ?? 0);
                                        $oldQty  = old("lines.$idx.returned_weight_kg", 0);
                                    @endphp
                                    <tr>
                                        <td class="text-muted">#{{ $l['id'] }}</td>
                                        <td>#{{ $l['store_stock_item_id'] }}</td>
                                        <td>
                                            <div class="small fw-semibold">
                                                {{ $l['item_code'] }} - {{ $l['item_name'] }}
                                            </div>
                                            @if(!empty($l['stock_project']))
                                                <div class="small text-muted">Stock Project: {{ $l['stock_project'] }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format((float)$l['issued_qty'], 3) }}</td>
                                        <td class="text-end">{{ number_format((float)$l['returned_qty'], 3) }}</td>
                                        <td class="text-end">{{ number_format((float)$l['balance_qty'], 3) }}</td>
                                        <td>
                                            <input type="hidden" name="lines[{{ $idx }}][store_stock_item_id]" value="{{ $l['store_stock_item_id'] }}">
                                            <input type="hidden" name="lines[{{ $idx }}][store_issue_line_id]" value="{{ $l['id'] }}">
                                            <input type="number"
                                                   name="lines[{{ $idx }}][returned_weight_kg]"
                                                   class="form-control form-control-sm text-end"
                                                   min="0"
                                                   step="0.001"
                                                   max="{{ $balance }}"
                                                   value="{{ $oldQty }}"
                                                   @if($balance <= 0) disabled @endif>
                                        </td>
                                        <td>{{ $l['uom_name'] ?? '-' }}</td>
                                        <td>
                                            <input type="text"
                                                   name="lines[{{ $idx }}][remarks]"
                                                   class="form-control form-control-sm"
                                                   value="{{ old("lines.$idx.remarks") }}"
                                                   maxlength="255"
                                                   @if($balance <= 0) disabled @endif>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="form-text">
                        Only lines with <strong>Return Now &gt; 0</strong> will be saved.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 45%">Issued Stock Item</th>
                                    <th class="text-end" style="width: 15%">Return Qty</th>
                                    <th style="width: 10%">UOM</th>
                                    <th style="width: 25%">Line Remarks</th>
                                    <th style="width: 5%"></th>
                                </tr>
                            </thead>
                            <tbody id="return-lines-body">
                                @php
                                    $oldLines = old('lines');
                                @endphp

                                @if(is_array($oldLines) && count($oldLines))
                                    @foreach($oldLines as $i => $oldLine)
                                        <tr data-index="{{ $i }}">
                                            <td>
                                                <select name="lines[{{ $i }}][store_stock_item_id]"
                                                        class="form-select form-select-sm stock-select"
                                                        required>
                                                    <option value="">-- Select Issued Stock --</option>
                                                    @foreach($stockItems as $stock)
                                                        @php
                                                            $issuedQty = (float) ($stock->weight_kg_total ?? 0) - (float) ($stock->weight_kg_available ?? 0);
                                                            $uomName   = $stock->item?->uom?->name;
                                                        @endphp
                                                        <option value="{{ $stock->id }}"
                                                                data-uom="{{ $uomName }}"
                                                                @selected((string)($oldLine['store_stock_item_id'] ?? '') === (string)$stock->id)>
                                                            #{{ $stock->id }}
                                                            | {{ $stock->item?->code }} - {{ $stock->item?->name }}
                                                            | {{ $stock->project?->code ?? '-' }}
                                                            | Issued: {{ number_format($issuedQty, 3) }} {{ $uomName }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number"
                                                       name="lines[{{ $i }}][returned_weight_kg]"
                                                       class="form-control form-control-sm text-end"
                                                       min="0.001"
                                                       step="0.001"
                                                       value="{{ old("lines.$i.returned_weight_kg") }}"
                                                       required>
                                            </td>
                                            <td>
                                                <span class="uom-label small text-muted"></span>
                                            </td>
                                            <td>
                                                <input type="text"
                                                       name="lines[{{ $i }}][remarks]"
                                                       class="form-control form-control-sm"
                                                       value="{{ old("lines.$i.remarks") }}"
                                                       maxlength="255">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line-btn">&times;</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr data-index="0">
                                        <td>
                                            <select name="lines[0][store_stock_item_id]"
                                                    class="form-select form-select-sm stock-select"
                                                    required>
                                                <option value="">-- Select Issued Stock --</option>
                                                @foreach($stockItems as $stock)
                                                    @php
                                                        $issuedQty = (float) ($stock->weight_kg_total ?? 0) - (float) ($stock->weight_kg_available ?? 0);
                                                        $uomName   = $stock->item?->uom?->name;
                                                    @endphp
                                                    <option value="{{ $stock->id }}"
                                                            data-uom="{{ $uomName }}">
                                                        #{{ $stock->id }}
                                                        | {{ $stock->item?->code }} - {{ $stock->item?->name }}
                                                        | {{ $stock->project?->code ?? '-' }}
                                                        | Issued: {{ number_format($issuedQty, 3) }} {{ $uomName }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="lines[0][returned_weight_kg]"
                                                   class="form-control form-control-sm text-end"
                                                   min="0.001"
                                                   step="0.001"
                                                   value=""
                                                   required>
                                        </td>
                                        <td>
                                            <span class="uom-label small text-muted"></span>
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="lines[0][remarks]"
                                                   class="form-control form-control-sm"
                                                   value=""
                                                   maxlength="255">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line-btn">&times;</button>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="form-text">
                        Tip: Prefer selecting a <strong>Store Issue</strong> above to return against issue lines (more traceable).
                    </div>
                @endif
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('store-returns.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-sm btn-primary">Create Store Return</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Reload on Store Issue selection to switch UI into "Issue Line Return" mode
        const issueSelect = document.getElementById('store_issue_id');
        if (issueSelect) {
            issueSelect.addEventListener('change', function () {
                const url = new URL(window.location.href);
                if (this.value) {
                    url.searchParams.set('store_issue_id', this.value);
                } else {
                    url.searchParams.delete('store_issue_id');
                }
                window.location.href = url.toString();
            });
        }

        // Stock-selection mode helpers (no issue selected)
        const linesBody = document.getElementById('return-lines-body');
        const addLineBtn = document.getElementById('add-line-btn');

        function updateUomLabel(row) {
            if (!row) return;
            const select = row.querySelector('.stock-select');
            const label = row.querySelector('.uom-label');

            if (!select || !label) return;

            const opt = select.selectedOptions && select.selectedOptions[0];
            const uom = opt ? (opt.getAttribute('data-uom') || '') : '';
            label.textContent = uom || '-';
        }

        if (linesBody) {
            // Initialize existing rows
            linesBody.querySelectorAll('tr').forEach(updateUomLabel);

            linesBody.addEventListener('change', function (e) {
                if (e.target.classList.contains('stock-select')) {
                    updateUomLabel(e.target.closest('tr'));
                }
            });

            linesBody.addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-line-btn')) {
                    e.target.closest('tr')?.remove();
                }
            });
        }

        if (addLineBtn && linesBody) {
            addLineBtn.addEventListener('click', function () {
                const nextIndex = linesBody.querySelectorAll('tr').length;

                const rowHtml = `
                    <tr data-index="${nextIndex}">
                        <td>
                            <select name="lines[${nextIndex}][store_stock_item_id]"
                                    class="form-select form-select-sm stock-select"
                                    required>
                                <option value="">-- Select Issued Stock --</option>
                                @foreach($stockItems as $stock)
                                    @php
                                        $issuedQty = (float) ($stock->weight_kg_total ?? 0) - (float) ($stock->weight_kg_available ?? 0);
                                        $uomName   = $stock->item?->uom?->name;
                                    @endphp
                                    <option value="{{ $stock->id }}"
                                            data-uom="{{ $uomName }}">
                                        #{{ $stock->id }}
                                        | {{ $stock->item?->code }} - {{ $stock->item?->name }}
                                        | {{ $stock->project?->code ?? '-' }}
                                        | Issued: {{ number_format($issuedQty, 3) }} {{ $uomName }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number"
                                   name="lines[${nextIndex}][returned_weight_kg]"
                                   class="form-control form-control-sm text-end"
                                   min="0.001"
                                   step="0.001"
                                   required>
                        </td>
                        <td>
                            <span class="uom-label small text-muted"></span>
                        </td>
                        <td>
                            <input type="text"
                                   name="lines[${nextIndex}][remarks]"
                                   class="form-control form-control-sm"
                                   maxlength="255">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line-btn">&times;</button>
                        </td>
                    </tr>
                `;

                linesBody.insertAdjacentHTML('beforeend', rowHtml);
                const newRow = linesBody.querySelector('tr:last-child');
                updateUomLabel(newRow);
            });
        }
    });
</script>
@endpush
