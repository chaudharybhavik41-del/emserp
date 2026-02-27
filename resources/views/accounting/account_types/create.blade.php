@extends('layouts.erp')

@section('title', 'Add Account Type')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <h1 class="h4 mb-0">Add Account Type</h1>
        <div class="small text-muted">Company ID: {{ $companyId }}</div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.account-types.store') }}">
                @include('accounting.account_types._form', [
                    'accountType' => $accountType,
                    'submitLabel' => 'Create Type',
                ])
            </form>
        </div>
    </div>
</div>
@endsection
