@extends('layouts.erp')

@section('title', 'Create Project')

@section('page_header')
    <div>
        <h1 class="h5 mb-0">Create Project</h1>
        <small class="text-muted">Set up a new project in the system.</small>
    </div>

    <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Projects
    </a>
@endsection

@section('content')
    {{-- The shared form partial contains the form tag, fields and buttons --}}
    @include('projects._form')
@endsection
