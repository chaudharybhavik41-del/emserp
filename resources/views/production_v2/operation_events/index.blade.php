@extends('layouts.erp')

@section('title', 'Production V2 Operation Events')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Operation Events</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('production-v2.project', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back To Production Module</a>
        <a href="{{ route('projects.production-v2.operation-events.create', ['project' => $project->id, 'operation_code' => 'drilling']) }}" class="btn btn-sm btn-primary">Record Operation</a>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'operations'])
    @include('production_v2.partials.mobile_list_styles')

    <div class="row g-3 mb-3">
        @forelse($summary->take(4) as $summaryRow)
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="small text-uppercase text-body-secondary mb-1">{{ $summaryRow->operationMaster?->name ?: 'Operation' }}</div>
                        <div class="display-6 mb-0">{{ number_format((int) $summaryRow->total_rows) }}</div>
                        <div class="small text-body-secondary mt-1">Qty {{ number_format((float) $summaryRow->total_qty, 3) }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-light border mb-0">No generic route operations logged yet.</div>
            </div>
        @endforelse
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Operation</th>
                            <th>Target</th>
                            <th>Date</th>
                            <th class="text-end">Qty</th>
                            <th>Status</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>
                                <div>{{ $row->operationMaster?->name ?: '-' }}</div>
                                <div class="small text-body-secondary">{{ $row->worker?->name ?: 'No worker' }}</div>
                            </td>
                            <td>{{ $row->partDefinition?->part_code ?: $row->assembly?->assembly_code ?: '-' }}</td>
                            <td>{{ $row->operation_date?->format('Y-m-d') ?: '-' }}</td>
                            <td class="text-end">{{ number_format((float) $row->qty, 3) }}</td>
                            <td><span class="badge text-bg-light border">{{ ucfirst($row->status) }}</span></td>
                            <td class="text-end"><a href="{{ route('projects.production-v2.operation-events.show', ['project' => $project->id, 'operationEvent' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No generic operation events captured yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pv2-mobile-list">
                @forelse($rows as $row)
                    <div class="pv2-mobile-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold">{{ $row->operationMaster?->name ?: '-' }}</div>
                                <div class="small text-body-secondary">{{ $row->partDefinition?->part_code ?: $row->assembly?->assembly_code ?: '-' }}</div>
                            </div>
                            <span class="badge text-bg-light border">{{ ucfirst($row->status) }}</span>
                        </div>
                        <div class="small text-body-secondary mt-2">{{ $row->operation_date?->format('Y-m-d') ?: '-' }} | Qty {{ number_format((float) $row->qty, 3) }} | {{ $row->worker?->name ?: 'No worker' }}</div>
                        <div class="mt-3"><a href="{{ route('projects.production-v2.operation-events.show', ['project' => $project->id, 'operationEvent' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a></div>
                    </div>
                @empty
                    <div class="p-4 text-center text-muted">No generic operation events captured yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $rows->links() }}
    </div>
@endsection
