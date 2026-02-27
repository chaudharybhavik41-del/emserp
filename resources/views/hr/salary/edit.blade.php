@extends('layouts.erp')

@section('title', 'Edit Salary Structure')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-2">Edit Salary Structure (Legacy Screen)</h4>
    <p class="text-muted">Use the current salary structure editor to modify component mappings and calculation rules.</p>

    <div class="card">
        <div class="card-body">
            <p class="mb-2"><strong>Code:</strong> {{ $salaryStructure->code }}</p>
            <p class="mb-2"><strong>Name:</strong> {{ $salaryStructure->name }}</p>
            <p class="mb-3"><strong>Description:</strong> {{ $salaryStructure->description ?: '-' }}</p>

            <a href="{{ route('hr.salary-structures.edit', $salaryStructure) }}" class="btn btn-primary btn-sm">Open Full Editor</a>
            <a href="{{ route('hr.salary.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>
</div>
@endsection
