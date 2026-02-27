@extends('layouts.erp')

@section('title', 'Add Account Group')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <h1 class="h4 mb-0">Add Account Group</h1>
        <div class="small text-muted">Company ID: {{ $companyId }}</div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.account-groups.store') }}">
                @include('accounting.account_groups._form', [
                    'group' => $group,
                    'parentOptions' => $parentOptions,
                    'submitLabel' => 'Create Group',
                ])
            </form>
        </div>
    </div>
</div>
@endsection
