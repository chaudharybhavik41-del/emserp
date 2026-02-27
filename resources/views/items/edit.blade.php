@extends('layouts.erp')

@section('title', 'Edit Item')

@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">Edit Item: {{ $item->code }} - {{ $item->name }}</h1>

    <div class="card">
        <div class="card-body">
            @include('items._form', ['item' => $item])
        </div>
    </div>
</div>
@endsection
