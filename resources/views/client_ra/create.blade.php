@extends('layouts.erp')

@section('title', 'New Client RA Bill')

@section('content')
<div class="container-fluid">
    @include('partials.alerts')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">New Client RA Bill (Sales Invoice)</h1>
        <a href="{{ route('accounting.client-ra.index') }}" class="btn btn-outline-secondary btn-sm">← Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('client_ra._form', [
                'clientRa' => null,
            ])
        </div>
    </div>
</div>
@endsection
