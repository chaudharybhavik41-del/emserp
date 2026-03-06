@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Create Machinery Asset</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('fixed-assets.machinery.store') }}">
                @include('fixed_assets.machinery._form')
                <div class="mt-3">
                    <button class="btn btn-primary">Save Asset</button>
                    <a href="{{ route('fixed-assets.machinery.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
