@extends('layouts.erp')

@section('title', 'Edit User')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Edit User: {{ $user->name }}</h1>
                <div>
                    <a href="{{ route('users.show', $user) }}" class="btn btn-outline-info">
                        <i class="bi bi-eye me-1"></i> View
                    </a>
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>

            @include('users._form', ['user' => $user])
        </div>
    </div>
</div>
@endsection
