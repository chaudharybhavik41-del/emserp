@extends('layouts.erp')

@section('title', 'Create Quotation')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">
            Create Quotation for Lead: {{ $lead->code ?? ('Lead #' . $lead->id) }}
        </h1>
        <div class="text-muted small">
            {{ $lead->title ?? '' }}
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @include('crm.quotations._form')
    </div>
</div>
@endsection
