<x-app-layout>
<div class="container-fluid">
    <h1 class="mb-3">
        Material Planning Debug
        (Project: {{ $project->name }} / BOM: {{ $bom->bom_number ?? $bom->id }})
    </h1>

    {{-- Filters (same params as selectStock) --}}
    <form method="GET" class="row g-2 mb-4">
        <input type="hidden" name="group_category" value="{{ $filters['group_category'] }}">
        <div class="col-md-3">
            <label class="form-label">Grade</label>
            <input type="text" name="grade" value="{{ $filters['grade'] }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Thickness (mm)</label>
            <input type="number" name="thickness_mm" value="{{ $filters['thickness_mm'] }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Section Profile</label>
            <input type="text" name="section_profile" value="{{ $filters['section_profile'] }}" class="form-control">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary me-2" type="submit">Apply</button>
            <a href="{{ route('projects.boms.material-planning.index', [$project, $bom]) }}" class="btn btn-secondary">
                Back to Planning
            </a>
        </div>
    </form>

    {{-- Target Group --}}
    <div class="card mb-4">
        <div class="card-header">
            Target Group (from buildRawRequirements)
        </div>
        <div class="card-body">
            @if($targetGroup)
                <pre class="mb-0">{{ json_encode($targetGroup, JSON_PRETTY_PRINT) }}</pre>
            @else
                <div class="text-muted">No target group matched these filters.</div>
            @endif
        </div>
    </div>

    {{-- All raw requirement groups (for cross-check) --}}
    <div class="card mb-4">
        <div class="card-header">
            All Raw Requirement Groups ({{ count($rawRequirements) }})
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Category</th>
                        <th>Grade</th>
                        <th>Thk (mm)</th>
                        <th>Section</th>
                        <th>Lines</th>
                        <th>Total Qty</th>
                        <th>Total Wt</th>
                        <th>Default Item</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rawRequirements as $idx => $g)
                        <tr @if($targetGroup && $targetGroup === $g) class="table-success" @endif>
                            <td>{{ $idx + 1 }}</td>
                            <td>{{ $g['category'] }}</td>
                            <td>{{ $g['grade'] }}</td>
                            <td>{{ $g['thickness_mm'] }}</td>
                            <td>{{ $g['section'] }}</td>
                            <td>{{ $g['lines'] }}</td>
                            <td>{{ $g['total_qty'] }}</td>
                            <td>{{ $g['total_weight'] }}</td>
                            <td>{{ $g['default_item_code'] }} ({{ $g['default_item_id'] }})</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-muted">No groups.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ALL reserved pieces for this BOM --}}
    <div class="card mb-4">
        <div class="card-header">
            All Pieces Reserved for this BOM (no grade / thickness filter) – {{ $allReservedForBom->count() }}
        </div>
        <div class="card-body">
            @if($allReservedForBom->isEmpty())
                <div class="text-muted">None.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category</th>
                            <th>Grade (Item)</th>
                            <th>Thk</th>
                            <th>Section</th>
                            <th>Len (mm)</th>
                            <th>Weight (kg)</th>
                            <th>Status</th>
                            <th>Item Code</th>
                            <th>Origin</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($allReservedForBom as $p)
                            <tr>
                                <td>{{ $p->id }}</td>
                                <td>{{ $p->material_category->value ?? $p->material_category }}</td>
                                <td>{{ $p->item->grade ?? '' }}</td>
                                <td>{{ $p->thickness_mm }}</td>
                                <td>{{ $p->section_profile }}</td>
                                <td>{{ $p->length_mm }}</td>
                                <td>{{ $p->weight_kg }}</td>
                                <td>{{ $p->status->value ?? $p->status }}</td>
                                <td>{{ $p->item->code ?? '' }}</td>
                                <td>
                                    P{{ $p->origin_project_id }} /
                                    B{{ $p->origin_bom_id }}
                                    ({{ $p->source_type }})
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Strict matching – this should mirror Select Stock --}}
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    Strict Reserved (same logic as "Already Reserved for this BOM")
                    – {{ $reservedStrict->count() }}
                </div>
                <div class="card-body">
                    @if($reservedStrict->isEmpty())
                        <div class="text-muted">No matches.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Grade</th>
                                    <th>Thk</th>
                                    <th>Section</th>
                                    <th>Len</th>
                                    <th>Wt</th>
                                    <th>Item</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($reservedStrict as $p)
                                    <tr>
                                        <td>{{ $p->id }}</td>
                                        <td>{{ $p->item->grade ?? '' }}</td>
                                        <td>{{ $p->thickness_mm }}</td>
                                        <td>{{ $p->section_profile }}</td>
                                        <td>{{ $p->length_mm }}</td>
                                        <td>{{ $p->weight_kg }}</td>
                                        <td>{{ $p->item->code ?? '' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    Strict Available (same logic as "Available Stock Pieces") – {{ $availableStrict->count() }}
                </div>
                <div class="card-body">
                    @if($availableStrict->isEmpty())
                        <div class="text-muted">No matches.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Grade</th>
                                    <th>Thk</th>
                                    <th>Section</th>
                                    <th>Len</th>
                                    <th>Wt</th>
                                    <th>Item</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($availableStrict as $p)
                                    <tr>
                                        <td>{{ $p->id }}</td>
                                        <td>{{ $p->item->grade ?? '' }}</td>
                                        <td>{{ $p->thickness_mm }}</td>
                                        <td>{{ $p->section_profile }}</td>
                                        <td>{{ $p->length_mm }}</td>
                                        <td>{{ $p->weight_kg }}</td>
                                        <td>{{ $p->item->code ?? '' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
</x-app-layout>
