@extends('layouts.erp')

@section('title', 'Store Requisitions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Store Requisitions</h1>
        @can('store.requisition.create')
            <a href="{{ route('store-requisitions.create') }}" class="btn btn-sm btn-primary">
                New Requisition
            </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 10%">Req. No</th>
                        <th style="width: 12%">Date</th>
                        <th style="width: 24%">Project</th>
                        <th style="width: 20%">Contractor / Person</th>
                        <th style="width: 14%">Status</th>
                        <th style="width: 10%">Requested By</th>
                        <th style="width: 10%"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($requisitions as $req)
                        @php
                            $status = $req->status ?? 'requested';
                            $badgeClass = 'bg-secondary';
                            $statusLabel = ucfirst($status);

                            if ($status === 'requested') {
                                $badgeClass  = 'bg-warning text-dark';
                                $statusLabel = 'Requested';
                            } elseif ($status === 'issued') {
                                $badgeClass  = 'bg-info text-dark';
                                $statusLabel = 'Partially Issued';
                            } elseif ($status === 'closed') {
                                $badgeClass  = 'bg-success';
                                $statusLabel = 'Closed (Fully Issued)';
                            } elseif ($status === 'cancelled') {
                                $badgeClass  = 'bg-danger';
                                $statusLabel = 'Cancelled';
                            }
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('store-requisitions.show', $req) }}">
                                    {{ $req->requisition_number ?? ('SR-' . $req->id) }}
                                </a>
                            </td>
                            <td>{{ optional($req->requisition_date)->format('d-m-Y') }}</td>
                            <td>
                                @if($req->project)
                                    {{ $req->project->code }} - {{ $req->project->name }}
                                @else
                                    <span class="text-muted small">No project</span>
                                @endif
                            </td>
                            <td>
                                @if($req->contractor)
                                    {{ $req->contractor->name }}
                                @else
                                    <span class="text-muted small">In-house</span>
                                @endif
                                @if($req->contractor_person_name)
                                    <br><span class="small text-muted">{{ $req->contractor_person_name }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $badgeClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td>
                                {{ $req->requestedBy->name ?? '-' }}
                            </td>
                            <td class="text-end">
                                <a href="{{ route('store-requisitions.show', $req) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">
                                No store requisitions found.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($requisitions->hasPages())
            <div class="card-footer pb-0">
                {{ $requisitions->links() }}
            </div>
        @endif
    </div>
@endsection
