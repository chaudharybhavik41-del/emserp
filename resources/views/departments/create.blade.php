@extends('layouts.erp')

@section('title', 'Create Department')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Create Department</h1>
</div>

<div class="card">
    <div class="card-body">
        @include('departments._form')
    </div>
</div>
@endsection
