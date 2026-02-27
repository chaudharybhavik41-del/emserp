@extends('layouts.erp')

@section('title', 'CRM - View Breakup Template')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h4 mb-0">Breakup Template</h1>
            <div class="text-muted small">
                <b>{{ $template->name }}</b>
                <span class="ms-2 badge bg-light text-dark border">{{ $template->code }}</span>
                @if($template->is_default)
                    <span class="ms-2 badge bg-success">Default</span>
                @endif
                @if($template->is_active)
                    <span class="ms-2 badge bg-primary">Active</span>
                @else
                    <span class="ms-2 badge bg-secondary">Inactive</span>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('crm.quotation-breakup-templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">
                Edit
            </a>
            <a href="{{ route('crm.quotation-breakup-templates.index') }}" class="btn btn-sm btn-outline-secondary">
                Back
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 small">
                <div class="col-md-3">
                    <div class="text-muted">Code</div>
                    <div class="fw-semibold">{{ $template->code }}</div>
                </div>
                <div class="col-md-5">
                    <div class="text-muted">Name</div>
                    <div class="fw-semibold">{{ $template->name }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted">Sort Order</div>
                    <div class="fw-semibold">{{ $template->sort_order }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted">Created</div>
                    <div class="fw-semibold">{{ optional($template->created_at)->format('d-m-Y') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info small">
        <b>Basis meaning:</b>
        <span class="ms-2"><b>Per Unit</b> = Rs per UOM</span>
        <span class="ms-2"><b>Lumpsum</b> = total for the line item</span>
        <span class="ms-2"><b>%</b> = percentage of base direct cost</span>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="fw-semibold">Template Components</div>
            <div class="small text-muted">{{ is_array($lines ?? null) ? count($lines) : 0 }} line(s)</div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Component</th>
                    <th style="width: 140px;">Basis</th>
                    <th style="width: 160px;" class="text-end">Rate</th>
                </tr>
                </thead>
                <tbody>
                @forelse(($lines ?? []) as $i => $line)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>{{ $line['name'] ?? '' }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ $basisLabels[$line['basis'] ?? 'per_unit'] ?? ($line['basis'] ?? 'per_unit') }}
                            </span>
                        </td>
                        <td class="text-end">{{ $line['rate'] ?? '0' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-3 text-muted">No components found in this template.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <details class="mt-3">
        <summary class="small text-muted">Show raw stored format</summary>
        <div class="small text-muted mt-1">
            System stores the template as one line per component: <code>Name|basis|rate</code>
            (basis = <code>per_unit</code> / <code>lumpsum</code> / <code>percent</code>).
        </div>
        <pre class="small mb-0">{{ $template->content }}</pre>
    </details>
@endsection
