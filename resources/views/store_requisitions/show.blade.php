@extends('layouts.erp')

@section('title', 'Store Requisition ' . ($requisition->requisition_number ?? '#' . $requisition->id))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                Store Requisition
                @if($requisition->requisition_number)
                    - {{ $requisition->requisition_number }}
                @else
                    - SR-{{ $requisition->id }}
                @endif
            </h1>
            <div class="small text-muted">
                Date:
                {{ optional($requisition->requisition_date)->format('d-m-Y') }}
            </div>
        </div>
        <div class="text-end">
            @php
                $status = $requisition->status ?? 'requested';
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
            <span class="badge {{ $badgeClass }} mb-2">
                {{ $statusLabel }}
            </span>
            <div>
                <a href="{{ route('store-requisitions.index') }}" class="btn btn-sm btn-secondary">
                    Back to List
                </a>
                @can('store.requisition.update')
                    @if(! in_array($requisition->status, ['closed','cancelled'], true))
                        <a href="{{ route('store-requisitions.edit', $requisition) }}"
                           class="btn btn-sm btn-outline-primary">
                            Edit
                        </a>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0 h6">Header</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4">Requisition No</dt>
                        <dd class="col-sm-8">
                            {{ $requisition->requisition_number ?? ('SR-' . $requisition->id) }}
                        </dd>

                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8">
                            {{ optional($requisition->requisition_date)->format('d-m-Y') }}
                        </dd>

                        <dt class="col-sm-4">Project</dt>
                        <dd class="col-sm-8">
                            @if($requisition->project)
                                {{ $requisition->project->code }} - {{ $requisition->project->name }}
                            @else
                                <span class="text-muted">No project (general)</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Contractor</dt>
                        <dd class="col-sm-8">
                            {{ $requisition->contractor->name ?? '-' }}
                        </dd>

                        <dt class="col-sm-4">Person</dt>
                        <dd class="col-sm-8">
                            {{ $requisition->contractor_person_name ?? '-' }}
                        </dd>

                        <dt class="col-sm-4">Requested By</dt>
                        <dd class="col-sm-8">
                            {{ $requisition->requestedBy->name ?? '-' }}
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0 h6">Additional Info</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge {{ $badgeClass }}">
                                {{ $statusLabel }}
                            </span>
                        </dd>

                        <dt class="col-sm-4">Remarks</dt>
                        <dd class="col-sm-8">
                            {{ $requisition->remarks ?? '-' }}
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2">
            <h5 class="card-title mb-0 h6">Materials</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 24%">Item</th>
                        <th style="width: 10%">UOM</th>
                        <th style="width: 12%">Required</th>
                        <th style="width: 12%">Issued</th>
                        <th style="width: 12%">Pending</th>
                        <th style="width: 14%">Brand</th>
                        <th style="width: 16%">Line Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($requisition->lines as $line)
                        @php
                            $required = (float) ($line->required_qty ?? 0);
                            $issued   = (float) ($line->issued_qty ?? 0);
                            $pending  = max(0, $required - $issued);

                            $lineStatusClass = 'bg-secondary';
                            $lineStatusLabel = 'Pending';

                            if ($required <= $issued + 0.0001 && $required > 0) {
                                $lineStatusClass = 'bg-success';
                                $lineStatusLabel = 'Fully Issued';
                            } elseif ($issued > 0.0001 && $pending > 0) {
                                $lineStatusClass = 'bg-info text-dark';
                                $lineStatusLabel = 'Partially Issued';
                            }
                        @endphp
                        <tr>
                            <td>
                                {{ $line->item->name ?? ('Item #' . $line->item_id) }}<br>
                                @if($line->description)
                                    <span class="small text-muted">{{ $line->description }}</span>
                                @endif
                            </td>
                            <td>{{ $line->uom->name ?? '-' }}</td>
                            <td>{{ number_format($required, 3) }}</td>
                            <td>{{ number_format($issued, 3) }}</td>
                            <td>{{ number_format($pending, 3) }}</td>
                            <td>{{ $line->preferred_make ?: '-' }}</td>
                            <td>
                                <span class="badge {{ $lineStatusClass }}">
                                    {{ $lineStatusLabel }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">
                                No materials on this requisition.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
