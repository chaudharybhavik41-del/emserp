@extends('layouts.erp')

@section('title', 'Production V2 Trial Assembly')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">TA-{{ $trialAssembly->id }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.trial-assemblies.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        <a href="{{ route('projects.production-v2.inspection-events.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">Create Follow-up Inspection</a>
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Trial Date</div><div>{{ $trialAssembly->trial_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-4"><div class="small text-body-secondary">Assembly Group Ref</div><div>{{ $trialAssembly->assembly_group_ref }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Checked By</div><div>{{ $trialAssembly->checkedBy?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Inspector</div><div>{{ $trialAssembly->inspector?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-1"><div class="small text-body-secondary">Status</div><div>{{ $trialAssembly->status }}</div></div>
                <div class="col-12 col-md-2">
                    <div class="small text-body-secondary">Daily DPR</div>
                    <div>
                        @if($trialAssembly->dpr)
                            <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $trialAssembly->dpr->id]) }}">DPR-{{ $trialAssembly->dpr->id }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12">
                    <div class="small text-body-secondary">Linked Assemblies</div>
                    <div>
                        @forelse($trialAssembly->assemblies as $assembly)
                            <span class="badge text-bg-light border">{{ $assembly->assembly_code }}</span>
                        @empty
                            -
                        @endforelse
                    </div>
                </div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $trialAssembly->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Measurement Register</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Parameter</th>
                            <th>Required</th>
                            <th>Tolerance</th>
                            <th>Actual</th>
                            <th>Assembly Ref</th>
                            <th>OK</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($trialAssembly->measurements as $row)
                        <tr>
                            <td>{{ $row->parameter_name }}</td>
                            <td>{{ $row->required_dimension ?: '-' }}</td>
                            <td>{{ $row->tolerance ?: '-' }}</td>
                            <td>{{ $row->actual_dimension ?: '-' }}</td>
                            <td>{{ $row->assembly?->assembly_code ?: ($row->assembly_ref ?: '-') }}</td>
                            <td>{{ is_null($row->ok_status) ? '-' : ($row->ok_status ? 'Yes' : 'No') }}</td>
                            <td>{{ $row->remarks ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No measurement rows found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
