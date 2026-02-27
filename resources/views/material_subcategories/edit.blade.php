@extends('layouts.erp')

@section('title', 'Edit Material Subcategory')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Edit Material Subcategory: {{ $subcategory->code }}</h1>
</div>

<div class="card">
    <div class="card-body">
        @include('material_subcategories._form')
    </div>
</div>
@endsection
