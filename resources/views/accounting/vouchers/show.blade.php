@extends('layouts.erp')

@section('title', 'Voucher Details')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Voucher: {{ $voucher->voucher_no }}</h1>
            @include('accounting.vouchers._voucher_doc_badge', ['voucher' => $voucher, 'docLinks' => $docLinks ?? []])
            <div class="small text-muted">
                Type: <strong>{{ strtoupper($voucher->voucher_type) }}</strong>
                · Date: <strong>{{ optional($voucher->voucher_date)->toDateString() }}</strong>
                · Status: <strong>{{ ucfirst($voucher->status) }}</strong>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('accounting.vouchers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>

            @if($voucher->isDraft())
                @can('accounting.vouchers.update')
                    <a href="{{ route('accounting.vouchers.edit', $voucher) }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-pencil"></i> Edit
                    </a>

                    <form method="POST" action="{{ route('accounting.vouchers.post', $voucher) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Post this voucher? After posting, editing will be blocked.')">
                            <i class="bi bi-check2-circle"></i> Post
                        </button>
                    </form>
                @endcan

                @can('accounting.vouchers.delete')
                    <form method="POST" action="{{ route('accounting.vouchers.destroy', $voucher) }}" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this draft voucher?')">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </form>
                @endcan
            @else
                @can('accounting.vouchers.update')
                    @if(!$voucher->isReversed() && !$voucher->reversal_of_voucher_id)
                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reverseModal">
                            <i class="bi bi-arrow-counterclockwise"></i> Reverse
                        </button>
                    @endif
                @endcan
            @endif
        </div>
    </div>

    {{-- Reversal info banners --}}
    @if($reversedFrom)
        <div class="alert alert-secondary small">
            This voucher is a <strong>Reversal</strong> of
            <a href="{{ route('accounting.vouchers.show', $reversedFrom->id) }}" class="fw-semibold text-decoration-none">
                {{ $reversedFrom->voucher_no }}
            </a>.
        </div>
    @endif

    @if($reversalVoucher)
        <div class="alert alert-warning small">
            This voucher has been <strong>Reversed</strong> by
            <a href="{{ route('accounting.vouchers.show', $reversalVoucher->id) }}" class="fw-semibold text-decoration-none">
                {{ $reversalVoucher->voucher_no }}
            </a>.
            @if($voucher->reversal_reason)
                <div class="mt-1 text-muted">Reason: {{ $voucher->reversal_reason }}</div>
            @endif
        </div>
    @endif

    {{-- Reverse voucher modal --}}
@if($voucher->isPosted() && !$voucher->isReversed() && !$voucher->reversal_of_voucher_id)
    <div class="modal fade" id="reverseModal" tabindex="-1" aria-labelledby="reverseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-warning">
                <div class="modal-header bg-warning-subtle">
                    <h5 class="modal-title" id="reverseModalLabel">
                        <i class="bi bi-arrow-counterclockwise"></i> Reverse Voucher
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" action="{{ route('accounting.vouchers.reverse', $voucher) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-warning small mb-3">
                            This will create a reversal voucher (same amount, opposite Dr/Cr) and mark this voucher as reversed.
                            Use reversal only for corrections. You cannot edit posted vouchers directly.
                        </div>

                        <div class="mb-3">
                            <label class="form-label form-label-sm">Reversal Date</label>
                            <input type="date"
                                   name="reversal_date"
                                   value="{{ old('reversal_date', now()->toDateString()) }}"
                                   class="form-control form-control-sm"
                                   required>
                        </div>

                        <div class="mb-0">
                            <label class="form-label form-label-sm">Reason (optional)</label>
                            <textarea name="reason"
                                      rows="3"
                                      class="form-control form-control-sm"
                                      placeholder="Why are you reversing this voucher?">{{ old('reason') }}</textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit"
                                class="btn btn-warning btn-sm"
                                onclick="return confirm('Create reversal voucher now?')">
                            <i class="bi bi-arrow-counterclockwise"></i> Create Reversal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif


    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header py-2">
                    <div class="fw-semibold small">Voucher Info</div>
                </div>
                <div class="card-body">
                    <div class="row g-2 small">
                        <div class="col-md-6"><span class="text-muted">Company ID:</span> {{ $voucher->company_id }}</div>
                        <div class="col-md-6"><span class="text-muted">Reference:</span> {{ $voucher->reference ?: '-' }}</div>
                        <div class="col-md-6"><span class="text-muted">Project:</span> {{ $voucher->project?->code ?: '-' }}</div>
                        <div class="col-md-6"><span class="text-muted">Cost Center:</span> {{ $voucher->costCenter?->name ?: '-' }}</div>
                        <div class="col-md-6"><span class="text-muted">Created by:</span> {{ $voucher->createdBy?->name ?: '-' }}</div>
                        <div class="col-md-6"><span class="text-muted">Created at:</span> {{ $voucher->created_at?->format('d-m-Y H:i') }}</div>
                        <div class="col-md-6"><span class="text-muted">Posted by:</span> {{ $voucher->postedBy?->name ?: '-' }}</div>
                        <div class="col-md-6"><span class="text-muted">Posted at:</span> {{ $voucher->posted_at?->format('d-m-Y H:i') ?: '-' }}</div>

                        @if($voucher->isReversed())
                            <div class="col-md-6"><span class="text-muted">Reversed by:</span> {{ $voucher->reversedBy?->name ?: '-' }}</div>
                            <div class="col-md-6"><span class="text-muted">Reversed at:</span> {{ $voucher->reversed_at?->format('d-m-Y H:i') ?: '-' }}</div>
                        @endif
                    </div>

                    <hr>

                    <div class="small text-muted">Narration</div>
                    <div class="fw-semibold">{{ $voucher->narration ?: '-' }}</div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header py-2">
                    <div class="fw-semibold small">Totals</div>
                </div>
                <div class="card-body">
                    @php
                        $diff = $totalDebit - $totalCredit;
                    @endphp
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded p-2">
                                <div class="small text-muted">Debit</div>
                                <div class="fw-semibold">{{ number_format($totalDebit, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2">
                                <div class="small text-muted">Credit</div>
                                <div class="fw-semibold">{{ number_format($totalCredit, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 {{ abs($diff) > 0.01 ? 'border-danger' : '' }}">
                                <div class="small text-muted">Diff (Dr-Cr)</div>
                                <div class="fw-semibold">{{ number_format($diff, 2) }}</div>
                            </div>
                        </div>
                    </div>

                    @if(abs($diff) > 0.01)
                        <div class="alert alert-danger small mt-3 mb-0">
                            This voucher is not balanced. Posting will fail until Debit = Credit.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div class="fw-semibold small">Voucher Lines</div>
            <div class="small text-muted">{{ $voucher->lines->count() }} lines</div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%">#</th>
                        <th style="width: 35%">Account</th>
                        <th>Description</th>
                        <th style="width: 12%" class="text-end">Debit</th>
                        <th style="width: 12%" class="text-end">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($voucher->lines->sortBy('line_no') as $line)
                        <tr>
                            <td class="small">{{ $line->line_no }}</td>
                            <td class="small">
                                <div class="fw-semibold">{{ $line->account?->name }}</div>
                                <div class="text-muted">{{ $line->account?->code }}</div>
                                @if($line->costCenter)
                                    <div class="text-muted">Cost Center: {{ $line->costCenter->name }}</div>
                                @endif
                            </td>
                            <td class="small">{{ $line->description ?: '-' }}</td>
                            <td class="small text-end">{{ number_format((float)$line->debit, 2) }}</td>
                            <td class="small text-end">{{ number_format((float)$line->credit, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light fw-semibold">
                        <td colspan="3" class="text-end small">Total</td>
                        <td class="text-end small">{{ number_format($totalDebit, 2) }}</td>
                        <td class="text-end small">{{ number_format($totalCredit, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div class="fw-semibold small">Audit Trail (last 50)</div>
            @can('core.activity_log.view')
                <a href="{{ route('activity-logs.index', ['subject_type' => \App\Models\Accounting\Voucher::class, 'subject_id' => $voucher->id]) }}" class="btn btn-outline-secondary btn-sm">
                    View All Logs
                </a>
            @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 16%">Date/Time</th>
                        <th style="width: 14%">User</th>
                        <th style="width: 14%">Action</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($activityLogs))
                    @foreach($activityLogs as $log)
                        <tr>
                            <td class="small">{{ optional($log->created_at)->format('d-m-Y H:i') }}</td>
                            <td class="small">{{ $log->user_name ?: ($log->user?->name ?: 'System') }}</td>
                            <td class="small"><span class="badge bg-light text-dark">{{ $log->action }}</span></td>
                            <td class="small">{{ $log->description }}</td>
                        </tr>
                                        @endforeach
                    @else

                        <tr>
                            <td colspan="4" class="text-center small text-muted py-3">No audit logs found for this voucher.</td>
                        </tr>
                                        @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection