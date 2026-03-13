@extends('layouts.erp')

@section('title', 'Create Production V2 Rework Event')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Create Production V2 Rework Event</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    <div class="alert alert-warning">
        Rework is a follow-up flow for failed, re-offer, or hold inspections. Capture the repair action here, then raise a fresh inspection after rework is completed.
    </div>

    @if(! $selectedDpr)
        <div class="alert alert-light border">
            Start from Daily DPR when possible so rework stays linked to the same execution day record.
            <a href="{{ route('projects.production-v2.dprs.create', ['project' => $project->id, 'activity' => 'rework']) }}" class="alert-link">Create Rework DPR</a>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end mb-4">
                @if($selectedDpr)
                    <input type="hidden" name="dpr_id" value="{{ $selectedDpr->id }}">
                @endif
                <div class="col-12 col-lg-8">
                    <label class="form-label mb-1" for="inspection_event_id">Source Inspection</label>
                    <select id="inspection_event_id" name="inspection_event_id" class="form-select" data-erp-select>
                        <option value="">Select inspection</option>
                        @foreach($candidateInspections as $inspection)
                            <option value="{{ $inspection->id }}" @selected((int) request('inspection_event_id') === (int) $inspection->id)>
                                IN-{{ $inspection->id }} | {{ $inspection->assembly?->assembly_code ?: '-' }} | {{ strtoupper($inspection->inspection_type) }} | {{ $inspection->result ?: '-' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-4 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100">Load Inspection</button>
                    <a href="{{ route('projects.production-v2.rework-events.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary w-100">Cancel</a>
                </div>
            </form>

            @if($selectedInspection)
                <div class="card bg-light-subtle border-0 mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-3">
                                <div class="small text-body-secondary">Inspection</div>
                                <div>IN-{{ $selectedInspection->id }}</div>
                                <div class="small text-body-secondary">{{ strtoupper($selectedInspection->inspection_type) }}</div>
                            </div>
                            <div class="col-12 col-md-3">
                                <div class="small text-body-secondary">Assembly</div>
                                <div>{{ $selectedInspection->assembly?->assembly_code ?: '-' }}</div>
                            </div>
                            <div class="col-12 col-md-2">
                                <div class="small text-body-secondary">Result</div>
                                <div>{{ $selectedInspection->result ?: '-' }}</div>
                            </div>
                            <div class="col-12 col-md-2">
                                <div class="small text-body-secondary">Checked By</div>
                                <div>{{ $selectedInspection->checkedBy?->name ?: '-' }}</div>
                            </div>
                            <div class="col-12 col-md-2">
                                <div class="small text-body-secondary">Existing Rework</div>
                                <div>{{ number_format($selectedInspection->reworkEvents->count()) }}</div>
                            </div>
                            <div class="col-12">
                                <div class="small text-body-secondary">Defect Description</div>
                                <div>{{ $selectedInspection->defect_description ?: '-' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="small text-body-secondary">Previous Repair Action</div>
                                <div>{{ $selectedInspection->repair_action ?: '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($selectedInspection && ! $canCreateRework)
                <div class="alert alert-info">
                    A rework event already exists for this inspection. Use the follow-up failed re-inspection as the source if another rework cycle is needed.
                </div>
            @endif

            <form method="POST" action="{{ route('projects.production-v2.rework-events.store', ['project' => $project->id]) }}" data-pwa-sync="critical">
                @csrf
                <input type="hidden" name="rework_dpr_id" value="{{ old('rework_dpr_id', $selectedDpr?->id) }}">
                <input type="hidden" name="assembly_id" value="{{ old('assembly_id', $selectedAssembly?->id ?? $selectedInspection?->assembly_id) }}">
                <input type="hidden" name="source_inspection_event_id" value="{{ old('source_inspection_event_id', $selectedInspection?->id) }}">

                @if($selectedDpr)
                    <div class="alert alert-primary">
                        Using DPR-{{ $selectedDpr->id }} from {{ $selectedDpr->dpr_date?->format('Y-m-d') ?: '-' }}.
                    </div>
                @endif

                <div class="alert alert-light border mb-3 d-md-none">
                    <div class="fw-semibold small mb-1">Mobile Entry</div>
                    <div class="small text-body-secondary">Pick reason, mark pending or passed, then capture the repair action in one short note.</div>
                </div>

                <div class="card bg-body-tertiary border-0 mb-3">
                    <div class="card-body py-3">
                        <div class="d-flex flex-wrap gap-2">
                            @foreach(['weld_defect' => 'Weld Defect', 'dimension' => 'Dimension', 'alignment' => 'Alignment', 'other' => 'Other'] as $quickReasonValue => $quickReasonLabel)
                                <button type="button" class="btn btn-sm btn-outline-primary js-rework-set" data-target="reason_code" data-value="{{ $quickReasonValue }}">{{ $quickReasonLabel }}</button>
                            @endforeach
                            @foreach(['pending' => 'Pending', 'passed' => 'Passed', 'reoffer' => 'Reoffer'] as $quickResultValue => $quickResultLabel)
                                <button type="button" class="btn btn-sm btn-outline-secondary js-rework-set" data-target="final_result" data-value="{{ $quickResultValue }}">{{ $quickResultLabel }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="rework_date">Rework Date</label>
                        <input id="rework_date" type="date" name="rework_date" value="{{ old('rework_date', $selectedDpr?->dpr_date?->format('Y-m-d') ?: now()->format('Y-m-d')) }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="reason_code">Reason Code</label>
                        <select id="reason_code" name="reason_code" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select reason</option>
                            @foreach($reasonCodes as $code)
                                <option value="{{ $code }}" @selected(old('reason_code') === $code)>{{ strtoupper(str_replace('_', ' ', $code)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="reoffer_date">Re-offer Date</label>
                        <input id="reoffer_date" type="date" name="reoffer_date" value="{{ old('reoffer_date') }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="final_result">Final Result</label>
                        <select id="final_result" name="final_result" class="form-select" data-erp-select>
                            @foreach($resultOptions as $option)
                                <option value="{{ $option }}" @selected(old('final_result', 'pending') === $option)>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="reason_description">Reason Description</label>
                        <textarea id="reason_description" name="reason_description" class="form-control" rows="3">{{ old('reason_description', $selectedInspection?->defect_description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="action_taken">Action Taken</label>
                        <textarea id="action_taken" name="action_taken" class="form-control" rows="4">{{ old('action_taken', $selectedInspection?->repair_action) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" class="form-control" rows="3">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                @if($errors->any())
                    <div class="alert alert-danger mt-3 mb-0">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="sticky-bottom bg-body py-2 border-top mt-4">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('projects.production-v2.rework-events.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary" @disabled(! $selectedInspection || ! $canCreateRework)>Create Rework Event</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-rework-set').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.getElementById(button.dataset.target);
            if (!target) {
                return;
            }

            target.value = button.dataset.value || '';
            target.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
});
</script>
@endpush
