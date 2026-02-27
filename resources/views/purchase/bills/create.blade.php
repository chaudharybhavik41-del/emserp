@extends('layouts.erp')

@section('title', 'New Purchase Bill')

@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">New Purchase Bill</h1>

    <div class="card">
        <div class="card-body">
            @include('purchase.bills._form', ['bill' => $bill])
        </div>
    </div>
</div>
@endsection
