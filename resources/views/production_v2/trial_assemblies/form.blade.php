@extends('layouts.erp')

@section('title', 'Create Production V2 Trial Assembly')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Create Production V2 Trial Assembly</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    <div class="alert alert-info">
        Trial assembly records grouped checks across one or more assemblies. Use `Assembly Group Ref` for the combined setup and record dimension/fit measurements line by line.
    </div>

    @if(! $selectedDpr)
        <div class="alert alert-light border">
            Start from Daily DPR when possible so grouped checks stay linked to the same day record.
            <a href="{{ route('projects.production-v2.dprs.create', ['project' => $project->id, 'activity' => 'trial_assembly']) }}" class="alert-link">Create Trial DPR</a>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end mb-4">
                @if($selectedDpr)
                    <input type="hidden" name="dpr_id" value="{{ $selectedDpr->id }}">
                @endif
                <div class="col-12 col-lg-9">
                    <label class="form-label mb-1" for="assembly_ids">Assemblies</label>
                    <select id="assembly_ids" name="assembly_ids[]" class="form-select" multiple data-erp-select>
                        @foreach($assemblies as $assembly)
                            <option value="{{ $assembly->id }}" @selected($selectedAssemblies->contains('id', $assembly->id))>
                                {{ $assembly->assembly_code }} - {{ $assembly->assembly_name }} (insp: {{ $assembly->inspection_events_count }}, rework: {{ $assembly->rework_events_count }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-3 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100">Load Assemblies</button>
                    <a href="{{ route('projects.production-v2.trial-assemblies.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary w-100">Cancel</a>
                </div>
            </form>

            @if($selectedAssemblies->isNotEmpty())
                <div class="card bg-light-subtle border-0 mb-4">
                    <div class="card-body">
                        <div class="small text-body-secondary mb-2">Selected Assemblies</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($selectedAssemblies as $assembly)
                                <span class="badge text-bg-light border">{{ $assembly->assembly_code }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('projects.production-v2.trial-assemblies.store', ['project' => $project->id]) }}" data-pwa-sync="critical">
                @csrf
                <input type="hidden" name="dpr_id" value="{{ old('dpr_id', $selectedDpr?->id) }}">
                @foreach($selectedAssemblies as $assembly)
                    <input type="hidden" name="assembly_ids[]" value="{{ $assembly->id }}">
                @endforeach

                @if($selectedDpr)
                    <div class="alert alert-primary">
                        Using DPR-{{ $selectedDpr->id }} from {{ $selectedDpr->dpr_date?->format('Y-m-d') ?: '-' }}.
                    </div>
                @endif

                <div class="alert alert-light border mb-3 d-md-none">
                    <div class="fw-semibold small mb-1">Mobile Entry</div>
                    <div class="small text-body-secondary">Measurement rows stack as cards on phones. Pick the parameter, set OK or Not OK, and move to the next row without scrolling sideways.</div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="assembly_group_ref">Assembly Group Ref</label>
                        <input id="assembly_group_ref" name="assembly_group_ref" value="{{ old('assembly_group_ref', $defaultGroupRef) }}" class="form-control" maxlength="150">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="trial_date">Trial Date</label>
                        <input id="trial_date" type="date" name="trial_date" value="{{ old('trial_date', $selectedDpr?->dpr_date?->format('Y-m-d') ?: now()->format('Y-m-d')) }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="checked_by">Checked By</label>
                        <select id="checked_by" name="checked_by" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select user</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((int) old('checked_by', $selectedDpr?->worker_user_id) === (int) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="inspector_id">Inspector</label>
                        <select id="inspector_id" name="inspector_id" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select user</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((int) old('inspector_id') === (int) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select" data-erp-select>
                            @foreach($statusOptions as $status)
                                <option value="{{ $status }}" @selected(old('status', 'draft') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" class="form-control" rows="3">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                @php
                    $oldRows = old('rows', []);
                    $rows = count($oldRows) > 0
                        ? $oldRows
                        : [
                            ['parameter_name' => 'Alignment', 'required_dimension' => '', 'tolerance' => '', 'actual_dimension' => '', 'assembly_id' => $selectedAssemblies->count() === 1 ? $selectedAssemblies->first()->id : '', 'assembly_ref' => $selectedAssemblies->pluck('assembly_code')->implode(' + '), 'ok_status' => '', 'remarks' => ''],
                            ['parameter_name' => 'Camber', 'required_dimension' => '', 'tolerance' => '', 'actual_dimension' => '', 'assembly_id' => $selectedAssemblies->count() === 1 ? $selectedAssemblies->first()->id : '', 'assembly_ref' => $selectedAssemblies->pluck('assembly_code')->implode(' + '), 'ok_status' => '', 'remarks' => ''],
                            ['parameter_name' => 'Hole Matching', 'required_dimension' => '', 'tolerance' => '', 'actual_dimension' => '', 'assembly_id' => $selectedAssemblies->count() === 1 ? $selectedAssemblies->first()->id : '', 'assembly_ref' => $selectedAssemblies->pluck('assembly_code')->implode(' + '), 'ok_status' => '', 'remarks' => ''],
                        ];
                @endphp

                <div class="card mt-4">
                    <div class="card-header">Measurements</div>
                    <div class="px-3 pt-3 pb-2 border-bottom bg-body-tertiary">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-success js-trial-action" data-action="all-ok">All OK</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-trial-action" data-action="clear-ok">Clear OK</button>
                            <button type="button" class="btn btn-sm btn-outline-primary js-trial-action" data-action="all-group">Set Group Scope</button>
                            <button type="button" class="btn btn-sm btn-outline-dark js-trial-action" data-action="copy-required">Copy Required To Actual</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 mobile-entry-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Required</th>
                                        <th>Tolerance</th>
                                        <th>Actual</th>
                                        <th>Assembly</th>
                                        <th>OK</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($rows as $index => $row)
                                    <tr>
                                        <td data-label="Parameter"><input name="rows[{{ $index }}][parameter_name]" value="{{ $row['parameter_name'] ?? '' }}" class="form-control" maxlength="150"></td>
                                        <td data-label="Required"><input name="rows[{{ $index }}][required_dimension]" value="{{ $row['required_dimension'] ?? '' }}" class="form-control" maxlength="150"></td>
                                        <td data-label="Tolerance"><input name="rows[{{ $index }}][tolerance]" value="{{ $row['tolerance'] ?? '' }}" class="form-control" maxlength="120"></td>
                                        <td data-label="Actual"><input name="rows[{{ $index }}][actual_dimension]" value="{{ $row['actual_dimension'] ?? '' }}" class="form-control" maxlength="150"></td>
                                        <td data-label="Assembly">
                                            <input type="hidden" name="rows[{{ $index }}][assembly_ref]" value="{{ $row['assembly_ref'] ?? '' }}">
                                            <select name="rows[{{ $index }}][assembly_id]" class="form-select" data-erp-select data-allow-clear="1">
                                                <option value="">Combined / Group</option>
                                                @foreach($selectedAssemblies as $assembly)
                                                    <option value="{{ $assembly->id }}" @selected((string) ($row['assembly_id'] ?? '') === (string) $assembly->id)>{{ $assembly->assembly_code }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td data-label="OK">
                                            <select name="rows[{{ $index }}][ok_status]" class="form-select" data-erp-select data-hide-search="true">
                                                <option value="" @selected(($row['ok_status'] ?? '') === '')>-</option>
                                                <option value="1" @selected((string) ($row['ok_status'] ?? '') === '1')>Yes</option>
                                                <option value="0" @selected((string) ($row['ok_status'] ?? '') === '0')>No</option>
                                            </select>
                                        </td>
                                        <td data-label="Remarks"><input name="rows[{{ $index }}][remarks]" value="{{ $row['remarks'] ?? '' }}" class="form-control"></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
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

                <div class="sticky-bottom bg-body py-2 border-top">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('projects.production-v2.trial-assemblies.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary">Create Trial Assembly</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
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
        color: var(--bs-secondary-color);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-trial-action').forEach(function (button) {
        button.addEventListener('click', function () {
            const action = button.dataset.action;

            document.querySelectorAll('select[name^="rows["][name$="[ok_status]"]').forEach(function (select) {
                if (action === 'all-ok') {
                    select.value = '1';
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (action === 'clear-ok') {
                    select.value = '';
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            if (action === 'all-group') {
                document.querySelectorAll('select[name^="rows["][name$="[assembly_id]"]').forEach(function (select) {
                    select.value = '';
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }

            if (action === 'copy-required') {
                document.querySelectorAll('input[name^="rows["][name$="[required_dimension]"]').forEach(function (requiredField) {
                    const actualField = document.querySelector(requiredField.name.replace('[required_dimension]', '[actual_dimension]'));
                    if (actualField && !actualField.value) {
                        actualField.value = requiredField.value;
                    }
                });
            }
        });
    });
});
</script>
@endpush
