@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Machinery Asset</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('fixed-assets.machinery.update', $asset) }}">
                @method('PUT')
                @include('fixed_assets.machinery._form')
                <div class="mt-3">
                    <button class="btn btn-primary">Update Asset</button>
                    <a href="{{ route('fixed-assets.machinery.show', $asset) }}" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
