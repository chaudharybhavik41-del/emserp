@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <h1 class="mb-3">
        Edit BOM: {{ $bom->bom_number }} (Project: {{ $project->code }} - {{ $project->name }})
    </h1>

    <form action="{{ route('projects.boms.update', [$project, $bom]) }}" method="POST">
        @method('PUT')
        @include('projects.boms.partials._form')

        <button type="submit" class="btn btn-primary">
            Update BOM
        </button>
        <a href="{{ route('projects.boms.show', [$project, $bom]) }}" class="btn btn-secondary">
            Cancel
        </a>
    </form>
</div>
@endsection
