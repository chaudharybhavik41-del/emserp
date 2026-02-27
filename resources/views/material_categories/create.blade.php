@extends('layouts.erp')

@section('title', 'Create Material Category')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Create Material Category</h1>
</div>

<div class="card">
    <div class="card-body">
        @include('material_categories._form')
    </div>
</div>
@endsection
