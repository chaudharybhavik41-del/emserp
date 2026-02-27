@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <h1 class="mb-3">
        Add BOM Item (BOM: {{ $bom->bom_number }})
    </h1>

    <form action="{{ route('projects.boms.items.store', [$project, $bom]) }}" method="POST">
        @csrf

        @include('projects.boms.items.partials._form')

        <button type="submit" class="btn btn-primary">Save Item</button>
        <a href="{{ route('projects.boms.show', [$project, $bom]) }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection
