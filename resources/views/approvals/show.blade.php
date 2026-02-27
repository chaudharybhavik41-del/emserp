@extends('layouts.erp')

@section('title', 'Approval Request #' . $approval->id)

@php
    $doc = $approval->approvable;
    $docUrl = data_get($approval->metadata ?? [], 'url');

    // Optional: map common models to show routes (safe: only if route exists).
    $docRouteMap = [
        \App\Models\PurchaseIndent::class => 'purchase-indents.show',
        \App\Models\PurchaseOrder::class  => 'purchase-orders.show',
        \App\Models\PurchaseBill::class   => 'purchase.bills.show',
        \App\Models\StoreRequisition::class => 'store-requisitions.show',
        \App\Models\Accounting\Voucher::class => 'accounting.vouchers.show',
    ];

    if (!$docUrl && $doc) {
        $routeName = $docRouteMap[$approval->approvable_type] ?? null;
        if ($routeName && \Illuminate\Support\Facades\Route::has($routeName)) {
            $docUrl = route($routeName, $doc);
        }
    }

    $status = $approval->status ?? 'pending';
    $statusBadge = match ($status) {
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'in_progress' => 'bg-info text-dark',
        'pending' => 'bg-warning text-dark',
        default => 'bg-secondary',
    };
@endphp

@section('page_header')
    <div>
        <div class="h5 mb-0">Approval Request #{{ $approval->id }}</div>
        <div class="small text-body-secondary">
            {{ $approval->module }}@if($approval->sub_module) / {{ $approval->sub_module }}@endif
            • Action: {{ $approval->action }}
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('my-approvals.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>

        @if($docUrl)
            <a href="{{ $docUrl }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right me-1"></i> Open Document
            </a>
        @endif
    </div>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header py-2"><strong>Request Details</strong></div>
                <div class="card-body small">
                    <div class="mb-1"><strong>Requested By:</strong> {{ $approval->requester->name ?? 'System' }}</div>
                    <div class="mb-1">
                        <strong>Status:</strong>
                        <span class="badge {{ $statusBadge }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                    </div>
                    <div class="mb-1"><strong>Requested At:</strong> {{ optional($approval->requested_at)->format('Y-m-d H:i') }}</div>
                    <div class="mb-1"><strong>Current Step:</strong> {{ $approval->current_step ?? 'N/A' }}</div>

                    @if($approval->closed_at)
                        <hr class="my-2">
                        <div class="mb-1"><strong>Closed At:</strong> {{ optional($approval->closed_at)->format('Y-m-d H:i') }}</div>
                        <div class="mb-0"><strong>Closed By:</strong> {{ $approval->closer->name ?? '-' }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header py-2"><strong>Related Document</strong></div>
                <div class="card-body small">
                    @if($doc)
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <div class="text-body-secondary">Type</div>
                                <div class="fw-semibold">{{ class_basename($approval->approvable_type) }}</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="text-body-secondary">Document ID</div>
                                <div class="fw-semibold">#{{ $approval->approvable_id }}</div>
                            </div>
                        </div>

                        <div class="mt-2">
                            <div class="text-body-secondary">Reference</div>
                            <div class="fw-semibold">
                                {{ $doc->doc_no ?? $doc->code ?? $doc->name ?? ('#'.$approval->approvable_id) }}
                            </div>
                        </div>

                        @if(!$docUrl)
                            <div class="mt-3 text-body-secondary">
                                Document link not configured for this module yet.
                            </div>
                        @endif
                    @else
                        <div class="text-muted">Document not found (it may have been deleted).</div>
                    @endif

                    @if(!empty($approval->metadata))
                        <hr class="my-3">
                        <div class="text-body-secondary mb-1">Metadata</div>
                        <pre class="bg-body-tertiary p-2 rounded small mb-0" style="white-space: pre-wrap;">{{ json_encode($approval->metadata, JSON_PRETTY_PRINT) }}</pre>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong>Approval Workflow Steps</strong>
            <span class="small text-body-secondary">Total: {{ $approval->steps->count() }}</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Step</th>
                        <th>Approver</th>
                        <th>Mandatory</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($approval->steps->sortBy('step_number') as $step)
                        @php
                            $stepStatus = $step->status ?? 'pending';
                            $stepBadge = match ($stepStatus) {
                                'approved' => 'bg-success',
                                'rejected' => 'bg-danger',
                                'pending' => 'bg-warning text-dark',
                                default => 'bg-secondary',
                            };

                            $approverLabel =
                                $step->approverUser?->name
                                ?? ($step->approverRole ? 'Role: '.$step->approverRole->name : '-');

                            $canAct = false;
                            if ($stepStatus === 'pending') {
                                $user = auth()->user();
                                if ($user) {
                                    $userRoleIds = $user->roles->pluck('id')->all();
                                    $canAct =
                                        ($step->approver_user_id && $step->approver_user_id == $user->id)
                                        || ($step->approver_role_id && in_array($step->approver_role_id, $userRoleIds));
                                }
                            }
                        @endphp

                        <tr>
                            <td>{{ $step->step_number }}</td>
                            <td>{{ $approverLabel }}</td>
                            <td>{{ $step->is_mandatory ? 'Yes' : 'No' }}</td>
                            <td><span class="badge {{ $stepBadge }}">{{ ucfirst($stepStatus) }}</span></td>
                            <td>{{ $step->remarks ?? '-' }}</td>

                            <td class="text-end">
                                @if($canAct)
                                    <div class="d-inline-flex gap-1">
                                        <form method="POST" action="{{ route('approvals.steps.approve', $step) }}">
                                            @csrf
                                            <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                Approve
                                            </button>
                                        </form>

                                        <button class="btn btn-sm btn-danger"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#rejectForm-{{ $step->id }}">
                                            Reject
                                        </button>
                                    </div>

                                    <div class="collapse mt-2" id="rejectForm-{{ $step->id }}">
                                        <form method="POST" action="{{ route('approvals.steps.reject', $step) }}" class="border rounded p-2">
                                            @csrf
                                            <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                                            <div class="mb-2">
                                                <textarea name="remarks"
                                                          class="form-control form-control-sm"
                                                          rows="2"
                                                          placeholder="Reason for rejection..."
                                                          required></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                Confirm Reject
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted small">No action</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($approval->remarks)
        <div class="card shadow-sm">
            <div class="card-header py-2"><strong>Request Remarks</strong></div>
            <div class="card-body">
                <p class="mb-0">{{ $approval->remarks }}</p>
            </div>
        </div>
    @endif
@endsection