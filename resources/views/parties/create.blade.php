
@extends('layouts.erp')

@section('title', 'Create Party')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Create Party</h1>

        <a href="{{ route('parties.index') }}" class="btn btn-sm btn-outline-secondary">
            Back to list
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            {{-- This includes the FULL form with address, GSTIN/PAN, MSME, etc. --}}
            @include('parties._form', ['party' => $party])
        </div>
    </div>
@endsection
