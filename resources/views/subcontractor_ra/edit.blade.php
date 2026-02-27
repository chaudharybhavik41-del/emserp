@extends('layouts.erp')

@section('title', 'Edit Subcontractor RA Bill')

@section('content')
<div class="container-fluid">
    @include('partials.alerts')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Subcontractor RA Bill: {{ $subcontractorRa->ra_number }}</h1>
        <div>
            <a href="{{ route('accounting.subcontractor-ra.show', $subcontractorRa) }}" class="btn btn-outline-secondary btn-sm">← Back</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @include('subcontractor_ra._form', [
                'subcontractorRa' => $subcontractorRa,
            ])
        </div>
    </div>
</div>
@endsection
