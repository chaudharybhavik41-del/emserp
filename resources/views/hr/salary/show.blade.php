@extends('layouts.erp')

@section('title', 'Salary Structure Detail')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">{{ $salaryStructure->name }}</h4>
    <div class="card"><div class="card-body">
        <p><strong>Code:</strong> {{ $salaryStructure->code }}</p>
        <p><strong>Description:</strong> {{ $salaryStructure->description ?: '-' }}</p>
        <p><strong>Employees:</strong> {{ $salaryStructure->employees->count() }}</p>
        <a href="{{ route('hr.salary.edit', $salaryStructure) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        <a href="{{ route('hr.salary.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div></div>
</div>
@endsection
