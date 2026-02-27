@extends('layouts.erp')

@section('title', 'Create Lead Stage')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Create Lead Stage</h1>
</div>

<div class="card">
    <div class="card-body">
        @include('crm.lead_stages._form')
    </div>
</div>
@endsection
