@extends('layouts.erp')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="mb-1">Cutting Plans</h1>
                <div class="text-muted">
                    Project: {{ $project->code ?? $project->name }} |
                    BOM: {{ $bom->bom_number ?? ('BOM #' . $bom->id) }}
                </div>
            </div>
            <div>
                <a href="{{ route('projects.boms.show', [$project, $bom]) }}" class="btn btn-outline-secondary">
                    Back to BOM
                </a>
                <a href="{{ route('projects.boms.cutting-plans.create', [$project, $bom]) }}"
                   class="btn btn-primary">
                    New Cutting Plan
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Grade</th>
                        <th>Thickness</th>
                        <th>Status</th>
                        <th>Plates</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($plans as $plan)
                        <tr>
                            <td>{{ $plan->name }}</td>
                            <td>{{ $plan->grade ?? '-' }}</td>
                            <td>{{ $plan->thickness_mm }} mm</td>
                            <td>{{ ucfirst($plan->status) }}</td>
                            <td>{{ $plan->plates()->count() }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.boms.cutting-plans.edit', [$project, $bom, $plan]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No cutting plans defined yet for this BOM.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
