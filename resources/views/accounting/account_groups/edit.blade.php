@extends('layouts.erp')

@section('title', 'Edit Account Group')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <h1 class="h4 mb-0">Edit Account Group</h1>
        <div class="small text-muted">Company ID: {{ $companyId }}</div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.account-groups.update', $accountGroup->id) }}">
                @method('PUT')
                @include('accounting.account_groups._form', [
                    'group' => $accountGroup,
                    'parentOptions' => $parentOptions,
                    'submitLabel' => 'Update Group',
                ])
            </form>
        </div>
    </div>
</div>
@endsection
