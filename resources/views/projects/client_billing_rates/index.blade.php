@extends('layouts.erp')

@section('title', 'Project Client Billing Rates')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Client Billing Rates</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-outline-secondary">Project</a>
        <a href="{{ route('projects.client-billing-rates.create', $project) }}" class="btn btn-sm btn-primary">Add Rate</a>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Scope</th>
                            <th>Source Key</th>
                            <th>Description</th>
                            <th class="text-end">Rate</th>
                            <th>Revenue A/c</th>
                            <th>Effective</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->line_type_label }}</td>
                            <td>{{ $row->source_key ?: 'Default' }}</td>
                            <td>
                                {{ $row->description ?: '-' }}
                                @if($row->sac_hsn_code)
                                    <div class="small text-body-secondary">HSN/SAC: {{ $row->sac_hsn_code }}</div>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $row->rate, 2) }} {{ $row->uom?->code ?: '' }}</td>
                            <td>{{ $row->revenueAccount?->name ?: '-' }}</td>
                            <td>
                                {{ $row->effective_from?->format('d-m-Y') ?: 'Any' }}
                                to
                                {{ $row->effective_to?->format('d-m-Y') ?: 'Open' }}
                            </td>
                            <td>{{ $row->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.client-billing-rates.edit', [$project, $row]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No client billing rates configured yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} rates
                @else
                    Showing 0 rates
                @endif
            </small>
            {{ $rows->links() }}
        </div>
    </div>
@endsection
