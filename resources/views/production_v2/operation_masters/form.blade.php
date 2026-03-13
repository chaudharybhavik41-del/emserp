@extends('layouts.erp')

@section('title', $operationMaster->exists ? 'Edit Production V2 Operation Master' : 'Create Production V2 Operation Master')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $operationMaster->exists ? 'Edit Process Master' : 'Create Process Master' }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'operation_masters'])

    <div class="alert alert-info">
        Production planning owns route and process definition here. Create only reusable shop-floor process codes, then sequence them inside route templates.
    </div>

    <form method="POST" action="{{ $operationMaster->exists ? route('projects.production-v2.operation-masters.update', ['project' => $project->id, 'operationMaster' => $operationMaster->id]) : route('projects.production-v2.operation-masters.store', ['project' => $project->id]) }}">
        @csrf
        @if($operationMaster->exists)
            @method('PUT')
        @endif

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Code</label>
                        @if($operationMaster->is_system)
                            <input type="hidden" name="code" value="{{ old('code', $operationMaster->code) }}">
                        @endif
                        <input name="code" class="form-control" value="{{ old('code', $operationMaster->code) }}" @disabled($operationMaster->is_system) required>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label">Name</label>
                        <input name="name" class="form-control" value="{{ old('name', $operationMaster->name) }}" required>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Applies To</label>
                        @if($operationMaster->is_system)
                            <input type="hidden" name="applies_to" value="{{ old('applies_to', $operationMaster->applies_to) }}">
                        @endif
                        <select name="applies_to" class="form-select" data-erp-select data-hide-search="true" @disabled($operationMaster->is_system)>
                            @foreach(['part' => 'Part', 'assembly' => 'Assembly'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('applies_to', $operationMaster->applies_to) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Entry Mode</label>
                        @if($operationMaster->is_system)
                            <input type="hidden" name="entry_mode" value="{{ old('entry_mode', $operationMaster->entry_mode) }}">
                        @endif
                        <select name="entry_mode" class="form-select" data-erp-select data-hide-search="true" @disabled($operationMaster->is_system)>
                            @foreach(['generic' => 'Generic', 'specialized' => 'Specialized'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('entry_mode', $operationMaster->entry_mode) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Entry Route</label>
                        @if($operationMaster->is_system)
                            <input type="hidden" name="entry_route" value="{{ old('entry_route', $operationMaster->entry_route) }}">
                        @endif
                        <input name="entry_route" class="form-control" value="{{ old('entry_route', $operationMaster->entry_route) }}" @disabled($operationMaster->is_system) placeholder="projects.production-v2.operation-events.create">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Sort Order</label>
                        <input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $operationMaster->sort_order ?: 0) }}">
                    </div>
                    <div class="col-12 col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="requires_machine" value="1" id="requires_machine" @checked(old('requires_machine', $operationMaster->requires_machine))>
                            <label class="form-check-label" for="requires_machine">Requires Machine</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="requires_qc" value="1" id="requires_qc" @checked(old('requires_qc', $operationMaster->requires_qc))>
                            <label class="form-check-label" for="requires_qc">QC Usually Needed</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $operationMaster->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" rows="3" class="form-control">{{ old('remarks', $operationMaster->remarks) }}</textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2 justify-content-end">
                <a href="{{ route('projects.production-v2.operation-masters.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">{{ $operationMaster->exists ? 'Update' : 'Create' }}</button>
            </div>
        </div>
    </form>
@endsection
