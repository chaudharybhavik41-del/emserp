@extends('layouts.erp')

@section('title', 'Create Production Plan from BOM')

@section('content')
@php
    $pid = (int) ($projectId ?? 0);
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="bi bi-plus-circle"></i> Create Production Plan from BOM</h2>
        <a href="{{ $pid > 0 ? route('projects.production-plans.index', ['project' => $pid]) : url('/projects') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
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

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ $pid > 0 ? route('projects.production-plans.from-bom', ['project' => $pid]) : url()->current() }}" class="row g-2 mb-3">
                <div class="col-md-8">
                    <label class="form-label">Select Project</label>
                    <select name="project_id" class="form-select" onchange="this.form.submit()">
                        <option value="">— Select —</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ (string)$projectId === (string)$p->id ? 'selected' : '' }}>
                                {{ $p->code }} — {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end justify-content-end">
                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>

            <form method="POST" action="{{ $pid > 0 ? route('projects.production-plans.from-bom.store', ['project' => $pid]) : url()->current() }}">
                @csrf
                <input type="hidden" name="project_id" value="{{ $projectId }}">

                <div class="mb-3">
                    <label class="form-label">Select Approved BOM</label>
                    <select name="bom_id" class="form-select" {{ $projectId ? '' : 'disabled' }} required>
                        <option value="">— Select BOM —</option>
                        @foreach($boms as $b)
                            <option value="{{ $b->id }}">{{ $b->bom_number }} (v{{ $b->version }}) — {{ $b->status }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Only BOMs with status finalized/active are shown.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                </div>

                <button class="btn btn-primary" {{ $projectId ? '' : 'disabled' }}>
                    <i class="bi bi-check2-circle"></i> Create Plan
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
