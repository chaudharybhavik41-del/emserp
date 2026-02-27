@extends('layouts.erp')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Template {{ $template->template_code }}</h4>
        <small class="text-muted">
            {{ $template->name }}
        </small>
    </div>
    <div class="text-end">
        <a href="{{ route('bom-templates.index') }}" class="btn btn-outline-secondary btn-sm">
            Back to Templates
        </a>

        @can('project.bom_template.update')
            <a href="{{ route('bom-templates.edit', $template) }}" class="btn btn-outline-primary btn-sm">
                Edit Header
            </a>
        @endcan

        @can('project.bom_template.create')
            <a href="{{ route('bom-templates.create-bom-form', $template) }}"
               class="btn btn-success btn-sm">
                Create BOM for Project
            </a>
        @endcan
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        Template Summary
    </div>
    <div class="card-body">
        <div class="row mb-2">
            <div class="col-md-3">
                <strong>Code:</strong> {{ $template->template_code }}
            </div>
            <div class="col-md-3">
                <strong>Name:</strong> {{ $template->name }}
            </div>
            <div class="col-md-2">
                <strong>Structure:</strong> {{ $template->structure_type ?? '-' }}
            </div>
            <div class="col-md-2">
                <strong>Status:</strong> {{ ucfirst($template->status) }}
            </div>
            <div class="col-md-2">
                <strong>Total Weight:</strong> {{ number_format($template->total_weight, 3) }} kg
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-12">
                <strong>Description:</strong>
                {{ $template->description ?? '-' }}
            </div>
        </div>

        @if(!empty($categorySummary))
            <hr>
            <h6>Category-wise Summary (leaf materials)</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Material Category</th>
                            <th>Lines</th>
                            <th>Total Weight (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categorySummary as $cat => $row)
                            <tr>
                                <td>{{ ucfirst(str_replace('_', ' ', $cat)) }}</td>
                                <td>{{ $row['lines'] }}</td>
                                <td>{{ number_format($row['total_weight'], 3) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@include('bom-templates.partials._items_table', [
    'template' => $template,
    'assemblyWeights' => $assemblyWeights,
])
@endsection
