@extends('layouts.erp')

@section('title', 'Production V2 QC Gates')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">QC Gates</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'qc_gates'])

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Total</div><div class="display-6 mb-0">{{ number_format($summary['total']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Passed</div><div class="display-6 mb-0">{{ number_format($summary['passed']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Hold / Re-offer</div><div class="display-6 mb-0">{{ number_format($summary['hold']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Failed</div><div class="display-6 mb-0">{{ number_format($summary['failed']) }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Target</th>
                            <th>Process</th>
                            <th>Gate</th>
                            <th>Result</th>
                            <th class="text-end">Open</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->gate_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $row->assembly?->assembly_code ?: $row->partDefinition?->part_code ?: '-' }}</td>
                            <td>{{ $row->operationMaster?->name ?: '-' }}</td>
                            <td>{{ strtoupper(str_replace('_', ' ', $row->gate_type ?: '-')) }}</td>
                            <td><span class="badge {{ $row->result === 'passed' ? 'text-bg-success' : ($row->result === 'failed' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ strtoupper($row->result) }}</span></td>
                            <td class="text-end"><a href="{{ route('projects.production-v2.qc-gates.show', ['project' => $project->id, 'qcGate' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No QC gates recorded yet.</td></tr>
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
