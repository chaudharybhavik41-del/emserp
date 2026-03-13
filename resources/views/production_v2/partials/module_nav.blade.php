@php
    $active = $active ?? '';
@endphp

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('production-v2.project', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'workbench' ? 'btn-dark' : 'btn-outline-dark' }}">Workbench</a>
            <a href="{{ route('projects.production-v2.dprs.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'dprs' ? 'btn-primary' : 'btn-outline-primary' }}">Daily DPR</a>
            <a href="{{ route('projects.production-v2.route-templates.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'route_templates' ? 'btn-primary' : 'btn-outline-primary' }}">Routes</a>
            <a href="{{ route('projects.production-v2.route-planning.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'route_planning' ? 'btn-primary' : 'btn-outline-primary' }}">Route Planning</a>
            <a href="{{ route('projects.production-v2.operation-masters.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'operation_masters' ? 'btn-primary' : 'btn-outline-primary' }}">Processes</a>
            <a href="{{ route('projects.production-v2.cut-batches.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'cut_batches' ? 'btn-primary' : 'btn-outline-primary' }}">Cut</a>
            <a href="{{ route('projects.production-v2.fitups.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'fitups' ? 'btn-primary' : 'btn-outline-primary' }}">Fit-up</a>
            <a href="{{ route('projects.production-v2.welding-events.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'welding' ? 'btn-primary' : 'btn-outline-primary' }}">Welding</a>
            <a href="{{ route('projects.production-v2.inspection-events.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'inspection' ? 'btn-primary' : 'btn-outline-primary' }}">Inspection</a>
            <a href="{{ route('projects.production-v2.qc-gates.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'qc_gates' ? 'btn-primary' : 'btn-outline-primary' }}">QC Gates</a>
            <a href="{{ route('projects.production-v2.operation-events.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'operations' ? 'btn-primary' : 'btn-outline-primary' }}">Operations</a>
            <a href="{{ route('projects.production-v2.rework-events.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'rework' ? 'btn-primary' : 'btn-outline-primary' }}">Rework</a>
            <a href="{{ route('projects.production-v2.trial-assemblies.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'trial' ? 'btn-primary' : 'btn-outline-primary' }}">Trial</a>
            <a href="{{ route('projects.production-v2.wip.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'wip' ? 'btn-primary' : 'btn-outline-primary' }}">WIP</a>
        </div>
    </div>
</div>
