@extends('layouts.erp')

@section('title', 'Voucher Series')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Voucher Series</h1>
        @can('accounting.accounts.update')
            <a href="{{ route('accounting.voucher-series.create') }}" class="btn btn-primary btn-sm">
                Create Series
            </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
    @endif

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.voucher-series.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Search</label>
                    <input type="text"
                           name="q"
                           value="{{ request('q') }}"
                           class="form-control form-control-sm"
                           placeholder="Key / Name / Prefix">
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Disabled</option>
                    </select>
                </div>

                <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i>Apply
                    </button>
                    <a href="{{ route('accounting.voucher-series.index') }}" class="btn btn-sm btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    @php
        $sort = (string) request('sort', '');
        $dir = strtolower((string) request('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortLink = function (string $col) use ($sort, $dir) {
            $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
            return request()->fullUrlWithQuery(['sort' => $col, 'dir' => $nextDir]);
        };
    @endphp

    <div class="card">
        <div class="card-body">
            <div class="small text-muted mb-2">
                Company ID: {{ $companyId }} • Today: {{ $today->toDateString() }}
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 14%;">
                                <a href="{{ $sortLink('key') }}" class="text-decoration-none text-dark">
                                    Key
                                    @if($sort === 'key')
                                        <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                                    @endif
                                </a>
                            </th>
                            <th style="width: 18%;">
                                <a href="{{ $sortLink('name') }}" class="text-decoration-none text-dark">
                                    Name
                                    @if($sort === 'name')
                                        <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                                    @endif
                                </a>
                            </th>
                            <th style="width: 10%;">
                                <a href="{{ $sortLink('prefix') }}" class="text-decoration-none text-dark">
                                    Prefix
                                    @if($sort === 'prefix')
                                        <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                                    @endif
                                </a>
                            </th>
                            <th style="width: 10%;">
                                <a href="{{ $sortLink('use_financial_year') }}" class="text-decoration-none text-dark">
                                    FY?
                                    @if($sort === 'use_financial_year')
                                        <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                                    @endif
                                </a>
                            </th>
                            <th style="width: 10%;">Sep</th>
                            <th style="width: 10%;">Pad</th>
                            <th style="width: 10%;">
                                <a href="{{ $sortLink('is_active') }}" class="text-decoration-none text-dark">
                                    Active
                                    @if($sort === 'is_active')
                                        <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                                    @endif
                                </a>
                            </th>
                            <th>Next Voucher No (Preview)</th>
                            <th style="width: 10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                /** @var \App\Models\Accounting\VoucherSeries $s */
                                $s = $row['series'];
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $s->key }}</td>
                                <td>{{ $s->name }}</td>
                                <td><span class="badge bg-secondary">{{ $s->prefix }}</span></td>
                                <td>{{ $s->use_financial_year ? 'Yes' : 'No' }}</td>
                                <td>{{ $s->separator }}</td>
                                <td>{{ $s->pad_length }}</td>
                                <td>
                                    @if($s->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Disabled</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $row['preview'] }}</span>
                                    <div class="small text-muted">
                                        FY: {{ $row['fy_code'] }}
                                        @if(!is_null($row['next_number']))
                                            • Next Seq: {{ $row['next_number'] }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @can('accounting.accounts.update')
                                        <a href="{{ route('accounting.voucher-series.edit', $s) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    @else
                                        <span class="text-muted small">No access</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-3">No series found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
