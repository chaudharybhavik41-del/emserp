@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    @php
        // Controller usually passes $projectId; fallback to request/route.
        $pid = (int) ($projectId ?? request()->integer('project_id') ?? request()->route('project') ?? 0);
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="bi bi-plus-circle"></i> Create Production Plan from BOM</h2>

        @if($pid > 0)
            <a href="{{ route('projects.production-plans.index', ['project' => $pid]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        @else
            <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        @endif
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ $pid > 0 ? route('projects.production-plans.from-bom', ['project' => $pid]) : url()->current() }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Project</label>
                        <select name="project_id" class="form-select" required onchange="this.form.submit()">
                            <option value="">-- Select Project --</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" @selected((int)$p->id === (int)$pid)>
                                    {{ $p->code ?? ('#'.$p->id) }} - {{ $p->name ?? '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Select project to load finalized BOM(s).</div>
                    </div>

                    <div class="col-md-6 text-end">
                        @if($pid > 0)
                            <a href="{{ route('projects.production-plans.from-bom', ['project' => $pid]) }}" class="btn btn-outline-secondary">
                                Reset
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($pid > 0)
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('projects.production-plans.from-bom.store', ['project' => $pid]) }}">
                    @csrf

                    <input type="hidden" name="project_id" value="{{ $pid }}">

                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Finalized BOM</label>
                            <select name="bom_id" class="form-select" required>
                                <option value="">-- Select BOM --</option>
                                @foreach($boms as $b)
                                    <option value="{{ $b->id }}">
                                        {{ $b->bom_number ?? ('BOM#'.$b->id) }}
                                        @if(!empty($b->version)) - v{{ $b->version }} @endif
                                        @if(!empty($b->status)) ({{ $b->status }}) @endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Only BOMs with status <b>finalized</b> / <b>active</b> are listed.</div>
                        </div>

                        <div class="col-md-4 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle"></i> Create Production Plan
                            </button>
                        </div>
                    </div>

                    @if(isset($boms) && $boms->isEmpty())
                        <div class="alert alert-warning mt-3 mb-0">
                            No finalized BOM found for this project. Please finalize BOM first.
                        </div>
                    @endif
                </form>
            </div>
        </div>
    @else
        <div class="alert alert-info">
            Please select a Project to continue.
        </div>
    @endif
</div>
@endsection
