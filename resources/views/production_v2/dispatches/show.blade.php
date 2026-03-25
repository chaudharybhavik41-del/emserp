@extends('layouts.erp')

@section('title', 'Production V2 Dispatch')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $dispatch->dispatch_number }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.dispatches.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        @if($dispatch->isFinalized() && auth()->user()?->can('client_ra.create'))
            <a href="{{ route('accounting.client-ra.create', ['client_id' => $dispatch->client_party_id, 'project_id' => $project->id, 'production_v2_dispatch_id' => $dispatch->id]) }}" class="btn btn-sm btn-primary">Create Client RA</a>
        @endif
        @if($dispatch->isDraft() && auth()->user()?->can('production.dispatch.update'))
            <form method="post" action="{{ route('projects.production-v2.dispatches.finalize', ['project' => $project->id, 'dispatch' => $dispatch->id]) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-success">Finalize</button>
            </form>
            <form method="post" action="{{ route('projects.production-v2.dispatches.cancel', ['project' => $project->id, 'dispatch' => $dispatch->id]) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
            </form>
        @endif
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'dispatches'])

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Dispatch Date</div><div class="h5 mb-0">{{ $dispatch->dispatch_date?->format('Y-m-d') ?: '-' }}</div></div></div></div>
        <div class="col-12 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Status</div><div class="h5 mb-0">{{ ucfirst($dispatch->status) }}</div></div></div></div>
        <div class="col-12 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Total Qty</div><div class="h5 mb-0">{{ number_format((float) $dispatch->total_qty, 3) }}</div></div></div></div>
        <div class="col-12 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Total Weight</div><div class="h5 mb-0">{{ number_format((float) $dispatch->total_weight_kg, 3) }} kg</div></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4"><div class="small text-body-secondary">Client</div><div>{{ $dispatch->client?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Vehicle</div><div>{{ $dispatch->vehicle_number ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">LR No</div><div>{{ $dispatch->lr_number ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Gate Pass Ref</div><div>{{ $dispatch->gate_pass_ref ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Finalized By</div><div>{{ $dispatch->finalizedBy?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-6"><div class="small text-body-secondary">Transporter</div><div>{{ $dispatch->transporter_name ?: '-' }}</div></div>
                <div class="col-12 col-md-6"><div class="small text-body-secondary">Remarks</div><div>{{ $dispatch->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Dispatch Lines</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Assembly</th>
                            <th>Girder / Segment</th>
                            <th>Client Billing Parts</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Weight</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($dispatch->lines as $line)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $line->assembly_code_snapshot ?: ($line->assembly?->assembly_code ?: '-') }}</div>
                                <div class="small text-body-secondary">{{ $line->assembly_name_snapshot ?: ($line->assembly?->assembly_name ?: '-') }}</div>
                            </td>
                            <td>{{ $line->girder_no_snapshot ?: '-' }} / {{ $line->segment_no_snapshot ?: '-' }}</td>
                            <td>{{ $line->client_dispatch_description_snapshot ?: '-' }}</td>
                            <td class="text-end">{{ number_format((float) $line->qty, 3) }}</td>
                            <td class="text-end">{{ number_format((float) $line->weight_kg, 3) }} kg</td>
                            <td>{{ $line->remarks ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No dispatch lines found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
