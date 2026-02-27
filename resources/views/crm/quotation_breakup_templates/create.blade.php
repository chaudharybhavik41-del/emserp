@extends('layouts.erp')

@section('title', 'CRM - Add Breakup Template')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Add Breakup Template</h1>
        <a href="{{ route('crm.quotation-breakup-templates.index') }}" class="btn btn-sm btn-outline-secondary">
            Back
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger py-2">
            Please fix the highlighted errors.
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('crm.quotation-breakup-templates.store') }}">
                @csrf
                @include('crm.quotation_breakup_templates._form', ['template' => $template])
            </form>
        </div>
    </div>
@endsection
