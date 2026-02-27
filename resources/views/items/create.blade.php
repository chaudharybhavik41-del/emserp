@extends('layouts.erp')

@section('title', 'Create Item')

@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">Create Item</h1>

    <div class="card">
        <div class="card-body">
            @include('items._form', ['item' => $item])
        </div>
    </div>
</div>
@endsection
