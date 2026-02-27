@extends('layouts.erp')

@section('title', 'Create Lead Source')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Create Lead Source</h1>
</div>

<div class="card">
    <div class="card-body">
        @include('crm.lead_sources._form')
    </div>
</div>
@endsection
