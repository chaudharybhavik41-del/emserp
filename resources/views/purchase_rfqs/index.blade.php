@extends('layouts.erp')

@section('title', 'Purchase RFQs')
<style>.select2-container {
    width: 100% !important;
}

.select2-results__options {
    max-height: 250px;
    overflow-y: auto;
}
</style>
@section('content')
                                                    @php
    $rows = $rfqs->getCollection();
    $draftCount = $rows->where('status', 'draft')->count();
    $sentCount = $rows->where('status', 'sent')->count();
    $poCount = $rows->where('status', 'po_generated')->count();
    $closedCount = $rows->where('status', 'closed')->count();
                                                    @endphp
                                                    <div class="container-fluid px-0">
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <div>
                                                                <h1 class="h4 mb-0"><i class="bi bi-envelope-paper me-1"></i> Purchase RFQs</h1>
                                                                <div class="small text-muted">Create, send, compare quotes, and convert L1 to POs.</div>
                                                            </div>

                                                            @can('purchase.rfq.create')
                                                                <a href="{{ route('purchase-rfqs.create') }}" class="btn btn-sm btn-primary">
                                                                    <i class="bi bi-plus-circle me-1"></i> New RFQ
                                                                </a>
                                                            @endcan
                                                        </div>

                                                        @if(session('success'))
                                                            <div class="alert alert-success alert-dismissible fade show">
                                                                {{ session('success') }}
                                                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                                            </div>
                                                        @endif

                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-3 col-6">
                                                                <div class="card border-0 bg-light">
                                                                    <div class="card-body py-2">
                                                                        <div class="small text-muted">Draft</div>
                                                                        <div class="h5 mb-0">{{ $draftCount }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3 col-6">
                                                                <div class="card border-0 bg-light">
                                                                    <div class="card-body py-2">
                                                                        <div class="small text-muted">Sent</div>
                                                                        <div class="h5 mb-0">{{ $sentCount }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3 col-6">
                                                                <div class="card border-0 bg-light">
                                                                    <div class="card-body py-2">
                                                                        <div class="small text-muted">PO Generated</div>
                                                                        <div class="h5 mb-0">{{ $poCount }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3 col-6">
                                                                <div class="card border-0 bg-light">
                                                                    <div class="card-body py-2">
                                                                        <div class="small text-muted">Closed</div>
                                                                        <div class="h5 mb-0">{{ $closedCount }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                <div class="card mb-3 shadow-sm border-0">
                                                    <div class="card-body bg-light">
                                                        <form id="filterForm" class="row g-3 align-items-end">

                                                            <div class="col-lg-3 col-md-6">
                                                                <label class="form-label small fw-semibold text-muted">
                                                                    <i class="bi bi-hash me-1"></i> RFQ No
                                                                </label>
                                                                <input type="text" name="code" class="form-control form-control-sm" placeholder="Search RFQ No">
                                                            </div>

                                                            <div class="col-lg-3 col-md-6">
                                                                <label class="form-label small fw-semibold text-muted">
                                                                    <i class="bi bi-chat-left-text me-1"></i> Remarks
                                                                </label>
                                                                <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Search Remarks">
                                                            </div>

                                                            <div class="col-lg-3 col-md-6">
                                                                <label class="form-label small fw-semibold text-muted">
                                                                    <i class="bi bi-folder2-open me-1"></i> Project
                                                                </label>
                                                                <select name="project_id" class="form-select form-select-sm shadow-sm project-select2">
                                                                    <option value="">— All Projects —</option>
                                                                    @foreach($projects as $p)
                                                                        <option value="{{ $p->id }}">
                                                                            {{ $p->code }} - {{ $p->name }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>

                                                            <div class="col-lg-3 col-md-6">
                                                                <label class="form-label small fw-semibold text-muted">
                                                                    <i class="bi bi-flag me-1"></i> Status
                                                                </label>
                                                                <select name="status" class="form-select form-select-sm">
                                                                    <option value="">All Status</option>
                                                                    @foreach($statusOptions as $k => $v)
                                                                        <option value="{{ $k }}">{{ $v }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>

                                            <div class="card">
                                                <div class="card-body p-0" id="rfqTable">
                                                    @include('purchase_rfqs.partials.table')
                                                </div>
                                            </div>

                                                        {{-- <div class="card">
                                                            <div class="card-body p-0">
                                                                <div class="table-responsive">
                                                                    <table class="table table-sm table-hover mb-0 align-middle">
                                                                        <thead class="table-light">
                                                                            <tr>
                                                                                <th style="width: 140px;">RFQ No</th>
                                                                                <th>Project</th>
                                                                                <th style="width: 120px;">RFQ Date</th>
                                                                                <th style="width: 120px;">Due Date</th>
                                                                                <th style="width: 120px;">Status</th>
                                                                                <th style="width: 120px;" class="text-end">Action</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @forelse($rfqs as $rfq)
                                                                                @php
                                                    $statusClass = match ($rfq->status) {
                                                        'sent' => 'info',
                                                        'po_generated' => 'success',
                                                        'closed' => 'dark',
                                                        'cancelled' => 'danger',
                                                        default => 'secondary',
                                                    };
                                                                                @endphp
                                                                                <tr>
                                                                                    <td>
                                                                                        <a href="{{ route('purchase-rfqs.show', $rfq) }}" class="fw-semibold text-decoration-none">
                                                                                            {{ $rfq->code }}
                                                                                        </a>
                                                                                    </td>
                                                                                    <td>
                                                                                        @if($rfq->project)
                                                                                            {{ $rfq->project->code }} - {{ $rfq->project->name }}
                                                                                        @else
                                                                                            <span class="text-muted">-</span>
                                                                                        @endif
                                                                                    </td>
                                                                                    <td>{{ optional($rfq->rfq_date)?->format('d-m-Y') ?: '-' }}</td>
                                                                                    <td>{{ optional($rfq->due_date)?->format('d-m-Y') ?: '-' }}</td>
                                                                                    <td><span class="badge bg-{{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $rfq->status)) }}</span></td>
                                                                                    <td class="text-end">
                                                                                        <a href="{{ route('purchase-rfqs.show', $rfq) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                                                                                    </td>
                                                                                </tr>
                                                                            @empty
                                                                                <tr>
                                                                                    <td colspan="6" class="text-center text-muted py-3">No RFQs found.</td>
                                                                                </tr>
                                                                            @endforelse
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>

                                                            @if($rfqs instanceof \Illuminate\Pagination\AbstractPaginator)
                                                                <div class="card-footer py-2 d-flex justify-content-between align-items-center">
                                                                    <small class="text-muted">Showing {{ $rfqs->count() }} of {{ $rfqs->total() }} RFQs</small>
                                                                    {{ $rfqs->links() }}
                                                                </div>
                                                            @endif
                                                        </div> --}}
                                                    </div>
                                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

                                                    <script>
                                                    $(document).ready(function () {

                                                         $('.project-select2').select2({
                                                            width: '100%',
                                                            dropdownAutoWidth: false,
                                                            dropdownParent: $('#filterForm')
                                                        });

                                                        // 🔥 Auto focus when dropdown opens
                                                        $(document).on('select2:open', function () {
                                                            setTimeout(function () {
                                                                document.querySelector('.select2-container--open .select2-search__field')?.focus();
                                                            }, 0);
                                                        });


                                                            function loadRfqs(url = "{{ route('purchase-rfqs.index') }}") {
                                                                $.ajax({
                                                                    url: url,
                                                                    type: "GET",
                                                                    data: $('#filterForm').serialize(),
                                                                    success: function (response) {
                                                                        $('#rfqTable').html(response);
                                                                    }
                                                                });
                                                            }

                                                            let typingTimer;

                                                            $('#filterForm').on('keyup', 'input[name="code"], input[name="remarks"]', function () {
                                                                clearTimeout(typingTimer);
                                                                typingTimer = setTimeout(function () {
                                                                    loadRfqs();
                                                                }, 300);
                                                            });

                                                            $('#filterForm').on('change', 'select', function () {
                                                                loadRfqs();
                                                            });

                                                            $(document).on('click', '#rfqTable .pagination a', function (e) {
                                                                e.preventDefault();
                                                                loadRfqs($(this).attr('href'));
                                                            });

                                                        });


                                                    </script>

@endsection
