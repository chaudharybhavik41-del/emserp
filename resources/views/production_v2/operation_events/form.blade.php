@extends('layouts.erp')

@section('title', 'Create Production V2 Operation Event')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Record {{ $operation->name }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'operations'])

    @if(!$selectedDpr)
        <div class="alert alert-info">
            No linked DPR selected. <a href="{{ route('projects.production-v2.dprs.create', ['project' => $project->id, 'activity' => 'op:' . $operation->code]) }}" class="alert-link">Create {{ $operation->name }} DPR</a> if this operation should be logged inside the daily shell.
        </div>
    @else
        <div class="alert alert-success d-flex justify-content-between align-items-center gap-2">
            <div>
                Linked to DPR-{{ $selectedDpr->id }} for {{ $selectedDpr->dpr_date?->format('Y-m-d') ?: '-' }}.
            </div>
            <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $selectedDpr->id]) }}" class="btn btn-sm btn-outline-success">Open DPR</a>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-light border">
                        <div class="fw-semibold mb-1">{{ $operation->name }} Route Capture</div>
                        <div class="small text-body-secondary">
                            @if($operation->applies_to === 'part')
                                This operation is tracked at part or WIP level. Select the released part and optionally the exact WIP piece for stronger sequence traceability.
                            @else
                                This operation is tracked at assembly level. Select the released assembly that currently requires this route step.
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('projects.production-v2.operation-events.store', ['project' => $project->id]) }}" data-pwa-sync="critical">
                @csrf
                <input type="hidden" name="operation_master_id" value="{{ $operation->id }}">
                @if($selectedDpr)
                    <input type="hidden" name="dpr_id" value="{{ $selectedDpr->id }}">
                @endif

                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Operation Date</label>
                        <input type="date" name="operation_date" class="form-control" value="{{ old('operation_date', $selectedDpr?->dpr_date?->format('Y-m-d') ?: now()->toDateString()) }}" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Shift</label>
                        <input name="shift" class="form-control" value="{{ old('shift', $selectedDpr?->shift) }}">
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            @foreach(['A', 'B', 'C', 'General'] as $shift)
                                <button type="button" class="btn btn-sm btn-outline-secondary quick-shift" data-shift="{{ $shift }}">{{ $shift }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Qty</label>
                        <input type="number" step="0.001" min="0.001" name="qty" class="form-control" inputmode="decimal" value="{{ old('qty', 1) }}" required>
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            @foreach(['1', '2', '5', '10'] as $qtyPreset)
                                <button type="button" class="btn btn-sm btn-outline-secondary quick-qty" data-qty="{{ $qtyPreset }}">{{ $qtyPreset }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" data-erp-select data-hide-search="true">
                            @foreach(['draft', 'approved', 'hold'] as $status)
                                <option value="{{ $status }}" @selected(old('status', 'approved') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($operation->applies_to === 'part')
                    <div class="col-12 col-md-6">
                        <label class="form-label">Part</label>
                        <select name="part_definition_id" id="part_definition_id" class="form-select" data-erp-select data-placeholder="Select part" data-allow-clear="1">
                            <option value="">Select part</option>
                            @foreach($parts as $part)
                                <option value="{{ $part->id }}" @selected((string) old('part_definition_id', request('part_definition_id')) === (string) $part->id)>{{ $part->part_code }} - {{ $part->part_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">WIP Piece</label>
                        <select name="wip_item_id" class="form-select" data-erp-select data-placeholder="Select WIP piece" data-allow-clear="1">
                            <option value="">Select WIP piece</option>
                            @foreach($wipItems as $wip)
                                <option value="{{ $wip->id }}" @selected((string) old('wip_item_id') === (string) $wip->id)>{{ $wip->piece_no ?: ('WIP-' . $wip->id) }} | Qty {{ number_format((float) $wip->qty, 3) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Optional. Use when this operation is tracked against a specific available piece.</div>
                    </div>
                    @else
                    <div class="col-12">
                        <label class="form-label">Assembly</label>
                        <select name="assembly_id" class="form-select" data-erp-select data-placeholder="Select assembly" data-allow-clear="1">
                            <option value="">Select assembly</option>
                            @foreach($assemblies as $assembly)
                                <option value="{{ $assembly->id }}" @selected((string) old('assembly_id', request('assembly_id')) === (string) $assembly->id)>{{ $assembly->assembly_code }} - {{ $assembly->assembly_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="col-12 col-md-4">
                        <label class="form-label">Machine</label>
                        <select name="machine_id" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select machine</option>
                            @foreach($machines as $machine)
                                <option value="{{ $machine->id }}" @selected((string) old('machine_id', $selectedDpr?->machine_id) === (string) $machine->id)>{{ $machine->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Worker / Operator</label>
                        <select name="worker_user_id" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select user</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((string) old('worker_user_id', $selectedDpr?->worker_user_id) === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Contractor</label>
                        <select name="contractor_party_id" class="form-select" data-erp-select data-allow-clear="1">
                            <option value="">Select contractor</option>
                            @foreach($contractors as $contractor)
                                <option value="{{ $contractor->id }}" @selected((string) old('contractor_party_id', $selectedDpr?->contractor_party_id) === (string) $contractor->id)>{{ $contractor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Result</label>
                        <input name="result" class="form-control" value="{{ old('result') }}" placeholder="ok / hold / reoffer">
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            @foreach(['ok', 'hold', 'reoffer'] as $resultPreset)
                                <button type="button" class="btn btn-sm btn-outline-secondary quick-result" data-result="{{ $resultPreset }}">{{ strtoupper($resultPreset) }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Reference No</label>
                        <input name="reference_no" class="form-control" value="{{ old('reference_no') }}" placeholder="job card / report ref">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" rows="3" class="form-control">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                        <div class="position-sticky bottom-0 bg-body border-top mt-4 pt-3 d-flex gap-2 justify-content-end">
                            <a href="{{ route('projects.production-v2.operation-events.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Operation Event</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header">Recent {{ $operation->name }}</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Target</th>
                                    <th class="text-end">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($recentRows as $row)
                                <tr>
                                    <td>{{ $row->operation_date?->format('Y-m-d') ?: '-' }}</td>
                                    <td>{{ $row->partDefinition?->part_code ?: $row->assembly?->assembly_code ?: '-' }}</td>
                                    <td class="text-end">{{ number_format((float) $row->qty, 3) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">No recent rows for this operation.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const shiftInput = document.querySelector('input[name="shift"]');
    const qtyInput = document.querySelector('input[name="qty"]');
    const resultInput = document.querySelector('input[name="result"]');

    document.querySelectorAll('.quick-shift').forEach((button) => {
        button.addEventListener('click', () => {
            if (shiftInput) {
                shiftInput.value = button.dataset.shift || '';
            }
        });
    });

    document.querySelectorAll('.quick-qty').forEach((button) => {
        button.addEventListener('click', () => {
            if (qtyInput) {
                qtyInput.value = button.dataset.qty || '1';
            }
        });
    });

    document.querySelectorAll('.quick-result').forEach((button) => {
        button.addEventListener('click', () => {
            if (resultInput) {
                resultInput.value = button.dataset.result || '';
            }
        });
    });
});
</script>
@endpush
