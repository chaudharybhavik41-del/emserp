@extends('layouts.erp')

@section('title', 'Store Return ' . ($return->return_number ?? ''))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            Store Return
            @if($return->return_number)
                - {{ $return->return_number }}
            @endif
        </h1>
        <div class="d-flex gap-2">
            @can('store.issue.post_to_accounts')
                @php
                $acc = $return->accounting_status ?? 'pending';
            @endphp
                @if($acc !== 'posted' && $acc !== 'not_required')
                    <form method="POST" action="{{ route('store-returns.post-to-accounts', $return) }}">
                        @csrf
                        <button class="btn btn-sm btn-success"
                                onclick="return confirm('Post this Store Return to Accounts?');">
                            Post to Accounts
                        </button>
                    </form>
                @endif
            @endcan

            <a href="{{ route('store-returns.index') }}" class="btn btn-sm btn-outline-secondary">
                Back to list
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <strong>Return Date:</strong><br>
                    {{ optional($return->return_date)->format('d-m-Y') ?? '-' }}
                </div>
                <div class="col-md-3">
                    <strong>Status:</strong><br>
                    <span class="badge bg-secondary">{{ strtoupper($return->status) }}</span>
                </div>
                <div class="col-md-3">
                    <strong>Accounting:</strong><br>
                    @php
                $acc = $return->accounting_status ?? 'pending';
            @endphp
                    <span class="badge bg-{{ $acc === 'posted' ? 'success' : ($acc === 'not_required' ? 'secondary' : 'warning') }}">
                        {{ strtoupper($acc) }}
                    </span>
                    @if(!empty($return->voucher_id))
                        <div class="small mt-1">
                            <a href="{{ route('accounting.vouchers.show', $return->voucher_id) }}" class="text-decoration-none">
                                View Voucher
                            </a>
                        </div>
                    @endif
                </div>
                <div class="col-md-3">
                    <strong>Project:</strong><br>
                    @if($return->project)
                        {{ $return->project->code }} - {{ $return->project->name }}
                    @else
                        -
                    @endif
                </div>
                <div class="col-md-3">
                    <strong>Issue Ref:</strong><br>
                    @if($return->issue)
                        {{ $return->issue->issue_number }}
                    @else
                        -
                    @endif
                </div>

                <div class="col-md-3">
                    <strong>Gate Pass Ref:</strong><br>
                    @if($return->gatePass)
                        <a href="{{ route('gate-passes.show', $return->gatePass) }}" class="text-decoration-none">
                            {{ $return->gatePass->gatepass_number }}
                        </a>
                    @else
                        -
                    @endif
                </div>
            </div>

            <div class="row g-3 mt-3">
                <div class="col-md-6">
                    <strong>Contractor:</strong><br>
                    @if($return->contractor)
                        {{ $return->contractor->name }}
                    @else
                        -
                    @endif
                </div>
                <div class="col-md-6">
                    <strong>Contractor / Person Name:</strong><br>
                    {{ $return->contractor_person_name ?? '-' }}
                </div>
            </div>

            @if($return->reason)
                <div class="row g-3 mt-3">
                    <div class="col-12">
                        <strong>Reason:</strong><br>
                        {{ $return->reason }}
                    </div>
                </div>
            @endif

            @if($return->remarks)
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <strong>Remarks:</strong><br>
                        {{ $return->remarks }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2">
            <h5 class="card-title mb-0 h6">Returned Stock Items</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 25%">Item</th>
                        <th style="width: 15%">Category</th>
                        <th style="width: 18%">Size / Section</th>
                        <th style="width: 10%">Qty (pcs)</th>
                        <th style="width: 10%">Weight (kg)</th>
                        <th style="width: 10%">Stock ID</th>
                        <th style="width: 12%">Issue Line</th>
                        <th style="width: 12%">Stock Project</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($return->lines as $line)
                        @php
                            $stock = $line->stockItem;
                        @endphp
                        <tr>
                            <td>
                                @if($stock && $stock->item)
                                    {{ $stock->item->name }}
                                @else
                                    {{ $line->item->name ?? ('Item #'.$line->item_id) }}
                                @endif
                            </td>
                            <td>
                                @if($stock)
                                    {{ ucfirst(str_replace('_', ' ', $stock->material_category ?? '')) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($stock && $stock->material_category === 'steel_plate')
                                    T{{ $stock->thickness_mm }} x W{{ $stock->width_mm }} x L{{ $stock->length_mm }}
                                @elseif($stock && $stock->material_category === 'steel_section')
                                    {{ $stock->section_profile }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $line->returned_qty_pcs }}</td>
                            <td>{{ $line->returned_weight_kg ?? '-' }}</td>
                            <td>#{{ $stock->id ?? $line->store_stock_item_id }}</td>
                            <td>
                                @if($line->store_issue_line_id)
                                    #{{ $line->store_issue_line_id }}
                                    @if($line->issueLine && $line->issueLine->issue)
                                        <div class="small text-muted">{{ $line->issueLine->issue->issue_number }}</div>
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($stock && $stock->project)
                                    {{ $stock->project->code }} - {{ $stock->project->name }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">
                                No stock items on this return.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection



================================================================================


