@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <h1 class="mb-3">Create BOM for Project: {{ $project->code }} - {{ $project->name }}</h1>

    <form action="{{ route('projects.boms.store', $project) }}" method="POST">
        @include('projects.boms.partials._form')

        <button type="submit" class="btn btn-primary">
            Save BOM
        </button>
        <a href="{{ route('projects.boms.index', $project) }}" class="btn btn-secondary">
            Cancel
        </a>
    </form>
</div>
@endsection
