@extends('layouts.erp')

@section('title', 'Create Fuel Issue')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Create Fuel Issue</h1>
    </div>

    @if($errors->has('general'))
        <div class="alert alert-danger">{{ $errors->first('general') }}</div>
    @endif

    <form action="{{ route('fuel-issues.store') }}" method="POST">
        @csrf

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Issue Date</label>
                        <input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}"
                               class="form-control form-control-sm @error('issue_date') is-invalid @enderror" required>
                        @error('issue_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Machine</label>
                        <select name="machine_id" id="machine_id" class="form-select form-select-sm @error('machine_id') is-invalid @enderror" required>
                            <option value="">-- Select Machine --</option>
                            @foreach($machines as $machine)
                                <option value="{{ $machine->id }}"
                                        data-project-id="{{ (int) ($machine->current_project_id ?? 0) }}"
                                    {{ (int) old('machine_id') === (int) $machine->id ? 'selected' : '' }}>
                                    {{ $machine->code ? ($machine->code . ' - ') : '' }}{{ $machine->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('machine_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">
                            Only machines marked "Allow this machine in Fuel Issue" are listed.
                        </div>
                        @if($machines->isEmpty())
                            <div class="text-danger small mt-1">
                                No machine is enabled for fuel issue. Enable it in Machinery master.
                            </div>
                        @endif
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Project (optional)</label>
                        <select name="project_id" id="project_id" class="form-select form-select-sm @error('project_id') is-invalid @enderror">
                            <option value="">-- Auto from Machine / General --</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" {{ (int) old('project_id', (int) $selectedProjectId) === (int) $project->id ? 'selected' : '' }}>
                                    {{ $project->code }} - {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label">Fuel Stock Item</label>
                        <select name="store_stock_item_id" id="store_stock_item_id"
                                class="form-select form-select-sm @error('store_stock_item_id') is-invalid @enderror" required>
                            <option value="">-- Select Stock --</option>
                            @foreach($stockItems as $stock)
                                @php
                                    $available = (float) ($stock->weight_kg_available ?? 0);
                                    $uom = $stock->item?->uom?->name ?? '';
                                    $label = ($stock->item?->code ? ($stock->item->code . ' - ') : '') . ($stock->item?->name ?? ('Item#' . $stock->item_id));
                                @endphp
                                <option value="{{ $stock->id }}"
                                        data-available="{{ $available }}"
                                        data-uom="{{ $uom }}"
                                    {{ (int) old('store_stock_item_id') === (int) $stock->id ? 'selected' : '' }}>
                                    {{ $label }} | Avl: {{ number_format($available, 3) }} {{ $uom }}
                                </option>
                            @endforeach
                        </select>
                        @error('store_stock_item_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">
                            Only items with material type code <strong>FUEL</strong> are listed.
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Qty</label>
                        <input type="number" name="qty" id="qty" min="0.001" step="0.001"
                               value="{{ old('qty') }}"
                               class="form-control form-control-sm @error('qty') is-invalid @enderror" required>
                        @error('qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Opening Meter</label>
                        <input type="number" name="opening_meter_reading" min="0" step="0.001"
                               value="{{ old('opening_meter_reading') }}"
                               class="form-control form-control-sm @error('opening_meter_reading') is-invalid @enderror">
                        @error('opening_meter_reading') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Closing Meter</label>
                        <input type="number" name="closing_meter_reading" min="0" step="0.001"
                               value="{{ old('closing_meter_reading') }}"
                               class="form-control form-control-sm @error('closing_meter_reading') is-invalid @enderror">
                        @error('closing_meter_reading') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control form-control-sm @error('remarks') is-invalid @enderror">{{ old('remarks') }}</textarea>
                        @error('remarks') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('fuel-issues.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-sm btn-primary">Save Fuel Issue</button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const machineEl = document.getElementById('machine_id');
            const projectEl = document.getElementById('project_id');
            const stockEl = document.getElementById('store_stock_item_id');
            const qtyEl = document.getElementById('qty');

            function autoProjectFromMachine() {
                if (!machineEl || !projectEl || projectEl.value) return;
                const opt = machineEl.selectedOptions[0];
                if (!opt) return;
                const machineProjectId = parseInt(opt.dataset.projectId || '0', 10);
                if (machineProjectId > 0) {
                    projectEl.value = String(machineProjectId);
                }
            }

            function validateQtyAgainstAvailable() {
                if (!stockEl || !qtyEl) return;
                const opt = stockEl.selectedOptions[0];
                if (!opt) return;
                const available = parseFloat(opt.dataset.available || '0');
                const qty = parseFloat(qtyEl.value || '0');
                if (qty > available && available > 0) {
                    qtyEl.setCustomValidity('Quantity exceeds available stock (' + available.toFixed(3) + ').');
                } else {
                    qtyEl.setCustomValidity('');
                }
            }

            machineEl?.addEventListener('change', autoProjectFromMachine);
            stockEl?.addEventListener('change', validateQtyAgainstAvailable);
            qtyEl?.addEventListener('input', validateQtyAgainstAvailable);
        });
    </script>
@endsection
