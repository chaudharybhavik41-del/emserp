@extends('layouts.erp')

@section('title', 'Production V2 Design Releases')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Design Releases</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('production-v2.project.design', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back To Design Module</a>
        @can('production.plan.create')
            <a href="{{ route('projects.production-v2.design-releases.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">Create Release</a>
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
                            <th>Release</th>
                            <th>Date</th>
                            <th class="text-end">Parts</th>
                            <th class="text-end">Assemblies</th>
                            <th class="text-end">Cutting Plans</th>
                            <th class="text-end">Material Reqs</th>
                            <th class="text-end">Open</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>
                                {{ $row->release_number }}
                                <div class="small text-body-secondary">{{ $row->releasedBy?->name ?: '-' }}</div>
                            </td>
                            <td>{{ $row->release_date?->format('Y-m-d') ?: '-' }}</td>
                            <td class="text-end">{{ number_format($row->parts_count) }}</td>
                            <td class="text-end">{{ number_format($row->assemblies_count) }}</td>
                            <td class="text-end">{{ number_format($row->cutting_plans_count) }}</td>
                            <td class="text-end">{{ number_format($row->material_requirements_count) }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.design-releases.show', ['project' => $project->id, 'designRelease' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No design releases yet.</td>
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
