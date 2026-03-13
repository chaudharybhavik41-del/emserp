@extends('layouts.erp')

@section('title', 'Production V2 Route Templates')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Route Templates</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('production-v2.project', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back To Production Module</a>
        @can('production.plan.create')
        <a href="{{ route('projects.production-v2.route-templates.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">Create Route Template</a>
        @endcan
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'route_templates'])
    @include('production_v2.partials.mobile_list_styles')

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Total Templates</div>
                    <div class="display-6 mb-0">{{ number_format($summary['total']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-primary-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-primary mb-1">Part Routes</div>
                    <div class="display-6 mb-0">{{ number_format($summary['part']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-success-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-success mb-1">Assembly Routes</div>
                    <div class="display-6 mb-0">{{ number_format($summary['assembly']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-dark-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Active / Approved</div>
                    <div class="display-6 mb-0">{{ number_format($summary['active']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Applies To</th>
                            <th>Status</th>
                            <th class="text-end">Steps</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($templates as $template)
                        <tr>
                            <td>{{ $template->template_code }}</td>
                            <td>
                                <div>{{ $template->template_name }}</div>
                                <div class="small text-body-secondary">{{ $template->remarks ?: 'No remarks' }}</div>
                            </td>
                            <td><span class="badge {{ $template->applies_to === 'part' ? 'text-bg-primary' : 'text-bg-success' }}">{{ ucfirst($template->applies_to) }}</span></td>
                            <td><span class="badge text-bg-light border">{{ ucfirst($template->status) }}</span></td>
                            <td class="text-end">{{ $template->steps_count }}</td>
                            <td class="text-end"><a href="{{ route('projects.production-v2.route-templates.show', ['project' => $project->id, 'routeTemplate' => $template->id]) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No route templates created yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pv2-mobile-list p-3">
                @forelse($templates as $template)
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">{{ $template->template_code }}</div>
                                <div class="small text-body-secondary">{{ $template->template_name }}</div>
                            </div>
                            <span class="badge {{ $template->applies_to === 'part' ? 'text-bg-primary' : 'text-bg-success' }}">{{ ucfirst($template->applies_to) }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Status</span>
                                <span>{{ ucfirst($template->status) }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Steps</span>
                                <span>{{ $template->steps_count }}</span>
                            </div>
                        </div>
                        <div class="small text-body-secondary mt-3">{{ $template->remarks ?: 'No remarks' }}</div>
                        <div class="mt-3">
                            <a href="{{ route('projects.production-v2.route-templates.show', ['project' => $project->id, 'routeTemplate' => $template->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No route templates created yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $templates->links() }}
    </div>
@endsection
