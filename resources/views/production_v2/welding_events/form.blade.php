@extends('layouts.erp')

@section('title', 'Create Production V2 Welding Event')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Create Production V2 Welding Event</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    <div class="alert alert-info">
        Welding is assembly-centric in V2. A welding event can be created only after at least one fit-up exists for the selected assembly.
    </div>

    @if(! $selectedDpr)
        <div class="alert alert-light border">
            Start from Daily DPR when possible so the same supervisor/operator day record flows into welding.
            <a href="{{ route('projects.production-v2.dprs.create', ['project' => $project->id, 'activity' => 'welding']) }}" class="alert-link">Create Welding DPR</a>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end mb-4">
                @if($selectedDpr)
                    <input type="hidden" name="dpr_id" value="{{ $selectedDpr->id }}">
                @endif
                <div class="col-12 col-lg-8">
                    <label class="form-label mb-1" for="assembly_id">Assembly</label>
                    <select id="assembly_id" name="assembly_id" class="form-select" data-erp-select>
                        <option value="">Select assembly</option>
                        @foreach($assemblies as $assembly)
                            <option value="{{ $assembly->id }}" @selected((int) request('assembly_id') === (int) $assembly->id)>
                                {{ $assembly->assembly_code }} - {{ $assembly->assembly_name }} (fit-ups: {{ $assembly->fitups_count }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-4 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100">Load Assembly</button>
                    <a href="{{ route('projects.production-v2.welding-events.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary w-100">Cancel</a>
                </div>
            </form>

            @if($selectedAssembly)
                <div class="card bg-light-subtle border-0 mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <div class="small text-body-secondary">Assembly</div>
                                <div>{{ $selectedAssembly->assembly_code }}</div>
                                <div class="small text-body-secondary">{{ $selectedAssembly->assembly_name }}</div>
                            </div>
                            <div class="col-12 col-md-2"><div class="small text-body-secondary">Fit-ups</div><div>{{ number_format($selectedAssembly->fitups_count) }}</div></div>
                            <div class="col-12 col-md-2"><div class="small text-body-secondary">Welding Events</div><div>{{ number_format($selectedAssembly->welding_events_count) }}</div></div>
                            <div class="col-12 col-md-2"><div class="small text-body-secondary">Inspections</div><div>{{ number_format($selectedAssembly->inspection_events_count) }}</div></div>
                            <div class="col-12 col-md-2"><div class="small text-body-secondary">Requirements</div><div>{{ number_format($selectedAssembly->requirements->count()) }}</div></div>
                        </div>
                        @if($selectedAssembly->fitups->isNotEmpty())
                            <hr>
                            <div class="small text-body-secondary mb-2">Recent Fit-ups</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Fit-up</th>
                                            <th>Date</th>
                                            <th>Supervisor</th>
                                            <th>Inspector</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($selectedAssembly->fitups as $fitup)
                                            <tr>
                                                <td>FU-{{ $fitup->id }}</td>
                                                <td>{{ $fitup->fitup_date?->format('Y-m-d') ?: '-' }}</td>
                                                <td>{{ $fitup->supervisor?->name ?: '-' }}</td>
                                                <td>{{ $fitup->inspector?->name ?: '-' }}</td>
                                                <td>{{ $fitup->status }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($latestFitup && $fitupConsumptionSummary->isNotEmpty())
                            <hr>
                            <div class="small text-body-secondary mb-2">Latest Fit-up Traceability</div>
                            <div class="row g-3 mb-2">
                                <div class="col-12 col-md-3"><div class="small text-body-secondary">Latest Fit-up</div><div>FU-{{ $latestFitup->id }}</div></div>
                                <div class="col-12 col-md-3"><div class="small text-body-secondary">Date</div><div>{{ $latestFitup->fitup_date?->format('Y-m-d') ?: '-' }}</div></div>
                                <div class="col-12 col-md-3"><div class="small text-body-secondary">Supervisor</div><div>{{ $latestFitup->supervisor?->name ?: '-' }}</div></div>
                                <div class="col-12 col-md-3"><div class="small text-body-secondary">Contractor</div><div>{{ $latestFitup->contractor?->name ?: '-' }}</div></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Part</th>
                                            <th>WIP Ref</th>
                                            <th class="text-end">Qty</th>
                                            <th>Source Ref</th>
                                            <th>Heat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($fitupConsumptionSummary as $consumption)
                                            @php
                                                $sourceRef = $consumption->plate_number_snapshot
                                                    ?: ($consumption->wipItem?->motherStock?->section_profile
                                                        ?: ($consumption->wipItem?->piece_no ?: $consumption->wipItem?->lot_no));
                                            @endphp
                                            <tr>
                                                <td>{{ $consumption->partDefinition?->part_code }}<div class="small text-body-secondary">{{ $consumption->partDefinition?->part_name }}</div></td>
                                                <td>{{ $consumption->wipItem?->piece_no ?: ($consumption->wipItem?->lot_no ?: '-') }}</td>
                                                <td class="text-end">{{ number_format((float) $consumption->consumed_qty, 3) }} {{ $consumption->uom?->code }}</td>
                                                <td>{{ $sourceRef ?: '-' }}</td>
                                                <td>{{ $consumption->heat_number_snapshot ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('projects.production-v2.welding-events.store', ['project' => $project->id]) }}" data-pwa-sync="critical">
                @csrf
                <input type="hidden" name="dpr_id" value="{{ old('dpr_id', $selectedDpr?->id) }}">
                <input type="hidden" name="assembly_id" value="{{ old('assembly_id', $selectedAssembly?->id) }}">

                @if($selectedDpr)
                    <div class="alert alert-primary">
                        Using DPR-{{ $selectedDpr->id }} from {{ $selectedDpr->dpr_date?->format('Y-m-d') ?: '-' }}.
                        {{ $selectedDpr->worker?->name ? 'Welder: ' . $selectedDpr->worker->name . '.' : '' }}
                        {{ $selectedDpr->machine?->name ? 'Machine: ' . $selectedDpr->machine->name . '.' : '' }}
                    </div>
                @endif

                <div class="alert alert-light border mb-3 d-md-none">
                    <div class="fw-semibold small mb-1">Mobile Entry</div>
                    <div class="small text-body-secondary">Pick process, tap a quick preset, fill only the changed values, and submit from the sticky bar.</div>
                </div>

                <div class="card bg-body-tertiary border-0 mb-3">
                    <div class="card-body py-3">
                        <div class="d-flex flex-wrap gap-2">
                            @foreach(['SMAW', 'GMAW', 'SAW'] as $quickProcess)
                                <button type="button" class="btn btn-sm btn-outline-primary js-weld-set" data-target="welding_process" data-value="{{ $quickProcess }}">{{ $quickProcess }}</button>
                            @endforeach
                            @foreach(['draft', 'completed', 'approved'] as $quickStatus)
                                <button type="button" class="btn btn-sm btn-outline-secondary js-weld-set" data-target="status" data-value="{{ $quickStatus }}">{{ ucfirst($quickStatus) }}</button>
                            @endforeach
                            @foreach(['CO2', 'AR + CO2', 'NA'] as $quickGas)
                                <button type="button" class="btn btn-sm btn-outline-dark js-weld-set" data-target="shielding_gas" data-value="{{ $quickGas }}">{{ $quickGas }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Execution Core</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-3">
                        <label class="form-label" for="welding_process">Process</label>
                        <select id="welding_process" name="welding_process" class="form-select" data-erp-select>
                            @foreach($weldingProcesses as $process)
                                <option value="{{ $process }}" @selected(old('welding_process', 'SMAW') === $process)>{{ $process }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="weld_date">Weld Date</label>
                        <input id="weld_date" type="date" name="weld_date" value="{{ old('weld_date', $selectedDpr?->dpr_date?->format('Y-m-d') ?: now()->format('Y-m-d')) }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="welder_id">Welder</label>
                        <select id="welder_id" name="welder_id" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select welder</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((int) old('welder_id', $selectedDpr?->worker_user_id) === (int) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="contractor_party_id">Contractor</label>
                        <select id="contractor_party_id" name="contractor_party_id" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select contractor</option>
                            @foreach($contractors as $contractor)
                                <option value="{{ $contractor->id }}" @selected((int) old('contractor_party_id', $selectedDpr?->contractor_party_id ?? $latestFitup?->contractor_party_id) === (int) $contractor->id)>{{ $contractor->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="joint_description">Joint Description</label>
                        <input id="joint_description" name="joint_description" value="{{ old('joint_description', $selectedAssembly ? ('Assembly ' . $selectedAssembly->assembly_code) : '') }}" class="form-control" maxlength="200">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="line_no">Line No</label>
                        <input id="line_no" name="line_no" value="{{ old('line_no') }}" class="form-control" maxlength="120">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="weld_size_mm">Weld Size (mm)</label>
                        <input id="weld_size_mm" type="number" step="0.001" min="0" inputmode="decimal" name="weld_size_mm" value="{{ old('weld_size_mm') }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="wpss_ref">WPS / PQR Ref</label>
                        <input id="wpss_ref" name="wpss_ref" value="{{ old('wpss_ref') }}" class="form-control" maxlength="150">
                    </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Consumables And Parameters</div>
                    <div class="card-body">
                        <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="consumable_item_id">Consumable Item</label>
                        <select id="consumable_item_id" name="consumable_item_id" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select consumable</option>
                            @foreach($consumableItems as $item)
                                <option value="{{ $item->id }}" @selected((int) old('consumable_item_id') === (int) $item->id)>{{ $item->code }} - {{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="consumable_batch">Consumable Batch</label>
                        <input id="consumable_batch" name="consumable_batch" value="{{ old('consumable_batch') }}" class="form-control" maxlength="120">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="shielding_gas">Shielding Gas</label>
                        <input id="shielding_gas" name="shielding_gas" value="{{ old('shielding_gas') }}" class="form-control" maxlength="120">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="current_amp">Current (A)</label>
                        <input id="current_amp" type="number" step="0.01" min="0" inputmode="decimal" name="current_amp" value="{{ old('current_amp') }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="voltage">Voltage</label>
                        <input id="voltage" type="number" step="0.01" min="0" inputmode="decimal" name="voltage" value="{{ old('voltage') }}" class="form-control">
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label" for="travel_speed">Travel Speed</label>
                        <input id="travel_speed" type="number" step="0.001" min="0" inputmode="decimal" name="travel_speed" value="{{ old('travel_speed') }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="heat_input">Heat Input</label>
                        <input id="heat_input" type="number" step="0.001" min="0" inputmode="decimal" name="heat_input" value="{{ old('heat_input') }}" class="form-control">
                    </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Responsibility And Close</div>
                    <div class="card-body">
                        <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="machine_id">Machine</label>
                        <select id="machine_id" name="machine_id" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select machine</option>
                            @foreach($machines as $machine)
                                <option value="{{ $machine->id }}" @selected((int) old('machine_id', $selectedDpr?->machine_id) === (int) $machine->id)>{{ $machine->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="supervisor_id">Supervisor</label>
                        <select id="supervisor_id" name="supervisor_id" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select supervisor</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((int) old('supervisor_id', $latestFitup?->supervisor_id) === (int) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="inspector_id">Inspector</label>
                        <select id="inspector_id" name="inspector_id" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select inspector</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((int) old('inspector_id', $latestFitup?->inspector_id) === (int) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select" data-erp-select>
                            @foreach($statusOptions as $status)
                                <option value="{{ $status }}" @selected(old('status', 'draft') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" class="form-control" rows="3">{{ old('remarks', $latestFitup ? 'Prepared from FU-' . $latestFitup->id : '') }}</textarea>
                    </div>
                        </div>
                    </div>
                </div>

                @if($latestFitup)
                    <div class="alert alert-secondary mt-3 mb-0">
                        Contractor, supervisor, inspector, and remarks are defaulted from the latest fit-up. Edit them only if welding is being executed under a different setup.
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger mt-3 mb-0">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="sticky-bottom bg-body py-2 border-top">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('projects.production-v2.welding-events.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary" @disabled(! $selectedAssembly)>Create Welding Event</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-weld-set').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.getElementById(button.dataset.target);
            if (!target) {
                return;
            }

            target.value = button.dataset.value || '';
            target.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    const processField = document.getElementById('welding_process');
    const gasField = document.getElementById('shielding_gas');
    const currentField = document.getElementById('current_amp');
    const voltageField = document.getElementById('voltage');

    if (processField) {
        processField.addEventListener('change', function () {
            if (processField.value === 'SMAW' && gasField && !gasField.value) {
                gasField.value = 'NA';
            }

            if (processField.value === 'GMAW' && gasField && !gasField.value) {
                gasField.value = 'AR + CO2';
            }

            if (processField.value === 'SAW' && !currentField.value) {
                currentField.value = '450';
            }

            if (processField.value === 'SAW' && !voltageField.value) {
                voltageField.value = '32';
            }
        });
    }
});
</script>
@endpush
