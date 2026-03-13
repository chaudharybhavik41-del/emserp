@php
    $active = $active ?? '';
@endphp

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('production-v2.project.design', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'workbench' ? 'btn-dark' : 'btn-outline-dark' }}">Design Workbench</a>
            <a href="{{ route('projects.production-v2.parts.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'parts' ? 'btn-primary' : 'btn-outline-primary' }}">Parts</a>
            <a href="{{ route('projects.production-v2.assemblies.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'assemblies' ? 'btn-primary' : 'btn-outline-primary' }}">Assemblies</a>
            <a href="{{ route('projects.production-v2.cutting-plans.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'cutting_plans' ? 'btn-primary' : 'btn-outline-primary' }}">Cutting Plans</a>
            <a href="{{ route('projects.production-v2.material-requirements.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'material_requirements' ? 'btn-primary' : 'btn-outline-primary' }}">Material Requirement</a>
            <a href="{{ route('projects.production-v2.design-releases.index', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'design_releases' ? 'btn-primary' : 'btn-outline-primary' }}">Releases</a>
            <a href="{{ route('projects.production-v2.import-bom.form', ['project' => $project->id]) }}" class="btn btn-sm {{ $active === 'import_bom' ? 'btn-primary' : 'btn-outline-primary' }}">Import BOM</a>
        </div>
    </div>
</div>
