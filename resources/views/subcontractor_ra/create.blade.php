@extends('layouts.erp')

@section('title', 'New Subcontractor RA Bill')

@section('content')
<div class="container-fluid">
    @include('partials.alerts')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">New Subcontractor RA Bill</h1>
        <a href="{{ route('accounting.subcontractor-ra.index') }}" class="btn btn-outline-secondary btn-sm">← Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('subcontractor_ra._form', [
                'subcontractorRa' => null,
            ])
        </div>
    </div>
</div>
@endsection
