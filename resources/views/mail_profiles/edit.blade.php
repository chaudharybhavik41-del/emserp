@extends('layouts.erp')

@section('title', 'Edit Mail Profile')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Edit Mail Profile: {{ $profile->code }}</h1>
</div>

<div class="card">
    <div class="card-body">
        @include('mail_profiles._form')
    </div>
</div>
@endsection
