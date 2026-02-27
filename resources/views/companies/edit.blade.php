@extends('layouts.erp')

@section('title', 'Edit Company')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Edit Company: {{ $company->code }}</h1>
</div>

<div class="card">
    <div class="card-body">
        @include('companies._form')
    </div>
</div>
@endsection
