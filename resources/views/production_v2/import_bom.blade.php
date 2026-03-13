@extends('layouts.erp')

@section('title', 'Import Production V2 From BOM')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Import Production V2 From BOM</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('production-v2.project.design', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">
        Back To Design Module
    </a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('projects.production-v2.import-bom.store', ['project' => $project->id]) }}">
                @csrf

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Finalized / Active BOM</label>
                        <select name="bom_id" class="form-select @error('bom_id') is-invalid @enderror" required data-erp-select data-placeholder="Select BOM">
                            <option value="">Select BOM</option>
                            @foreach($boms as $bom)
                                <option value="{{ $bom->id }}" @selected((string) old('bom_id') === (string) $bom->id)>
                                    {{ $bom->bom_number ?: ('BOM #' . $bom->id) }}@if($bom->version) - v{{ $bom->version }}@endif ({{ strtoupper((string) $bom->status) }})
                                </option>
                            @endforeach
                        </select>
                        @error('bom_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="replace_existing" name="replace_existing" @checked(old('replace_existing', '1'))>
                            <label class="form-check-label" for="replace_existing">
                                Replace existing V2 planning records for this project
                            </label>
                        </div>
                        <div class="form-text">This deletes current V2 part definitions, assemblies, and assembly requirements only. It will block if downstream V2 execution data already exists.</div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="activate_v2" name="activate_v2" @checked(old('activate_v2', '1'))>
                            <label class="form-check-label" for="activate_v2">
                                Mark project as `V2 Enabled` after import
                            </label>
                        </div>
                    </div>

                    @if($boms->isEmpty())
                        <div class="col-12">
                            <div class="alert alert-warning mb-0">
                                No finalized or active BOM found for this project.
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" @disabled($boms->isEmpty())>
                        Import To Production V2
                    </button>
                    <a href="{{ route('production-v2.project.design', ['project' => $project->id]) }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
