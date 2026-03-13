@extends('layouts.erp')

@section('title', 'Create Production V2 Inspection Event')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Create Production V2 Inspection Event</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @if(! $selectedDpr)
        <div class="alert alert-light border">
            Start from Daily DPR when possible so inspection stays linked to the day’s QC activity.
            <a href="{{ route('projects.production-v2.dprs.create', ['project' => $project->id, 'activity' => 'inspection']) }}" class="alert-link">Create Inspection DPR</a>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end mb-4">
                @if($selectedDpr)
                    <input type="hidden" name="dpr_id" value="{{ $selectedDpr->id }}">
                @endif
                <div class="col-12 col-lg-5">
                    <label class="form-label mb-1" for="assembly_id">Assembly</label>
                    <select id="assembly_id" name="assembly_id" class="form-select" data-erp-select>
                        <option value="">Select assembly</option>
                        @foreach($assemblies as $assembly)
                            <option value="{{ $assembly->id }}" @selected((int) request('assembly_id') === (int) $assembly->id)>
                                {{ $assembly->assembly_code }} - {{ $assembly->assembly_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-5">
                    <label class="form-label mb-1" for="welding_event_id">Related Welding Event</label>
                    <select id="welding_event_id" name="welding_event_id" class="form-select" data-erp-select data-allow-clear="1">
                        <option value="">Select welding event</option>
                        @foreach($weldingEvents as $event)
                            <option value="{{ $event->id }}" @selected((int) request('welding_event_id') === (int) $event->id)>
                                WE-{{ $event->id }} | {{ $event->assembly?->assembly_code ?: '-' }} | {{ $event->welding_process }} | {{ $event->weld_date?->format('Y-m-d') ?: '-' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100">Load</button>
                    <a href="{{ route('projects.production-v2.inspection-events.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary w-100">Cancel</a>
                </div>
            </form>

            @if($selectedAssembly || $selectedWeldingEvent || $selectedReworkEvent)
                <div class="card bg-light-subtle border-0 mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            @if($selectedAssembly)
                                <div class="col-12 col-md-4">
                                    <div class="small text-body-secondary">Assembly</div>
                                    <div>{{ $selectedAssembly->assembly_code }}</div>
                                    <div class="small text-body-secondary">{{ $selectedAssembly->assembly_name }}</div>
                                </div>
                                <div class="col-12 col-md-2"><div class="small text-body-secondary">Fit-ups</div><div>{{ number_format($selectedAssembly->fitups_count) }}</div></div>
                                <div class="col-12 col-md-2"><div class="small text-body-secondary">Welding Events</div><div>{{ number_format($selectedAssembly->welding_events_count) }}</div></div>
                                <div class="col-12 col-md-2"><div class="small text-body-secondary">Inspections</div><div>{{ number_format($selectedAssembly->inspection_events_count) }}</div></div>
                                <div class="col-12 col-md-2"><div class="small text-body-secondary">Rework Events</div><div>{{ number_format($selectedAssembly->rework_events_count) }}</div></div>
                            @endif
                            @if($selectedWeldingEvent)
                                <div class="col-12 col-md-2">
                                    <div class="small text-body-secondary">Selected Weld</div>
                                    <div>WE-{{ $selectedWeldingEvent->id }}</div>
                                    <div class="small text-body-secondary">{{ $selectedWeldingEvent->welding_process }}</div>
                                </div>
                                <div class="col-12 col-md-2">
                                    <div class="small text-body-secondary">Welder</div>
                                    <div>{{ $selectedWeldingEvent->welder?->name ?: '-' }}</div>
                                    <div class="small text-body-secondary">{{ $selectedWeldingEvent->weld_date?->format('Y-m-d') ?: '-' }}</div>
                                </div>
                                <div class="col-12 col-md-2">
                                    <div class="small text-body-secondary">Consumable</div>
                                    <div>{{ $selectedWeldingEvent->consumableItem?->code ?: '-' }}</div>
                                    <div class="small text-body-secondary">{{ $selectedWeldingEvent->consumable_batch ?: '-' }}</div>
                                </div>
                            @endif
                            @if($selectedReworkEvent)
                                <div class="col-12 col-md-3">
                                    <div class="small text-body-secondary">Source Rework</div>
                                    <div>RW-{{ $selectedReworkEvent->id }}</div>
                                    <div class="small text-body-secondary">Current result: {{ $selectedReworkEvent->final_result ?: '-' }}</div>
                                </div>
                            @endif
                        </div>
                        @if($latestFitup && $fitupConsumptionSummary->isNotEmpty())
                            <hr>
                            <div class="small text-body-secondary mb-2">Latest Fit-up Traceability</div>
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
                        @if($selectedReworkEvent && $selectedReworkEvent->sourceInspection)
                            <hr>
                            <div class="small text-body-secondary mb-2">Rework Closure Context</div>
                            <div class="row g-3">
                                <div class="col-12 col-md-3"><div class="small text-body-secondary">Failed Inspection</div><div>IN-{{ $selectedReworkEvent->sourceInspection->id }}</div></div>
                                <div class="col-12 col-md-3"><div class="small text-body-secondary">Inspection Result</div><div>{{ $selectedReworkEvent->sourceInspection->result ?: '-' }}</div></div>
                                <div class="col-12 col-md-3"><div class="small text-body-secondary">Checked By</div><div>{{ $selectedReworkEvent->sourceInspection->checkedBy?->name ?: '-' }}</div></div>
                                <div class="col-12"><div class="small text-body-secondary">Rework Action</div><div>{{ $selectedReworkEvent->action_taken ?: '-' }}</div></div>
                            </div>
                        @endif
                        @if($selectedWeldingEvent && $selectedWeldingEvent->inspections->isNotEmpty())
                            <hr>
                            <div class="small text-body-secondary mb-2">Existing Inspections For This Weld</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Inspection</th>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Result</th>
                                            <th>Checked By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($selectedWeldingEvent->inspections as $inspection)
                                            <tr>
                                                <td>IN-{{ $inspection->id }}</td>
                                                <td>{{ $inspection->inspection_date?->format('Y-m-d') ?: '-' }}</td>
                                                <td>{{ strtoupper($inspection->inspection_type) }}</td>
                                                <td>{{ $inspection->result ?: '-' }}</td>
                                                <td>{{ $inspection->checkedBy?->name ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('projects.production-v2.inspection-events.store', ['project' => $project->id]) }}" data-pwa-sync="critical">
                @csrf
                <input type="hidden" name="related_dpr_id" value="{{ old('related_dpr_id', $selectedDpr?->id) }}">
                <input type="hidden" name="assembly_id" value="{{ old('assembly_id', $selectedAssembly?->id ?? $selectedWeldingEvent?->assembly_id) }}">
                <input type="hidden" name="source_rework_event_id" value="{{ old('source_rework_event_id', $selectedReworkEvent?->id) }}">

                @if($selectedDpr)
                    <div class="alert alert-primary">
                        Using DPR-{{ $selectedDpr->id }} from {{ $selectedDpr->dpr_date?->format('Y-m-d') ?: '-' }}.
                        {{ $selectedDpr->worker?->name ? 'Checked by default: ' . $selectedDpr->worker->name . '.' : '' }}
                    </div>
                @endif

                <div class="alert alert-light border mb-3 d-md-none">
                    <div class="fw-semibold small mb-1">Mobile Entry</div>
                    <div class="small text-body-secondary">Tap inspection type and result, then only fill defect and repair fields when the result is not passed.</div>
                </div>

                <div class="card bg-body-tertiary border-0 mb-3">
                    <div class="card-body py-3">
                        <div class="d-flex flex-wrap gap-2">
                            @foreach(['visual' => 'Visual', 'dpt' => 'DPT', 'ut' => 'UT', 'final' => 'Final'] as $quickTypeValue => $quickTypeLabel)
                                <button type="button" class="btn btn-sm btn-outline-primary js-inspection-set" data-target="inspection_type" data-value="{{ $quickTypeValue }}">{{ $quickTypeLabel }}</button>
                            @endforeach
                            @foreach(['passed' => 'Passed', 'failed' => 'Failed', 'reoffer' => 'Reoffer'] as $quickResultValue => $quickResultLabel)
                                <button type="button" class="btn btn-sm btn-outline-secondary js-inspection-set" data-target="result" data-value="{{ $quickResultValue }}">{{ $quickResultLabel }}</button>
                            @endforeach
                            @foreach(['porosity' => 'Porosity', 'undercut' => 'Undercut', 'crack' => 'Crack', 'dimension' => 'Dimension'] as $quickDefectValue => $quickDefectLabel)
                                <button type="button" class="btn btn-sm btn-outline-dark js-inspection-set" data-target="defect_type" data-value="{{ $quickDefectValue }}">{{ $quickDefectLabel }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="inspection_type">Inspection Type</label>
                        <select id="inspection_type" name="inspection_type" class="form-select" data-erp-select>
                            @foreach($inspectionTypes as $type)
                                <option value="{{ $type }}" @selected(old('inspection_type', 'visual') === $type)>{{ strtoupper($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="inspection_date">Inspection Date</label>
                        <input id="inspection_date" type="date" name="inspection_date" value="{{ old('inspection_date', $selectedDpr?->dpr_date?->format('Y-m-d') ?: now()->format('Y-m-d')) }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="result">Result</label>
                        <select id="result" name="result" class="form-select" data-erp-select>
                            @foreach($resultOptions as $result)
                                <option value="{{ $result }}" @selected(old('result', 'passed') === $result)>{{ ucfirst($result) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="related_welding_event_id">Related Weld</label>
                        <select id="related_welding_event_id" name="related_welding_event_id" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select welding event</option>
                            @foreach($weldingEvents as $event)
                                <option value="{{ $event->id }}" @selected((int) old('related_welding_event_id', $selectedWeldingEvent?->id) === (int) $event->id)>
                                    WE-{{ $event->id }} | {{ $event->assembly?->assembly_code ?: '-' }} | {{ $event->welding_process }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="line_no">Line No</label>
                        <input id="line_no" name="line_no" value="{{ old('line_no', $selectedWeldingEvent?->line_no) }}" class="form-control" maxlength="120">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="defect_type">Defect Type</label>
                        <input id="defect_type" name="defect_type" value="{{ old('defect_type') }}" class="form-control" maxlength="120">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="reoffer_no">Reoffer No</label>
                        <input id="reoffer_no" name="reoffer_no" value="{{ old('reoffer_no', $selectedReworkEvent?->sourceInspection?->reoffer_no) }}" class="form-control" maxlength="120">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="retest_result">Retest Result</label>
                        <input id="retest_result" name="retest_result" value="{{ old('retest_result', $selectedReworkEvent?->sourceInspection?->result) }}" class="form-control" maxlength="60">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="checked_by">Checked By</label>
                        <select id="checked_by" name="checked_by" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select user</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((int) old('checked_by', $selectedDpr?->worker_user_id ?? $selectedWeldingEvent?->inspector_id) === (int) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="inspector_agency">Inspector Agency</label>
                        <input id="inspector_agency" name="inspector_agency" value="{{ old('inspector_agency') }}" class="form-control" maxlength="150">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="defect_description">Defect Description</label>
                        <textarea id="defect_description" name="defect_description" class="form-control" rows="3">{{ old('defect_description', $selectedReworkEvent?->sourceInspection?->defect_description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="repair_action">Repair Action</label>
                        <textarea id="repair_action" name="repair_action" class="form-control" rows="3">{{ old('repair_action', $selectedReworkEvent?->action_taken) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" class="form-control" rows="3">{{ old('remarks', $selectedWeldingEvent ? 'Inspection against WE-' . $selectedWeldingEvent->id : '') }}</textarea>
                    </div>
                </div>

                @if($selectedWeldingEvent)
                    <div class="alert alert-secondary mt-3 mb-0">
                        Checked by and remarks are prefilled from the selected welding context. Change them only if inspection is being executed under different responsibility.
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
                        <a href="{{ route('projects.production-v2.inspection-events.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary" @disabled(! ($selectedAssembly || $selectedWeldingEvent))>Create Inspection Event</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-inspection-set').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.getElementById(button.dataset.target);
            if (!target) {
                return;
            }

            target.value = button.dataset.value || '';
            target.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    const resultField = document.getElementById('result');
    const reofferField = document.getElementById('reoffer_no');
    const defectField = document.getElementById('defect_description');
    if (resultField) {
        resultField.addEventListener('change', function () {
            if (resultField.value === 'reoffer' && reofferField && !reofferField.value) {
                reofferField.focus();
                return;
            }

            if (['failed', 'hold'].includes(resultField.value) && defectField && !defectField.value) {
                defectField.focus();
            }
        });
    }
});
</script>
@endpush
