@extends('layouts.erp')

@section('title', 'Create Mail Profile')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Create Mail Profile</h1>
</div>

<div class="card">
    <div class="card-body">
        @include('mail_profiles._form')
    </div>
</div>
@endsection
