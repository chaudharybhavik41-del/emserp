@extends('layouts.erp')

@section('title', 'Create Subcontractor Work Order')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Create Subcontractor Work Order</h1>
            <div class="text-muted small">Define the commercial terms that should flow into subcontractor RA bills.</div>
        </div>
        <a href="{{ route('accounting.subcontractor-work-orders.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('subcontractor_work_orders._form')
        </div>
    </div>
</div>
@endsection
