@extends('layouts.erp')

@section('title', 'HR Settings')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">HR Settings</h4>
    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><strong>General Settings</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('hr.settings.update') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Default Notice Period (days)</label><input type="number" min="0" max="365" class="form-control" name="default_notice_period_days" value="{{ old('default_notice_period_days', $settings['default_notice_period_days']) }}"></div>
                            <div class="col-md-6"><label class="form-label">Default Probation (months)</label><input type="number" min="0" max="24" class="form-control" name="default_probation_months" value="{{ old('default_probation_months', $settings['default_probation_months']) }}"></div>
                            <div class="col-md-6"><label class="form-label">Payroll Cutoff Day</label><input type="number" min="1" max="31" class="form-control" name="payroll_cutoff_day" value="{{ old('payroll_cutoff_day', $settings['payroll_cutoff_day']) }}"></div>
                            <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="enable_ot_approval" value="1" id="enable_ot_approval" @checked(old('enable_ot_approval', $settings['enable_ot_approval']))><label class="form-check-label" for="enable_ot_approval">Enable OT Approval Workflow</label></div></div>
                        </div>
                        <div class="mt-3"><button class="btn btn-primary">Save Settings</button></div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><strong>Statutory Slab Masters</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>PF Slabs</span><span class="badge bg-primary">{{ $statutoryCounts['pf'] }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>ESI Slabs</span><span class="badge bg-primary">{{ $statutoryCounts['esi'] }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>PT Slabs</span><span class="badge bg-primary">{{ $statutoryCounts['pt'] }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>TDS Slabs</span><span class="badge bg-primary">{{ $statutoryCounts['tds'] }}</span></div>
                    <div class="d-flex justify-content-between"><span>LWF Slabs</span><span class="badge bg-primary">{{ $statutoryCounts['lwf'] }}</span></div>
                    <hr>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.settings.pf-slabs.index') }}">PF</a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.settings.esi-slabs.index') }}">ESI</a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.settings.pt-slabs.index') }}">PT</a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.settings.tds-slabs.index') }}">TDS</a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.settings.lwf-slabs.index') }}">LWF</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
