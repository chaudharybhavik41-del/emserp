@extends('layouts.erp')

@section('title', 'CRM - Edit Breakup Template')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Breakup Template</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('crm.quotation-breakup-templates.show', $template) }}" class="btn btn-sm btn-outline-light">
                View
            </a>
            <a href="{{ route('crm.quotation-breakup-templates.index') }}" class="btn btn-sm btn-outline-secondary">
                Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger py-2">
            Please fix the highlighted errors.
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('crm.quotation-breakup-templates.update', $template) }}">
                @csrf
                @method('PUT')
                @include('crm.quotation_breakup_templates._form', ['template' => $template])
            </form>
        </div>
    </div>
@endsection
