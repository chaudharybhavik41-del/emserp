@extends('layouts.erp')

@section('title', 'Edit Subcontractor Work Order')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Edit Subcontractor Work Order</h1>
            <div class="text-muted small">{{ $subcontractorWorkOrder->work_order_number }}</div>
        </div>
        <a href="{{ route('accounting.subcontractor-work-orders.show', $subcontractorWorkOrder) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('subcontractor_work_orders._form')
        </div>
    </div>
</div>
@endsection
