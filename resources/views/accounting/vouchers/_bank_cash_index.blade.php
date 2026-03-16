<div class="container-fluid">
    @php
        $counterpartyLabel = $counterpartyLabel ?? 'Counterparty';
        $createButtonLabel = $createButtonLabel ?? 'Create New Voucher';
    @endphp
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $pageTitle }}</h1>
            <div class="text-muted small">Review existing vouchers first, then create a new one from here.</div>
        </div>
        @can('accounting.vouchers.create')
            <a href="{{ $createRoute }}" class="btn btn-primary btn-sm">{{ $createButtonLabel }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label form-label-sm">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(['draft' => 'Draft', 'posted' => 'Posted'] as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" @selected(request('status') === $statusValue)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Bank / Cash</label>
                    <select name="bank_account_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($bankCashAccounts as $account)
                            <option value="{{ $account->id }}" @selected((string) request('bank_account_id') === (string) $account->id)>
                                {{ $account->code }} - {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">{{ $counterpartyLabel }}</label>
                    <select name="party_account_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($counterpartyAccounts as $account)
                            <option value="{{ $account->id }}" @selected((string) request('party_account_id') === (string) $account->id)>
                                {{ $account->code }} - {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label form-label-sm">Search</label>
                    <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="Voucher no, reference, narration">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Apply</button>
                    <a href="{{ $resetRoute }}" class="btn btn-link btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Voucher No</th>
                            <th>Date</th>
                            <th>Bank / Cash</th>
                            <th>{{ $counterpartyLabel }}</th>
                            <th>Reference</th>
                            <th>Narration</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vouchers as $voucher)
                            @php
                                $bankLine = null;
                                $partyLine = null;
                                foreach ($voucher->lines as $line) {
                                    if (!$bankLine && in_array($line->account?->type, ['bank', 'cash'], true)) {
                                        $bankLine = $line;
                                    }
                                    if (!$partyLine && !in_array($line->account?->type, ['bank', 'cash'], true)) {
                                        $partyLine = $line;
                                    }
                                }
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $voucher->voucher_no }}</td>
                                <td>{{ optional($voucher->voucher_date)->format('d-m-Y') }}</td>
                                <td>{{ $bankLine?->account?->name ?? '-' }}</td>
                                <td>{{ $partyLine?->account?->name ?? '-' }}</td>
                                <td>{{ $voucher->reference ?: '-' }}</td>
                                <td class="small">{{ \Illuminate\Support\Str::limit((string) ($voucher->narration ?? ''), 80) ?: '-' }}</td>
                                <td class="text-end">{{ number_format((float) $voucher->amount_base, 2) }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $voucher->status === 'posted' ? 'success' : 'secondary' }}">
                                        {{ ucfirst((string) $voucher->status) }}
                                    </span>
                                </td>
                                <td>{{ $voucher->createdBy?->name ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('accounting.vouchers.show', $voucher) }}" class="btn btn-outline-secondary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No vouchers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if(method_exists($vouchers, 'links'))
            <div class="card-footer">
                {{ $vouchers->links() }}
            </div>
        @endif
    </div>
</div>
