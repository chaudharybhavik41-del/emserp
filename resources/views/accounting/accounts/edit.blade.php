@extends('layouts.erp')

@section('title', 'Edit Account')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Edit Account: {{ $account->name }}</h1>
        <a href="{{ route('accounting.accounts.index') }}" class="btn btn-outline-secondary btn-sm">
            Back to Chart
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger py-2">
            Please fix the highlighted fields and try again.
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.accounts.update', $account) }}">
                @method('PUT')
                @include('accounting.accounts._form', ['account' => $account])
            </form>
        </div>
    </div>
</div>
@endsection
