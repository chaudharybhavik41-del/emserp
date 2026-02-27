@extends('layouts.erp')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="mb-1">Cutting Plan: {{ $plan->name }}</h1>
                <div class="text-muted">
                    Project: {{ $project->code ?? $project->name }} |
                    BOM: {{ $bom->bom_number ?? ('BOM #' . $bom->id) }}
                </div>
                <div class="text-muted small">
                    Grade: {{ $plan->grade ?? '-' }} |
                    Thickness: {{ $plan->thickness_mm }} mm
                </div>
            </div>
            <div>
                <a href="{{ route('projects.boms.cutting-plans.index', [$project, $bom]) }}"
                   class="btn btn-outline-secondary">
                    Back to Cutting Plans
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning">
                {{ session('warning') }}
            </div>
        @endif

        <div class="row">
            <div class="col-md-4">
                {{-- BOM plate components for this group --}}
                <div class="card mb-3">
                    <div class="card-header">
                        BOM Plate Components ({{ $plateItems->count() }})
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                <tr>
                                    <th>Part</th>
                                    <th>Size</th>
                                    <th>Qty</th>
                                    <th>Weight (kg)</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($plateItems as $item)
                                    @php
                                        $dims = $item->dimensions ?? [];
                                        $reqQty = (int) ($item->required_qty ?? $item->quantity ?? 0);
                                        $allocQty = (int) ($item->allocated_qty ?? 0);
                                        $remQty = (int) ($item->remaining_qty ?? max($reqQty - $allocQty, 0));
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ $item->item?->code ?? $item->item_code ?? ('#'.$item->id) }}<br>
                                            <small class="text-muted">{{ $item->description }}</small>
                                        </td>
                                        <td>
                                            {{ $dims['thickness_mm'] ?? '?' }} x
                                            {{ $dims['width_mm'] ?? '?' }} x
                                            {{ $dims['length_mm'] ?? '?' }} mm
                                        </td>
                                        <td>
                                            {{ $reqQty }}
                                            <div class="small text-muted">Remaining: {{ $remQty }}</div>
                                        </td>
                                        <td>{{ $item->total_weight !== null ? number_format($item->total_weight, 3) : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-muted text-center py-3">
                                            No plate components found for this grade &amp; thickness.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Add plate form --}}
                <div class="card mb-3">
                    <div class="card-header">
                        Add Plate to Cutting Plan
                    </div>
                    <div class="card-body">
                        <form method="POST"
                              action="{{ route('projects.boms.cutting-plans.add-plate', [$project, $bom, $plan]) }}"
                              class="row g-2">
                            @csrf

                            <div class="col-6">
                                <label class="form-label">Plate Label</label>
                                <input type="text"
                                       name="plate_label"
                                       value="{{ old('plate_label') }}"
                                       class="form-control form-control-sm @error('plate_label') is-invalid @enderror"
                                       placeholder="P1, P2, ...">
                                @error('plate_label')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6">
                                <label class="form-label">Qty (plates)</label>
                                <input type="number"
                                       name="plate_qty"
                                       value="{{ old('plate_qty', 1) }}"
                                       min="1"
                                       class="form-control form-control-sm @error('plate_qty') is-invalid @enderror">
                                @error('plate_qty')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">For remnant source, Qty must be 1.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Source</label>
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="source_mode"
                                           id="source_mode_new"
                                           value="new"
                                           {{ old('source_mode', 'new') === 'new' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="source_mode_new">
                                        New plate size (to purchase)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="source_mode"
                                           id="source_mode_remnant"
                                           value="remnant"
                                           {{ old('source_mode') === 'remnant' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="source_mode_remnant">
                                        From remnant library (same grade &amp; thickness)
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 js-source-new">
                                <div class="row g-2 mt-1">
                                    <div class="col-6">
                                        <label class="form-label">Width (mm)</label>
                                        <input type="number"
                                               name="width_mm"
                                               value="{{ old('width_mm') }}"
                                               class="form-control form-control-sm @error('width_mm') is-invalid @enderror">
                                        @error('width_mm')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Length (mm)</label>
                                        <input type="number"
                                               name="length_mm"
                                               value="{{ old('length_mm') }}"
                                               class="form-control form-control-sm @error('length_mm') is-invalid @enderror">
                                        @error('length_mm')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="form-text">
                                    Thickness is fixed at {{ $plan->thickness_mm }} mm from the plan.
                                </div>
                            </div>

                            <div class="col-12 js-source-remnant" style="display:none;">
                                <label class="form-label">Select remnant plate</label>
                                <select name="material_stock_piece_id"
                                        class="form-select form-select-sm @error('material_stock_piece_id') is-invalid @enderror">
                                    <option value="">-- choose from remnant library --</option>
                                    @foreach($remnants as $piece)
                                        <option value="{{ $piece->id }}" @selected(old('material_stock_piece_id') == $piece->id)>
                                            #{{ $piece->id }}
                                            {{ $piece->thickness_mm }} thk x {{ $piece->width_mm }} x {{ $piece->length_mm }} mm
                                            ({{ $piece->weight_kg !== null ? number_format($piece->weight_kg, 3) . ' kg' : 'no wt' }})
                                            @if($piece->plate_number)
                                                [{{ $piece->plate_number }}]
                                            @endif
                                            @if($piece->location)
                                                - {{ $piece->location }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('material_stock_piece_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if($remnants->isEmpty())
                                    <div class="form-text">
                                        No available remnant plates found for this grade &amp; thickness.
                                    </div>
                                @else
                                    <div class="form-text">
                                        Only AVAILABLE pieces with matching grade &amp; thickness are listed.
                                    </div>
                                @endif
                            </div>

                            <div class="col-12">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks"
                                          rows="2"
                                          class="form-control form-control-sm @error('remarks') is-invalid @enderror">{{ old('remarks') }}</textarea>
                                @error('remarks')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-sm btn-success">
                                    Add Plate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        Legend
                    </div>
                    <div class="card-body">
                        <ul class="small mb-0">
                            <li><strong>New plate size</strong> → virtual plate you intend to purchase for this cutting plan.</li>
                            <li><strong>Remnant plate</strong> → picked from remnant library (filtered by grade &amp; thickness).</li>
                            <li>Allocations map BOM plate components to specific plates for future production &amp; DPR.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        Plates &amp; Allocations
                    </div>
                    <div class="card-body p-0">
                        @if($plan->plates->isEmpty())
                            <p class="text-muted text-center py-3 mb-0">
                                No plates added yet. Use the form on the left to add a new or remnant plate.
                            </p>
                        @else
                            @foreach($plan->plates as $plate)
                                @php
                                    $allocatedWeight = $plate->allocations->sum('used_weight_kg');
                                    $grossWeight = $plate->gross_weight_kg;
                                    $coverage = ($grossWeight && $allocatedWeight)
                                        ? round(($allocatedWeight / $grossWeight) * 100, 1)
                                        : null;
                                @endphp
                                <div class="border-bottom">
                                    <div class="px-3 pt-3 pb-2 bg-light d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $plate->plate_label ?? ('Plate #' . $plate->id) }}</strong>
                                            <span class="text-muted">
                                                — {{ $plate->thickness_mm }} thk x {{ $plate->width_mm }} x {{ $plate->length_mm }} mm
                                            </span>
                                            @if($plate->source_type)
                                                <span class="badge bg-secondary ms-1">
                                                    {{ ucfirst($plate->source_type) }}
                                                </span>
                                            @endif
                                            @if($plate->materialStockPiece)
                                                <span class="badge bg-info ms-1">
                                                    Stock #{{ $plate->materialStockPiece->id }}
                                                </span>
                                            @endif
                                            <div class="small text-muted">
                                                Gross weight:
                                                {{ $plate->gross_weight_kg !== null ? number_format($plate->gross_weight_kg, 3) . ' kg' : '-' }}
                                                @if($coverage !== null)
                                                    — Alloc: {{ number_format($allocatedWeight, 3) }} kg ({{ $coverage }}%)
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="px-3 pb-3">
                                        <div class="row">
                                            <div class="col-md-7">
                                                <div class="table-responsive mt-2">
                                                    <table class="table table-sm mb-0 align-middle">
                                                        <thead>
                                                        <tr>
                                                            <th>Component</th>
                                                            <th>Qty</th>
                                                            <th>Weight (kg)</th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        @forelse($plate->allocations as $allocation)
                                                            <tr>
                                                                <td>
                                                                    @php
                                                                        $comp = $allocation->bomItem;
                                                                    @endphp

                                                                    @if($comp)
                                                                        {{ $comp->item?->code ?? $comp->item_code ?? ('#'.$comp->id) }}<br>
                                                                        <small class="text-muted">{{ $comp->description }}</small>
                                                                    @else
                                                                        <span class="text-muted">[deleted item]</span>
                                                                    @endif
                                                                </td>
                                                                <td>{{ $allocation->quantity }}</td>
                                                                <td>
                                                                    {{ $allocation->used_weight_kg !== null ? number_format($allocation->used_weight_kg, 3) : '-' }}
                                                                </td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="3" class="text-muted text-center py-2">
                                                                    No components allocated yet.
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="col-md-5">
                                                <form method="POST"
                                                      action="{{ route('projects.boms.cutting-plans.add-allocation', [$project, $bom, $plan, $plate]) }}"
                                                      class="row g-2 mt-2 js-allocation-form">
                                                    @csrf
                                                    <div class="col-12">
                                                        <label class="form-label">Add allocation</label>
                                                        <select name="bom_item_id"
                                                                class="form-select form-select-sm js-alloc-item">
                                                            @foreach($plateItems as $item)
                                                                @php
                                                                    $dims = $item->dimensions ?? [];
                                                                    $req = (int) ($item->required_qty ?? $item->quantity ?? 0);
                                                                    $rem = (int) ($item->remaining_qty ?? $req);
                                                                @endphp
                                                                <option value="{{ $item->id }}"
                                                                        data-remaining="{{ $rem }}"
                                                                        data-required="{{ $req }}">
                                                                    {{ $item->item?->code ?? $item->item_code ?? ('#'.$item->id) }}
                                                                    —
                                                                    {{ $dims['thickness_mm'] ?? '?' }} x
                                                                    {{ $dims['width_mm'] ?? '?' }} x
                                                                    {{ $dims['length_mm'] ?? '?' }} mm
                                                                    (remaining {{ $rem }} / {{ $req }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <div class="form-text small text-muted js-alloc-hint"></div>
                                                    </div>
                                                    <div class="col-5">
                                                        <input type="number"
                                                               name="quantity"
                                                               value="1"
                                                               min="1"
                                                               class="form-control form-control-sm js-alloc-qty"
                                                               placeholder="Qty">
                                                    </div>
                                                    <div class="col-7">
                                                        <input type="text"
                                                               name="notes"
                                                               value=""
                                                               class="form-control form-control-sm"
                                                               placeholder="Notes (optional)">
                                                    </div>
                                                    <div class="col-12 text-end">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            Add to Plate
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function toggleSourceMode() {
                const modeNew = document.getElementById('source_mode_new');
                const isNew = !modeNew || modeNew.checked;
                document.querySelectorAll('.js-source-new').forEach(el => el.style.display = isNew ? '' : 'none');
                document.querySelectorAll('.js-source-remnant').forEach(el => el.style.display = isNew ? 'none' : '');
            }

            function bindAllocationForms() {
                document.querySelectorAll('form.js-allocation-form').forEach(form => {
                    const select = form.querySelector('select.js-alloc-item');
                    const qtyInput = form.querySelector('input.js-alloc-qty');
                    const hint = form.querySelector('.js-alloc-hint');

                    if (!select || !qtyInput) return;

                    const update = () => {
                        const opt = select.options[select.selectedIndex];
                        const rem = opt && opt.dataset && opt.dataset.remaining ? parseInt(opt.dataset.remaining, 10) : null;

                        if (hint) {
                            if (rem !== null && !isNaN(rem)) {
                                hint.textContent = 'Remaining qty available to allocate: ' + rem;
                            } else {
                                hint.textContent = '';
                            }
                        }

                        if (rem !== null && !isNaN(rem) && rem > 0) {
                            qtyInput.max = rem;
                            const cur = parseInt(qtyInput.value || '0', 10);
                            if (!isNaN(cur) && cur > rem) {
                                qtyInput.value = rem;
                            }
                        } else {
                            qtyInput.removeAttribute('max');
                        }
                    };

                    select.addEventListener('change', update);
                    update();
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                const radios = document.querySelectorAll('input[name="source_mode"]');
                radios.forEach(r => r.addEventListener('change', toggleSourceMode));
                toggleSourceMode();

                bindAllocationForms();
            });
        </script>
    @endpush
@endsection
