@extends('layouts.erp')

@section('title', 'Employee Assets')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Assets</h4>
            <p class="text-muted mb-0">{{ $employee->employee_code }} - {{ $employee->full_name }}</p>
        </div>
        <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-outline-secondary btn-sm">Back to Employee</a>
    </div>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>{{ $editing ? 'Edit Asset' : 'Assign Asset' }}</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ $editing ? route('hr.employees.assets.update', [$employee, $editing]) : route('hr.employees.assets.store', $employee) }}">
                        @csrf
                        @if($editing) @method('PUT') @endif
                        <div class="mb-2"><label class="form-label">Type</label><input name="asset_type" class="form-control form-control-sm" value="{{ old('asset_type', $editing->asset_type ?? '') }}" required></div>
                        <div class="mb-2"><label class="form-label">Asset Name</label><input name="asset_name" class="form-control form-control-sm" value="{{ old('asset_name', $editing->asset_name ?? '') }}" required></div>
                        <div class="row g-2 mb-2"><div class="col-6"><input name="asset_code" class="form-control form-control-sm" placeholder="Code" value="{{ old('asset_code', $editing->asset_code ?? '') }}"></div><div class="col-6"><input name="serial_number" class="form-control form-control-sm" placeholder="Serial" value="{{ old('serial_number', $editing->serial_number ?? '') }}"></div></div>
                        <div class="row g-2 mb-2"><div class="col-6"><input name="make" class="form-control form-control-sm" placeholder="Make" value="{{ old('make', $editing->make ?? '') }}"></div><div class="col-6"><input name="model" class="form-control form-control-sm" placeholder="Model" value="{{ old('model', $editing->model ?? '') }}"></div></div>
                        <div class="row g-2 mb-2"><div class="col-6"><label class="form-label">Issued Date</label><input type="date" name="issued_date" class="form-control form-control-sm" value="{{ old('issued_date', isset($editing->issued_date) ? $editing->issued_date->format('Y-m-d') : now()->toDateString()) }}" required></div><div class="col-6"><label class="form-label">Return Date</label><input type="date" name="return_date" class="form-control form-control-sm" value="{{ old('return_date', isset($editing->return_date) ? $editing->return_date->format('Y-m-d') : '') }}"></div></div>
                        <div class="row g-2 mb-2"><div class="col-6"><input type="number" step="0.01" min="0" name="asset_value" class="form-control form-control-sm" placeholder="Asset Value" value="{{ old('asset_value', $editing->asset_value ?? 0) }}"></div><div class="col-6"><input type="number" step="0.01" min="0" name="deposit_amount" class="form-control form-control-sm" placeholder="Deposit" value="{{ old('deposit_amount', $editing->deposit_amount ?? 0) }}"></div></div>
                        <div class="mb-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select form-select-sm" required>
                                @foreach(['issued' => 'Issued', 'returned' => 'Returned', 'lost' => 'Lost', 'damaged' => 'Damaged'] as $k => $v)
                                    <option value="{{ $k }}" @selected(old('status', $editing->status ?? 'issued') === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><select name="condition_at_issue" class="form-select form-select-sm"><option value="new" @selected(old('condition_at_issue', $editing->condition_at_issue ?? 'good') === 'new')>New</option><option value="good" @selected(old('condition_at_issue', $editing->condition_at_issue ?? 'good') === 'good')>Good</option><option value="fair" @selected(old('condition_at_issue', $editing->condition_at_issue ?? 'good') === 'fair')>Fair</option></select></div>
                            <div class="col-6"><select name="condition_at_return" class="form-select form-select-sm"><option value="">Return Condition</option><option value="good" @selected(old('condition_at_return', $editing->condition_at_return ?? '') === 'good')>Good</option><option value="fair" @selected(old('condition_at_return', $editing->condition_at_return ?? '') === 'fair')>Fair</option><option value="damaged" @selected(old('condition_at_return', $editing->condition_at_return ?? '') === 'damaged')>Damaged</option></select></div>
                        </div>
                        <div class="mb-3"><textarea name="remarks" rows="2" class="form-control form-control-sm" placeholder="Remarks">{{ old('remarks', $editing->remarks ?? '') }}</textarea></div>
                        <div class="d-flex gap-2"><button class="btn btn-primary btn-sm">{{ $editing ? 'Update' : 'Assign' }}</button>@if($editing)<a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.employees.assets.index', $employee) }}">Cancel</a>@endif</div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Asset</th><th>Issue</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                @forelse($assets as $asset)
                                    <tr>
                                        <td>{{ $asset->asset_name }}<div class="small text-muted">{{ $asset->asset_type }} {{ $asset->asset_code ? '('.$asset->asset_code.')' : '' }}</div></td>
                                        <td>{{ $asset->issued_date?->format('d M Y') }}</td>
                                        <td><span class="badge bg-{{ $asset->status === 'issued' ? 'success' : ($asset->status === 'returned' ? 'secondary' : 'danger') }}">{{ ucfirst($asset->status) }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ route('hr.employees.assets.edit', [$employee, $asset]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            @if($asset->status === 'issued')
                                                <form method="POST" action="{{ route('hr.employees.assets.return', [$employee, $asset]) }}" class="d-inline" onsubmit="return confirm('Mark this asset as returned?')">@csrf <button class="btn btn-sm btn-outline-warning">Return</button></form>
                                            @endif
                                            <form method="POST" action="{{ route('hr.employees.assets.destroy', [$employee, $asset]) }}" class="d-inline" onsubmit="return confirm('Delete asset record?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">No assets assigned.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($assets->hasPages())<div class="card-footer">{{ $assets->links() }}</div>@endif
            </div>
        </div>
    </div>
</div>
@endsection
