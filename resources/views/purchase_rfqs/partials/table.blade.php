<div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th style="width:140px;">RFQ No</th>
                <th>Project</th>
                <th style="width:120px;">RFQ Date</th>
                <th style="width:120px;">Due Date</th>
                <th style="width:120px;">Status</th>
                <th style="width:120px;" class="text-end">Action</th>
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

                   <td style="max-width:220px;">
    @php
        $projectText = $rfq->project
            ? $rfq->project->code . ' - ' . $rfq->project->name
            : '-';
    @endphp

    <span class="text-truncate d-inline-block align-middle"
          style="max-width:200px;"
          data-bs-toggle="tooltip"
          data-bs-placement="top"
          title="{{ $projectText }}">
        {{ $projectText }}
    </span>
</td>


                    <td>{{ optional($rfq->rfq_date)?->format('d-m-Y') }}</td>
                    <td>{{ optional($rfq->due_date)?->format('d-m-Y') }}</td>

                    <td>
                        <span class="badge bg-{{ $statusClass }}">
                            {{ ucfirst(str_replace('_', ' ', $rfq->status)) }}
                        </span>
                    </td>

                    <td class="text-end">
                        <a href="{{ route('purchase-rfqs.show', $rfq) }}" class="btn btn-sm btn-outline-secondary">
                            Open
                        </a>
                    </td>
                </tr>

            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">
                        No RFQs found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($rfqs instanceof \Illuminate\Pagination\AbstractPaginator)
    <div class="p-2">
        {{ $rfqs->links() }}
    </div>
@endif