@extends('layouts.erp')

@section('title', 'Production V2 Operation Masters')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Process Masters</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'operation_masters'])

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('projects.production-v2.operation-masters.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">Create Process</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Total</div><div class="display-6 mb-0">{{ number_format($summary['total']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Part</div><div class="display-6 mb-0">{{ number_format($summary['part']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Assembly</div><div class="display-6 mb-0">{{ number_format($summary['assembly']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">QC Default</div><div class="display-6 mb-0">{{ number_format($summary['qc_default']) }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Applies To</th>
                            <th>Entry</th>
                            <th>QC Default</th>
                            <th>Status</th>
                            <th class="text-end">Open</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><span class="fw-semibold">{{ $row->code }}</span></td>
                            <td>{{ $row->name }}</td>
                            <td>{{ ucfirst($row->applies_to) }}</td>
                            <td>{{ ucfirst($row->entry_mode) }}</td>
                            <td>
                                @if($row->requires_qc)
                                    <span class="badge text-bg-warning">Yes</span>
                                @else
                                    <span class="badge text-bg-secondary">No</span>
                                @endif
                            </td>
                            <td>
                                @if($row->is_active)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end"><a href="{{ route('projects.production-v2.operation-masters.show', ['project' => $project->id, 'operationMaster' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No process masters found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if(method_exists($rows, 'links'))
            <div class="card-footer">{{ $rows->links() }}</div>
        @endif
    </div>
@endsection
