@extends('layouts.erp')

@section('title', 'Edit Standard Terms Template')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Standard Terms Template</h1>
        <a href="{{ route('standard-terms.index') }}" class="btn btn-sm btn-secondary">Back to list</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('standard-terms.update', $term) }}">
        @method('PUT')
        @include('standard_terms._form')
        <div class="mt-3 text-end">
            <button type="submit" class="btn btn-primary">Update Template</button>
        </div>
    </form>
@endsection
