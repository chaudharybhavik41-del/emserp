@extends('layouts.erp')

@section('content')
@php
    $isScoped = !empty($projectId);
    $indexUrl = $isScoped ? route('projects.production-qc.index', ['project' => $projectId]) : route('production.production-qc.index');
    $backUrl = $isScoped ? route('projects.production-dprs.index', ['project' => $projectId]) : route('production.production-dprs.index');
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-shield-check"></i> QC Pending</h2>
            <div class="text-muted small">
                @if($project)
                    Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}
                @else
                    Project: <span class="fw-semibold">All Projects</span>
                @endif
            </div>
        </div>
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    @if(! $isScoped)
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ $indexUrl }}" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Filter by Project</label>
                        <select name="project_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Projects</option>
                            @foreach(($projects ?? collect()) as $p)
                                <option value="{{ $p->id }}" {{ (string)($projectId ?? '') === (string)$p->id ? 'selected' : '' }}>
                                    {{ $p->code }} — {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ $indexUrl }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>ID</th>
                        <th>Plan</th>
                        <th>Activity</th>
                        <th>Item</th>
                        <th style="width:380px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pending as $qc)
                        @php
                            $qcProjectId = (int) ($qc->project_id ?? 0);
                            $updateUrl = $isScoped
                                ? route('projects.production-qc.update', [$projectId, $qc])
                                : route('production.production-qc.update', [$qc]);
                        @endphp
                        <tr>
                            <td>
                                {{ $qc->plan?->project?->code ?? ('#'.$qcProjectId) }}
                                <div class="text-muted small">{{ $qc->plan?->project?->name ?? '' }}</div>
                            </td>
                            <td class="fw-semibold">#{{ $qc->id }}</td>
                            <td>{{ $qc->plan?->plan_number }}</td>
                            <td>{{ $qc->activity?->name }}</td>
                            <td>
                                {{ $qc->planItemActivity?->planItem?->item_code ?? '—' }}
                                <div class="text-muted small">{{ $qc->planItemActivity?->planItem?->assembly_mark ?? '' }}</div>
                            </td>
                            <td>
                                <form method="POST" action="{{ $updateUrl }}" class="d-flex gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="result" class="form-select form-select-sm" required>
                                        <option value="">—</option>
                                        <option value="passed">Pass</option>
                                        <option value="failed">Fail</option>
                                    </select>
                                    <input name="remarks" class="form-control form-control-sm" placeholder="remarks (optional)">
                                    <button class="btn btn-sm btn-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No QC pending items.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-3">
                {{ $pending->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
