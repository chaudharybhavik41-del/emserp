@extends('layouts.erp')

@section('title', 'Edit Lead Stage')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Edit Lead Stage: {{ $lead_stage->code }}</h1>
</div>

<div class="card">
    <div class="card-body">
        @include('crm.lead_stages._form')
    </div>
</div>
@endsection
