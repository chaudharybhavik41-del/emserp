@extends('layouts.erp')

@section('title', 'Employee Bank Accounts')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Bank Accounts</h4>
            <p class="text-muted mb-0">{{ $employee->employee_code }} - {{ $employee->full_name }}</p>
        </div>
        <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-outline-secondary btn-sm">Back to Employee</a>
    </div>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>{{ $editing ? 'Edit Bank Account' : 'Add Bank Account' }}</strong></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('hr.employees.bank-accounts.update', [$employee, $editing]) : route('hr.employees.bank-accounts.store', $employee) }}">
                        @csrf
                        @if($editing) @method('PUT') @endif
                        <div class="mb-2"><label class="form-label">Bank</label><input name="bank_name" class="form-control form-control-sm" value="{{ old('bank_name', $editing->bank_name ?? '') }}" required></div>
                        <div class="mb-2"><label class="form-label">Branch</label><input name="branch_name" class="form-control form-control-sm" value="{{ old('branch_name', $editing->branch_name ?? '') }}"></div>
                        <div class="mb-2"><label class="form-label">Account Number</label><input name="account_number" class="form-control form-control-sm" value="{{ old('account_number', $editing->account_number ?? '') }}" required></div>
                        <div class="mb-2"><label class="form-label">IFSC</label><input name="ifsc_code" class="form-control form-control-sm" value="{{ old('ifsc_code', $editing->ifsc_code ?? '') }}" required></div>
                        <div class="mb-2"><label class="form-label">Account Holder</label><input name="account_holder_name" class="form-control form-control-sm" value="{{ old('account_holder_name', $editing->account_holder_name ?? '') }}" required></div>
                        <div class="mb-2">
                            <label class="form-label">Account Type</label>
                            <select name="account_type" class="form-select form-select-sm" required>
                                @foreach(['savings' => 'Savings', 'current' => 'Current', 'salary' => 'Salary'] as $k => $v)
                                    <option value="{{ $k }}" @selected(old('account_type', $editing->account_type ?? 'savings') === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2"><label class="form-label">Cancelled Cheque</label><input type="file" name="cancelled_cheque" class="form-control form-control-sm"></div>
                        <div class="d-flex flex-wrap gap-3 mb-3">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_primary" value="1" id="acc_primary" @checked(old('is_primary', $editing->is_primary ?? false))><label class="form-check-label" for="acc_primary">Primary</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="acc_active" @checked(old('is_active', $editing->is_active ?? true))><label class="form-check-label" for="acc_active">Active</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_verified" value="1" id="acc_verified" @checked(old('is_verified', $editing->is_verified ?? false))><label class="form-check-label" for="acc_verified">Verified</label></div>
                        </div>
                        <div class="d-flex gap-2"><button class="btn btn-primary btn-sm">{{ $editing ? 'Update' : 'Save' }}</button>@if($editing)<a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.employees.bank-accounts.index', $employee) }}">Cancel</a>@endif</div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Bank</th><th>Account</th><th>IFSC</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                @forelse($accounts as $account)
                                    <tr>
                                        <td>{{ $account->bank_name }} @if($account->is_primary)<span class="badge bg-primary">Primary</span>@endif</td>
                                        <td>{{ $account->account_number }}</td>
                                        <td><code>{{ $account->ifsc_code }}</code></td>
                                        <td>
                                            @if($account->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif
                                            @if($account->is_verified)<span class="badge bg-info">Verified</span>@endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('hr.employees.bank-accounts.edit', [$employee, $account]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="POST" action="{{ route('hr.employees.bank-accounts.destroy', [$employee, $account]) }}" class="d-inline" onsubmit="return confirm('Delete account?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">No bank accounts added.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($accounts->hasPages())<div class="card-footer">{{ $accounts->links() }}</div>@endif
            </div>
        </div>
    </div>
</div>
@endsection
