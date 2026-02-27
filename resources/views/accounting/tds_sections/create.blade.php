@extends('layouts.erp')

@section('title', 'Add TDS Section')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <h1 class="h4 mb-0">Add TDS Section</h1>
        <div class="small text-muted">Company ID: {{ $companyId }}</div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.tds-sections.store') }}">
                @csrf
                @include('accounting.tds_sections._form', ['section' => $section])
            </form>
        </div>
    </div>
</div>
@endsection
