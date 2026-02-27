@extends('layouts.erp')

@section('title', $title)

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">{{ $title }}</h4>
    @include('partials.flash')

    <div class="card"><div class="card-body">
        <form method="POST" action="{{ isset($slab) ? route('hr.settings.' . $type . '-slabs.update', $slab) : route('hr.settings.' . $type . '-slabs.store') }}">
            @csrf
            @if(isset($slab)) @method('PUT') @endif

            @if($type === 'pf')
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Effective From</label><input type="date" class="form-control" name="effective_from" value="{{ old('effective_from', isset($slab->effective_from) ? $slab->effective_from->format('Y-m-d') : now()->toDateString()) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Effective To</label><input type="date" class="form-control" name="effective_to" value="{{ old('effective_to', isset($slab->effective_to) ? $slab->effective_to->format('Y-m-d') : '') }}"></div>
                    <div class="col-md-4"><label class="form-label">Wage Ceiling</label><input type="number" min="0" step="0.01" class="form-control" name="wage_ceiling" value="{{ old('wage_ceiling', $slab->wage_ceiling ?? 15000) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Employee %</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="employee_contribution_rate" value="{{ old('employee_contribution_rate', $slab->employee_contribution_rate ?? 12) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Employer PF %</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="employer_pf_rate" value="{{ old('employer_pf_rate', $slab->employer_pf_rate ?? 3.67) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Employer EPS %</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="employer_eps_rate" value="{{ old('employer_eps_rate', $slab->employer_eps_rate ?? 8.33) }}" required></div>
                    <div class="col-md-4"><label class="form-label">EDLI %</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="employer_edli_rate" value="{{ old('employer_edli_rate', $slab->employer_edli_rate ?? 0.50) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Admin Charges %</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="admin_charges_rate" value="{{ old('admin_charges_rate', $slab->admin_charges_rate ?? 0.50) }}" required></div>
                    <div class="col-md-4"><label class="form-label">EDLI Admin %</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="edli_admin_rate" value="{{ old('edli_admin_rate', $slab->edli_admin_rate ?? 0.01) }}" required></div>
                </div>
            @elseif($type === 'esi')
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Effective From</label><input type="date" class="form-control" name="effective_from" value="{{ old('effective_from', isset($slab->effective_from) ? $slab->effective_from->format('Y-m-d') : now()->toDateString()) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Effective To</label><input type="date" class="form-control" name="effective_to" value="{{ old('effective_to', isset($slab->effective_to) ? $slab->effective_to->format('Y-m-d') : '') }}"></div>
                    <div class="col-md-4"><label class="form-label">Wage Ceiling</label><input type="number" min="0" step="0.01" class="form-control" name="wage_ceiling" value="{{ old('wage_ceiling', $slab->wage_ceiling ?? 21000) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Employee %</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="employee_rate" value="{{ old('employee_rate', $slab->employee_rate ?? 0.75) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Employer %</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="employer_rate" value="{{ old('employer_rate', $slab->employer_rate ?? 3.25) }}" required></div>
                </div>
            @elseif($type === 'pt')
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">State Code</label><input class="form-control" name="state_code" value="{{ old('state_code', $slab->state_code ?? '') }}" required></div>
                    <div class="col-md-5"><label class="form-label">State Name</label><input class="form-control" name="state_name" value="{{ old('state_name', $slab->state_name ?? '') }}" required></div>
                    <div class="col-md-4"><label class="form-label">Effective From</label><input type="date" class="form-control" name="effective_from" value="{{ old('effective_from', isset($slab->effective_from) ? $slab->effective_from->format('Y-m-d') : now()->toDateString()) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Effective To</label><input type="date" class="form-control" name="effective_to" value="{{ old('effective_to', isset($slab->effective_to) ? $slab->effective_to->format('Y-m-d') : '') }}"></div>
                    <div class="col-md-4"><label class="form-label">Salary From</label><input type="number" min="0" step="0.01" class="form-control" name="salary_from" value="{{ old('salary_from', $slab->salary_from ?? '') }}" required></div>
                    <div class="col-md-4"><label class="form-label">Salary To</label><input type="number" min="0" step="0.01" class="form-control" name="salary_to" value="{{ old('salary_to', $slab->salary_to ?? '') }}" required></div>
                    <div class="col-md-4"><label class="form-label">Tax Amount</label><input type="number" min="0" step="0.01" class="form-control" name="tax_amount" value="{{ old('tax_amount', $slab->tax_amount ?? '') }}" required></div>
                    <div class="col-md-4"><label class="form-label">Frequency</label><select name="frequency" class="form-select" required><option value="monthly" @selected(old('frequency', $slab->frequency ?? 'monthly') === 'monthly')>Monthly</option><option value="annual" @selected(old('frequency', $slab->frequency ?? '') === 'annual')>Annual</option></select></div>
                    <div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-select" required><option value="all" @selected(old('gender', $slab->gender ?? 'all') === 'all')>All</option><option value="male" @selected(old('gender', $slab->gender ?? '') === 'male')>Male</option><option value="female" @selected(old('gender', $slab->gender ?? '') === 'female')>Female</option></select></div>
                </div>
            @elseif($type === 'tds')
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Financial Year</label><input class="form-control" name="financial_year" value="{{ old('financial_year', $slab->financial_year ?? '') }}" required></div>
                    <div class="col-md-3"><label class="form-label">Regime</label><select name="regime" class="form-select" required><option value="new" @selected(old('regime', $slab->regime ?? 'new') === 'new')>New</option><option value="old" @selected(old('regime', $slab->regime ?? '') === 'old')>Old</option></select></div>
                    <div class="col-md-3"><label class="form-label">Category</label><select name="category" class="form-select" required><option value="general" @selected(old('category', $slab->category ?? 'general') === 'general')>General</option><option value="senior" @selected(old('category', $slab->category ?? '') === 'senior')>Senior</option><option value="super_senior" @selected(old('category', $slab->category ?? '') === 'super_senior')>Super Senior</option></select></div>
                    <div class="col-md-3"><label class="form-label">Tax %</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="tax_percent" value="{{ old('tax_percent', $slab->tax_percent ?? '') }}" required></div>
                    <div class="col-md-4"><label class="form-label">Income From</label><input type="number" min="0" step="0.01" class="form-control" name="income_from" value="{{ old('income_from', $slab->income_from ?? '') }}" required></div>
                    <div class="col-md-4"><label class="form-label">Income To</label><input type="number" min="0" step="0.01" class="form-control" name="income_to" value="{{ old('income_to', $slab->income_to ?? '') }}" required></div>
                    <div class="col-md-2"><label class="form-label">Surcharge %</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="surcharge_percent" value="{{ old('surcharge_percent', $slab->surcharge_percent ?? 0) }}"></div>
                    <div class="col-md-2"><label class="form-label">Cess %</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="cess_percent" value="{{ old('cess_percent', $slab->cess_percent ?? 4) }}"></div>
                </div>
            @else
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">State Code</label><input class="form-control" name="state_code" value="{{ old('state_code', $slab->state_code ?? '') }}" required></div>
                    <div class="col-md-5"><label class="form-label">State Name</label><input class="form-control" name="state_name" value="{{ old('state_name', $slab->state_name ?? '') }}" required></div>
                    <div class="col-md-4"><label class="form-label">Effective From</label><input type="date" class="form-control" name="effective_from" value="{{ old('effective_from', isset($slab->effective_from) ? $slab->effective_from->format('Y-m-d') : now()->toDateString()) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Effective To</label><input type="date" class="form-control" name="effective_to" value="{{ old('effective_to', isset($slab->effective_to) ? $slab->effective_to->format('Y-m-d') : '') }}"></div>
                    <div class="col-md-4"><label class="form-label">Employee Contribution</label><input type="number" min="0" step="0.01" class="form-control" name="employee_contribution" value="{{ old('employee_contribution', $slab->employee_contribution ?? '') }}" required></div>
                    <div class="col-md-4"><label class="form-label">Employer Contribution</label><input type="number" min="0" step="0.01" class="form-control" name="employer_contribution" value="{{ old('employer_contribution', $slab->employer_contribution ?? '') }}" required></div>
                    <div class="col-md-4"><label class="form-label">Frequency</label><select name="frequency" class="form-select" required><option value="monthly" @selected(old('frequency', $slab->frequency ?? 'monthly') === 'monthly')>Monthly</option><option value="half_yearly" @selected(old('frequency', $slab->frequency ?? '') === 'half_yearly')>Half Yearly</option><option value="annual" @selected(old('frequency', $slab->frequency ?? '') === 'annual')>Annual</option></select></div>
                </div>
            @endif

            <div class="form-check mt-3"><input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $slab->is_active ?? true))><label class="form-check-label" for="is_active">Active</label></div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary">Save</button>
                <a href="{{ route('hr.settings.' . $type . '-slabs.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div></div>
</div>
@endsection
