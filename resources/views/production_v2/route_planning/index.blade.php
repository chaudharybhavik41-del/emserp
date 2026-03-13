@extends('layouts.erp')

@section('title', 'Production V2 Route Planning')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Route Planning</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'route_planning'])

    <div class="alert alert-info">
        Production planning owns route assignment. Design defines parts and assemblies; production selects the applicable part and assembly routes here and the route snapshot is refreshed immediately.
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Parts</div><div class="display-6 mb-0">{{ number_format($summary['part_total']) }}</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Part Routes Assigned</div><div class="display-6 mb-0">{{ number_format($summary['part_routed']) }}</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Assemblies</div><div class="display-6 mb-0">{{ number_format($summary['assembly_total']) }}</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-body-secondary">Assembly Routes Assigned</div><div class="display-6 mb-0">{{ number_format($summary['assembly_routed']) }}</div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header">Part Route Assignment</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Part</th>
                                    <th class="text-end">Qty</th>
                                    <th>Current Route</th>
                                    <th style="width: 32%">Assign</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($parts as $part)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $part->part_code }}</div>
                                        <div class="small text-body-secondary">{{ $part->part_name }}</div>
                                    </td>
                                    <td class="text-end">{{ number_format((float) $part->required_qty, 3) }} {{ $part->uom?->code }}</td>
                                    <td>{{ $part->routeTemplate?->template_code ? $part->routeTemplate->template_code . ' - ' . $part->routeTemplate->template_name : 'No route' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('projects.production-v2.route-planning.parts.update', ['project' => $project->id, 'part' => $part->id]) }}" class="d-flex gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="parts_page" value="{{ $parts->currentPage() }}">
                                            <input type="hidden" name="assemblies_page" value="{{ $assemblies->currentPage() }}">
                                            <select name="route_template_id" class="form-select form-select-sm" data-erp-select data-allow-clear="1">
                                                <option value="">No route</option>
                                                @foreach($partTemplates as $template)
                                                    <option value="{{ $template->id }}" @selected((int) $part->route_template_id === (int) $template->id)>{{ $template->template_code }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No parts available for route planning.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">{{ $parts->appends(['assemblies_page' => $assemblies->currentPage()])->links() }}</div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header">Assembly Route Assignment</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Assembly</th>
                                    <th class="text-end">Parts</th>
                                    <th>Current Route</th>
                                    <th style="width: 32%">Assign</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($assemblies as $assembly)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $assembly->assembly_code }}</div>
                                        <div class="small text-body-secondary">{{ $assembly->assembly_name }}</div>
                                    </td>
                                    <td class="text-end">{{ number_format($assembly->requirements_count) }}</td>
                                    <td>{{ $assembly->routeTemplate?->template_code ? $assembly->routeTemplate->template_code . ' - ' . $assembly->routeTemplate->template_name : 'No route' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('projects.production-v2.route-planning.assemblies.update', ['project' => $project->id, 'assembly' => $assembly->id]) }}" class="d-flex gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="parts_page" value="{{ $parts->currentPage() }}">
                                            <input type="hidden" name="assemblies_page" value="{{ $assemblies->currentPage() }}">
                                            <select name="route_template_id" class="form-select form-select-sm" data-erp-select data-allow-clear="1">
                                                <option value="">No route</option>
                                                @foreach($assemblyTemplates as $template)
                                                    <option value="{{ $template->id }}" @selected((int) $assembly->route_template_id === (int) $template->id)>{{ $template->template_code }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No assemblies available for route planning.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">{{ $assemblies->appends(['parts_page' => $parts->currentPage()])->links() }}</div>
            </div>
        </div>
    </div>
@endsection
