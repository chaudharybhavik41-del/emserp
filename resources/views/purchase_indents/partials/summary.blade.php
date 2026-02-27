@php
    $rows = $indents->getCollection();
@endphp

<div class="row g-2 mb-3">

    <div class="col-md-3 col-6">
        <div class="card bg-light border-0">
            <div class="card-body py-2">
                <div class="small text-muted">Draft</div>
                <div class="h5 mb-0">{{ $rows->where('status', 'draft')->count() }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-6">
        <div class="card bg-light border-0">
            <div class="card-body py-2">
                <div class="small text-muted">Approved</div>
                <div class="h5 mb-0">{{ $rows->where('status', 'approved')->count() }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-6">
        <div class="card bg-light border-0">
            <div class="card-body py-2">
                <div class="small text-muted">Rejected</div>
                <div class="h5 mb-0">{{ $rows->where('status', 'rejected')->count() }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-6">
        <div class="card bg-light border-0">
            <div class="card-body py-2">
                <div class="small text-muted">Fully Ordered</div>
                <div class="h5 mb-0">
                    {{ $rows->where('procurement_status', 'ordered')->count() }}
                </div>
            </div>
        </div>
    </div>

</div>