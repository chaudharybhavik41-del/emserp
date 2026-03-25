@extends('layouts.erp')

@php
    $targetUrl = isset($employee->id) && $employee->id
        ? route('hr.employees.edit', $employee)
        : route('hr.employees.create');
@endphp

@section('title', 'Redirecting')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <h1 class="h5 mb-2">Redirecting to the current employee form</h1>
            <p class="mb-0">
                This legacy employee form is no longer used.
                <a href="{{ $targetUrl }}">Continue to the current employee form</a>.
            </p>
        </div>
    </div>
</div>

<script>
    window.location.replace(@json($targetUrl));
</script>
@endsection
