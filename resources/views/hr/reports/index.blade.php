@extends('layouts.erp')

@section('title', 'HR Reports')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">HR Reports</h4>

    <div class="row g-3 mb-3">
        @foreach($cards as $card)
            <div class="col-md-3">
                <div class="card"><div class="card-body"><small class="text-muted">{{ $card['title'] }}</small><h4 class="mb-0">{{ $card['value'] }}</h4></div></div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary btn-sm" href="{{ route('hr.reports.headcount') }}">Headcount</a>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('hr.reports.attrition') }}">Attrition</a>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('hr.reports.birthday') }}">Birthday</a>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('hr.reports.anniversary') }}">Anniversary</a>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('hr.reports.probation-due') }}">Probation Due</a>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('hr.reports.contract-expiry') }}">Contract Expiry</a>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('hr.reports.document-expiry') }}">Document Expiry</a>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('hr.reports.employee-directory') }}">Employee Directory</a>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('hr.reports.muster-roll') }}">Muster Roll</a>
            </div>
        </div>
    </div>
</div>
@endsection
