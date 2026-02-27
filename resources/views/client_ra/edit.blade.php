@extends('layouts.erp')

@section('title', 'Edit Client RA Bill')

@section('content')
<div class="container-fluid">
    @include('partials.alerts')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Client RA Bill: {{ $clientRa->ra_number }}</h1>
        <a href="{{ route('accounting.client-ra.show', $clientRa) }}" class="btn btn-outline-secondary btn-sm">← Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('client_ra._form', [
                'clientRa' => $clientRa,
            ])
        </div>
    </div>
</div>
@endsection
