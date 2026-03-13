@extends('layouts.erp')

@section('title', 'Create Production V2 QC Gate')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Record QC Gate</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'qc_gates'])

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="alert alert-info">
                        This QC gate belongs to the completed route step for <strong>{{ $targetLabel ?: 'selected target' }}</strong>. The next route step will stay blocked until this gate is passed.
                    </div>

                    <form method="POST" action="{{ route('projects.production-v2.qc-gates.store', ['project' => $project->id]) }}" data-pwa-sync="critical">
                        @csrf
                        @if($partRouteStep)
                            <input type="hidden" name="part_route_step_id" value="{{ $partRouteStep->id }}">
                        @endif
                        @if($assemblyRouteStep)
                            <input type="hidden" name="assembly_route_step_id" value="{{ $assemblyRouteStep->id }}">
                        @endif

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Process</label>
                                <input class="form-control" value="{{ $assemblyRouteStep?->operation_name ?: $partRouteStep?->operation_name ?: '-' }}" disabled>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Gate Mode</label>
                                <select name="gate_mode" class="form-select" data-erp-select data-hide-search="true">
                                    @foreach($gateModes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('gate_mode', $assemblyRouteStep?->qc_gate_mode ?: $partRouteStep?->qc_gate_mode) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Gate Type</label>
                                <select name="gate_type" class="form-select" data-erp-select data-hide-search="true">
                                    @foreach($gateTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('gate_type', $assemblyRouteStep?->qc_gate_type ?: $partRouteStep?->qc_gate_type) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Gate Date</label>
                                <input type="date" name="gate_date" class="form-control" value="{{ old('gate_date', now()->toDateString()) }}" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Result</label>
                                <select name="result" class="form-select" data-erp-select data-hide-search="true">
                                    @foreach($gateResults as $value => $label)
                                        <option value="{{ $value }}" @selected(old('result', 'passed') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Checked By</label>
                                <select name="checked_by" class="form-select" data-erp-select data-allow-clear="1">
                                    <option value="">Select user</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" @selected((string) old('checked_by') === (string) $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Inspector Agency</label>
                                <input name="inspector_agency" class="form-control" value="{{ old('inspector_agency') }}">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Reference No</label>
                                <input name="reference_no" class="form-control" value="{{ old('reference_no') }}" placeholder="report / offer ref">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" rows="3" class="form-control">{{ old('remarks', $assemblyRouteStep?->qc_gate_remarks ?: $partRouteStep?->qc_gate_remarks) }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('projects.production-v2.qc-gates.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save QC Gate</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header">Recent Gates For This Step</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Result</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($recentRows as $row)
                                <tr>
                                    <td>{{ $row->gate_date?->format('Y-m-d') ?: '-' }}</td>
                                    <td>{{ strtoupper($row->result) }}</td>
                                    <td>{{ $row->checkedBy?->name ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">No prior gate rows for this route step.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
