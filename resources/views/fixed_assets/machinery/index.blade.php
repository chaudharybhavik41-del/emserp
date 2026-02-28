@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-building-gear"></i> Fixed Assets - Machinery</h2>
        @can('fixed_assets.create')
            <a href="{{ route('fixed-assets.machinery.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Machinery
            </a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('fixed-assets.machinery.index') }}" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control" placeholder="Code, name, serial" value="{{ request('q') }}">
                </div>
                <div class="col-md-3">
                    <select name="project_id" class="form-select">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ (string) request('project_id') === (string) $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
                <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-secondary"><i class="bi bi-search"></i> Filter</button>
                    <a href="{{ route('fixed-assets.machinery.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Purchase Date</th>
                        <th>Opening WDV</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($assets as $asset)
                    <tr>
                        <td><a href="{{ route('fixed-assets.machinery.show', $asset) }}"><strong>{{ $asset->asset_code }}</strong></a></td>
                        <td>{{ $asset->name }}</td>
                        <td>{{ $asset->project->name ?? '—' }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $asset->status)) }}</span></td>
                        <td>{{ optional($asset->purchase_date)->format('d-m-Y') ?: '—' }}</td>
                        <td>{{ number_format((float) $asset->opening_wdv, 2) }}</td>
                        <td class="text-end">
                            <a href="{{ route('fixed-assets.machinery.show', $asset) }}" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                            @can('fixed_assets.edit')
                                <a href="{{ route('fixed-assets.machinery.edit', $asset) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No fixed assets found.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $assets->links() }}
        </div>
    </div>
</div>
@endsection
