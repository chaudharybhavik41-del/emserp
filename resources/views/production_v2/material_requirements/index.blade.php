@extends('layouts.erp')

@section('title', 'Production V2 Material Requirements')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Material Requirements</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('production-v2.project.design', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back To Design Module</a>
        @can('production.plan.create')
            <a href="{{ route('projects.production-v2.material-requirements.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">Create Material Requirement</a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Number</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Rows</th>
                            <th>Release</th>
                            <th class="text-end">Open</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->requirement_number }}</td>
                            <td>{{ $row->requirement_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $row->status }}</td>
                            <td class="text-end">{{ number_format($row->items_count) }}</td>
                            <td>{{ $row->designRelease?->release_number ?: '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.material-requirements.show', ['project' => $project->id, 'materialRequirement' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No material requirements yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($rows instanceof \Illuminate\Pagination\AbstractPaginator)
            <div class="card-footer">{{ $rows->links() }}</div>
        @endif
    </div>
@endsection
