@extends('layouts.erp')

@section('title', 'Edit Quotation')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">
            Edit Quotation: {{ $quotation->code }} (Rev {{ $quotation->revision_no }})
        </h1>
        @if($quotation->lead)
            <div class="text-muted small">
                Lead: {{ $quotation->lead->code }} - {{ $quotation->lead->title }}
            </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-body">
        @include('crm.quotations._form')
    </div>
</div>
@endsection
