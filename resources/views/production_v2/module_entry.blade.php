@extends('layouts.erp')

@section('title', $moduleTitle ?? 'Production V2')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $moduleTitle ?? 'Production V2' }}</h1>
        <div class="small text-body-secondary">{{ $moduleDescription ?? 'Select project and continue.' }}</div>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                    <label class="form-label mb-1" for="q">Search project</label>
                    <input type="text" id="q" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Code or name">
                </div>

                <div class="col-12 col-lg-7">
                    <label class="form-label mb-1" for="project_id">Project</label>
                    <select id="project_id" name="project_id" class="form-select" required data-erp-select data-placeholder="Select project">
                        <option value="">Select Project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">
                                {{ $project->code }} - {{ $project->name }}
                                @php $mode = $project->production_mode ?? 'legacy_only'; @endphp
                                @if($mode === 'v2_enabled')
                                    [V2 ENABLED]
                                @elseif($mode === 'legacy_to_v2_transition')
                                    [TRANSITION]
                                @else
                                    [LEGACY]
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-lg-2 d-grid">
                    <button type="submit" class="btn btn-primary">{{ $buttonLabel ?? 'Open Module' }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
