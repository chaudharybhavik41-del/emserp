@extends('layouts.erp')

@section('title', 'Year-End Close Dashboard')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Year-End Close Dashboard</h1>
            <div class="text-muted small">Read-only close checklist for previous FY balance, lock coverage, and carry-forward risks.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('accounting.period-locks.index') }}" class="btn btn-outline-secondary btn-sm">Period Locks</a>
            <a href="{{ route('accounting.reports.health-check') }}" class="btn btn-outline-secondary btn-sm">Health Check</a>
            <a href="{{ route('accounting.reports.trial-balance', ['as_of_date' => $previousFy['end']->toDateString()]) }}" class="btn btn-outline-primary btn-sm">Trial Balance</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Previous FY</div>
                    <div class="fw-semibold">FY {{ $previousFy['label'] }}</div>
                    <div class="small text-muted">{{ $previousFy['start']->format('d-m-Y') }} to {{ $previousFy['end']->format('d-m-Y') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Current FY</div>
                    <div class="fw-semibold">FY {{ $currentFy['label'] }}</div>
                    <div class="small text-muted">{{ $currentFy['start']->format('d-m-Y') }} to {{ $currentFy['end']->format('d-m-Y') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Next FY Starts</div>
                    <div class="fw-semibold">{{ $nextFyStart->format('d-m-Y') }}</div>
                    <div class="small text-muted">Opening balance carry-forward cutover date.</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Previous FY TB Difference</div>
                    <div class="fw-semibold {{ abs((float) $trialBalance['difference']) < 0.005 ? 'text-success' : 'text-danger' }}">
                        {{ number_format((float) $trialBalance['difference'], 2) }}
                    </div>
                    <div class="small text-muted">Debit {{ number_format((float) $trialBalance['grand_debit'], 2) }} · Credit {{ number_format((float) $trialBalance['grand_credit'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-success h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">OK</div>
                    <div class="h4 mb-0 text-success">{{ $summary['ok'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Warnings</div>
                    <div class="h4 mb-0 text-warning">{{ $summary['warning'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Critical</div>
                    <div class="h4 mb-0 text-danger">{{ $summary['critical'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info small">
        This dashboard does not auto-close the year and does not create any opening entries. Use it to decide when the previous FY is balanced, locked, and ready for controlled carry-forward work.
    </div>

    <div class="card mb-3">
        <div class="card-header py-2 fw-semibold">Carry Forward Export</div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-lg-8">
                    <div class="small text-muted mb-2">
                        Exports the next-year opening balance CSV in the same format used by the opening-balance import tool:
                        <code>account_code, opening_balance, dr_cr, opening_date</code>.
                    </div>
                    <div class="small text-muted">
                        Default export excludes party ledgers to avoid clashing with bill-wise AR/AP migration.
                        Non-party rows: <strong>{{ $carryForwardPreview['non_party_rows'] ?? 0 }}</strong>
                        · Party rows available separately: <strong>{{ $carryForwardPreview['party_rows'] ?? 0 }}</strong>
                    </div>
                </div>
                <div class="col-lg-4 d-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="{{ route('accounting.reports.year-end-close.carry-forward') }}" class="btn btn-outline-primary btn-sm">
                        Export OB CSV
                    </a>
                    <a href="{{ route('accounting.reports.year-end-close.carry-forward', ['include_party_ledgers' => 1]) }}" class="btn btn-outline-secondary btn-sm">
                        Export OB CSV With Parties
                    </a>
                </div>
            </div>
        </div>
    </div>

    @foreach($sections as $section)
        <div class="card mb-3">
            <div class="card-header py-2">
                <div class="fw-semibold">{{ $section['title'] }}</div>
                @if(!empty($section['description']))
                    <div class="text-muted small">{{ $section['description'] }}</div>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 24%">Check</th>
                            <th style="width: 10%">Status</th>
                            <th style="width: 22%">Detail</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                            @php
                                $status = $row['status'] ?? 'warning';
                                $badgeClass = match ($status) {
                                    'ok' => 'bg-success-subtle text-success-emphasis',
                                    'critical' => 'bg-danger-subtle text-danger-emphasis',
                                    default => 'bg-warning-subtle text-warning-emphasis',
                                };
                            @endphp
                            <tr>
                                <td class="small fw-semibold">{{ $row['label'] }}</td>
                                <td class="small"><span class="badge {{ $badgeClass }}">{{ ucfirst($status) }}</span></td>
                                <td class="small text-muted">{{ $row['detail'] }}</td>
                                <td class="small">{{ $row['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header py-2 fw-semibold">Opening Balances Without Effective Date</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ledger</th>
                                <th class="text-end">Opening</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($issueLists['opening_without_date'] as $account)
                                <tr>
                                    <td class="small">{{ $account->code }} - {{ $account->name }}</td>
                                    <td class="small text-end">{{ number_format((float) $account->opening_balance, 2) }} {{ strtoupper((string) ($account->opening_balance_type ?? 'dr')) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center small text-muted py-3">No ledgers in this bucket.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header py-2 fw-semibold">Opening Balances Inside Current FY</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ledger</th>
                                <th>Effective Date</th>
                                <th class="text-end">Opening</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($issueLists['opening_inside_current_fy'] as $account)
                                <tr>
                                    <td class="small">{{ $account->code }} - {{ $account->name }}</td>
                                    <td class="small">{{ optional($account->opening_balance_date)->format('d-m-Y') }}</td>
                                    <td class="small text-end">{{ number_format((float) $account->opening_balance, 2) }} {{ strtoupper((string) ($account->opening_balance_type ?? 'dr')) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center small text-muted py-3">No ledgers in this bucket.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header py-2 fw-semibold">Party Ledgers Carrying Opening Balances</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ledger</th>
                                <th>Effective Date</th>
                                <th class="text-end">Opening</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($issueLists['party_opening_balances'] as $account)
                                <tr>
                                    <td class="small">{{ $account->code }} - {{ $account->name }}</td>
                                    <td class="small">{{ optional($account->opening_balance_date)->format('d-m-Y') ?: '-' }}</td>
                                    <td class="small text-end">{{ number_format((float) $account->opening_balance, 2) }} {{ strtoupper((string) ($account->opening_balance_type ?? 'dr')) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center small text-muted py-3">No ledgers in this bucket.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header py-2 fw-semibold">Pre-Opening Voucher Activity</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ledger</th>
                                <th>Opening Date</th>
                                <th class="text-end">Opening</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($issueLists['pre_opening_activity'] as $account)
                                <tr>
                                    <td class="small">{{ $account->code }} - {{ $account->name }}</td>
                                    <td class="small">{{ optional($account->opening_balance_date)->format('d-m-Y') }}</td>
                                    <td class="small text-end">{{ number_format((float) $account->opening_balance, 2) }} {{ strtoupper((string) ($account->opening_balance_type ?? 'dr')) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center small text-muted py-3">No ledgers in this bucket.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
