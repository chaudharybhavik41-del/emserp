@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-box-seam"></i> Remnants (Project)</h2>
            <div class="text-muted small">Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}</div>
        </div>
        <a href="{{ route('projects.production-dashboard.index', $project) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mother Stock</th>
                        <th>Remnant Stock</th>
                        <th>Size</th>
                        <th class="text-end">Wt (kg)</th>
                        <th>Usable</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td class="fw-semibold">#{{ $r->id }}</td>
                            <td>{{ $r->mother_stock_item_id ?? '—' }}</td>
                            <td>{{ $r->remnant_stock_item_id ?? '—' }}</td>
                            <td class="small text-muted">
                                T: {{ $r->thickness_mm ?? '-' }} |
                                W: {{ $r->width_mm ?? '-' }} |
                                L: {{ $r->length_mm ?? '-' }}
                            </td>
                            <td class="text-end">{{ $r->weight_kg ?? '—' }}</td>
                            <td>
                                @if($r->is_usable)
                                    <span class="badge text-bg-success">Yes</span>
                                @else
                                    <span class="badge text-bg-secondary">No</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $r->status === 'available' ? 'text-bg-primary' : ($r->status === 'scrap' ? 'text-bg-dark' : 'text-bg-secondary') }}">
                                    {{ ucfirst($r->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No remnants captured yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-3">{{ $rows->links() }}</div>
        </div>
    </div>
</div>
@endsection
