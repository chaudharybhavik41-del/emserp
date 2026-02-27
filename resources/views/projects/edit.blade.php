@extends('layouts.erp')

@section('title', 'Edit Project')

@section('page_header')
    <div>
        <h1 class="h5 mb-0">Edit Project</h1>
        <small class="text-muted">
            Update details for project
            <span class="fw-semibold">{{ $project->code }}</span>.
        </small>
    </div>

    <a href="{{ route('projects.show', $project) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Project
    </a>
@endsection

@section('content')
    {{-- The shared form partial contains the form tag, fields and buttons --}}
    @include('projects._form')
@endsection
