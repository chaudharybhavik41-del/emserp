@extends('layouts.erp')

@section('title', 'Create Production V2 Dispatch')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Create Production V2 Dispatch</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.dispatches.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'dispatches'])

    <form method="post" action="{{ route('projects.production-v2.dispatches.store', ['project' => $project->id]) }}" class="d-grid gap-3" data-pwa-sync="critical">
        @csrf

        <div class="alert alert-info mb-0">
            Dispatch only lists assemblies that are route-complete, free from pending QC gates, and have no open rework. This keeps shipment aligned with actual V2 execution status.
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Dispatch Date</label>
                        <input type="date" name="dispatch_date" value="{{ old('dispatch_date', now()->toDateString()) }}" class="form-control @error('dispatch_date') is-invalid @enderror" required>
                        @error('dispatch_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Client</label>
                        <select name="client_party_id" class="form-select @error('client_party_id') is-invalid @enderror">
                            <option value="">Project Client</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" @selected((int) old('client_party_id', $defaultClientId) === (int) $client->id)>{{ $client->name }}</option>
                            @endforeach
                        </select>
                        @error('client_party_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Vehicle No</label>
                        <input type="text" name="vehicle_number" value="{{ old('vehicle_number') }}" class="form-control" placeholder="MH12AB1234">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">LR No</label>
                        <input type="text" name="lr_number" value="{{ old('lr_number') }}" class="form-control" placeholder="LR-001">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Gate Pass Ref</label>
                        <input type="text" name="gate_pass_ref" value="{{ old('gate_pass_ref') }}" class="form-control" placeholder="GP-001">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Transporter</label>
                        <input type="text" name="transporter_name" value="{{ old('transporter_name') }}" class="form-control" placeholder="Transporter name">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Remarks</label>
                        <input type="text" name="remarks" value="{{ old('remarks') }}" class="form-control" placeholder="Dispatch remarks">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Dispatch-Ready Assemblies</span>
                <span class="small text-body-secondary">{{ number_format($eligibleAssemblies->count()) }} ready</span>
            </div>
            <div class="card-body p-0">
                @if($eligibleAssemblies->isEmpty())
                    <div class="p-4 text-center text-muted">No assemblies are dispatch-ready yet. Complete route steps and close rework first.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Assembly</th>
                                    <th>Girder / Segment</th>
                                    <th>Client Billing Parts</th>
                                    <th class="text-end">Planned Qty</th>
                                    <th class="text-end">Already Dispatched</th>
                                    <th class="text-end">Remaining</th>
                                    <th style="width: 160px;">Dispatch Qty</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($eligibleAssemblies as $index => $row)
                                @php
                                    $assembly = $row['assembly'];
                                    $oldQty = old("lines.$index.qty");
                                @endphp
                                <tr>
                                    <td>
                                        <input type="hidden" name="lines[{{ $index }}][assembly_id]" value="{{ $assembly->id }}">
                                        <div class="fw-semibold">{{ $assembly->assembly_code }}</div>
                                        <div class="small text-body-secondary">{{ $assembly->assembly_name }}</div>
                                    </td>
                                    <td>{{ $assembly->girder_no ?: '-' }} / {{ $assembly->segment_no ?: '-' }}</td>
                                    <td>
                                        @php
                                            $clientDispatchParts = $assembly->requirements->where('is_client_dispatchable', true)->pluck('partDefinition.part_code')->filter()->unique()->values();
                                        @endphp
                                        @if($clientDispatchParts->isNotEmpty())
                                            <div class="small">{{ $clientDispatchParts->implode(', ') }}</div>
                                        @else
                                            <span class="text-muted small">No flagged parts</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format((float) $assembly->planned_qty, 3) }}</td>
                                    <td class="text-end">{{ number_format((float) $row['already_dispatched_qty'], 3) }}</td>
                                    <td class="text-end">{{ number_format((float) $row['remaining_qty'], 3) }}</td>
                                    <td>
                                        <input type="number" step="0.001" min="0" max="{{ $row['remaining_qty'] }}" name="lines[{{ $index }}][qty]" value="{{ $oldQty !== null ? $oldQty : $row['remaining_qty'] }}" class="form-control form-control-sm @error("lines.$index.qty") is-invalid @enderror" inputmode="decimal">
                                        @error("lines.$index.qty")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="text" name="lines[{{ $index }}][remarks]" value="{{ old("lines.$index.remarks") }}" class="form-control form-control-sm" placeholder="Optional line remark">
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @if($eligibleAssemblies->isNotEmpty())
            <div class="sticky-bottom pb-3" style="z-index: 10;">
                <div class="card border-0 shadow">
                    <div class="card-body d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
                        <div class="small text-body-secondary">Tick and go: default qty is the remaining dispatchable balance. Reduce only when sending a partial lot.</div>
                        <button type="submit" class="btn btn-primary">Create Dispatch</button>
                    </div>
                </div>
            </div>
        @endif
    </form>
@endsection
