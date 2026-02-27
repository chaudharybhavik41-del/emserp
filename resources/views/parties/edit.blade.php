@extends('layouts.erp')

@section('title', 'Edit Party')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Party: {{ $party->code }} - {{ $party->name }}</h1>

        <div>
            <a href="{{ route('parties.show', $party) }}" class="btn btn-sm btn-outline-secondary">
                View details
            </a>
            <a href="{{ route('parties.index') }}" class="btn btn-sm btn-outline-secondary ms-1">
                Back to list
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @include('parties._form', ['party' => $party])
        </div>
    </div>
@endsection
