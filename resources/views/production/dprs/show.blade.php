@extends('layouts.erp')

@section('title', 'DPR')

@section('content')
@php
    $taskIndexRoute = \Illuminate\Support\Facades\Route::has('tasks.index') ? 'tasks.index' : null;
    $taskCreateRoute = \Illuminate\Support\Facades\Route::has('tasks.create') ? 'tasks.create' : null;
    $isScoped = !empty($projectId);
    $listUrl = $isScoped ? url('/projects/'.$projectId.'/production-dprs') : url('/production/production-dprs');
    $submitUrl = $isScoped
        ? url('/projects/'.$projectId.'/production-dprs/'.$dpr->id.'/submit')
        : url('/production/production-dprs/'.$dpr->id.'/submit');
    $approveUrl = $isScoped
        ? url('/projects/'.$projectId.'/production-dprs/'.$dpr->id.'/approve')
        : url('/production/production-dprs/'.$dpr->id.'/approve');
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-clipboard2-check"></i> DPR #{{ $dpr->id }}</h2>
            <div class="text-muted small">
                Date: {{ $dpr->dpr_date }} |
                Plan: {{ $dpr->plan_number }} |
                Activity: {{ $dpr->activity_name }} ({{ $dpr->activity_code }}) |
                @if(!empty($dpr->cutting_plan_name))
                    Cutting Plan: {{ $dpr->cutting_plan_name }} |
                @endif
                @if(!empty($dpr->mother_plate_number) || !empty($dpr->mother_heat_number))
                    Mother Plate: {{ $dpr->mother_plate_number ?? '-' }} |
                    Heat: {{ $dpr->mother_heat_number ?? '-' }} |
                @endif
                Status: <strong>{{ strtoupper($dpr->status) }}</strong>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ $listUrl }}">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            @can('tasks.view')
                @if($taskIndexRoute && $isScoped)
                    <a class="btn btn-outline-secondary" href="{{ route($taskIndexRoute, ['project' => $projectId, 'q' => 'DPR #'.$dpr->id]) }}">
                        <i class="bi bi-list-task"></i> Related Tasks
                    </a>
                @endif
            @endcan
            @can('tasks.create')
                @if($taskCreateRoute && $isScoped)
                    <a class="btn btn-outline-primary" href="{{ route($taskCreateRoute, ['project' => $projectId, 'title' => 'DPR #'.$dpr->id.' follow-up', 'description' => 'Linked from DPR #'.$dpr->id.' (Plan '.$dpr->plan_number.', Activity '.$dpr->activity_name.')']) }}">
                        <i class="bi bi-plus-circle"></i> Add Task
                    </a>
                @endif
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold">Please fix the following:</div>
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(($lines ?? collect())->isEmpty())
        <div class="alert alert-warning">
            <div class="fw-semibold">No items were generated for this DPR.</div>
            <div class="small text-muted">
                This usually means there are <strong>no eligible plan items</strong> for the selected activity.
                Common reasons: previous activity is still pending, QC is pending/failed, or routing is not enabled.
                Go back and create DPR for the correct activity (or complete QC) and try again.
            </div>
        </div>
    @endif

    <form method="POST" action="{{ $submitUrl }}">
        @csrf

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Geofence</div>
                        <div class="text-muted small" id="geoStatusText">
                            Tap “Capture Location” to record GPS before submitting.
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnGeo">
                        <i class="bi bi-geo-alt"></i> Capture Location
                    </button>
                </div>

                <input type="hidden" name="geo_latitude" id="geo_latitude" value="{{ old('geo_latitude') }}">
                <input type="hidden" name="geo_longitude" id="geo_longitude" value="{{ old('geo_longitude') }}">
                <input type="hidden" name="geo_accuracy_m" id="geo_accuracy_m" value="{{ old('geo_accuracy_m') }}">
                <input type="hidden" name="geo_status" id="geo_status" value="{{ old('geo_status','captured') }}">

                @can('production.geofence.override')
                    <div class="mt-3 border-top pt-3">
                        <div class="fw-semibold text-warning"><i class="bi bi-shield-exclamation"></i> Geofence Override</div>
                        <div class="text-muted small">
                            If you are outside the geofence, you can submit only with an override reason.
                        </div>
                        <textarea name="geo_override_reason"
                                  class="form-control form-control-sm mt-2"
                                  rows="2"
                                  placeholder="Override reason (required only if outside geofence)">{{ old('geo_override_reason') }}</textarea>
                    </div>
                @endcan
            </div>
        </div>


        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Tick & Go Items</h6>
                    @if($dpr->status === 'draft' && !($lines ?? collect())->isEmpty())
                        <button class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Submit DPR</button>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width:60px;">Done</th>
                                <th>Item</th>
                                <th>Description</th>
                                <th class="text-end" style="width:90px;">Planned</th>
                                <th class="text-end" style="width:90px;">Done</th>
                                <th class="text-end" style="width:100px;">Remaining</th>
                                <th class="text-end" style="width:140px;">Qty (This DPR)</th>
                                <th style="width:150px;">UOM</th>
                                <th style="width:120px;">Minutes</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $idx => $l)
                                @php
                                    $itemLabel = ($l->item_type === 'assembly')
                                        ? ($l->assembly_mark ?: ('#'.$l->production_plan_item_id))
                                        : ($l->item_code ?: ('#'.$l->production_plan_item_id));
                                @endphp
                                <tr>
                                    <td>
                                        <input type="hidden" name="lines[{{ $idx }}][id]" value="{{ $l->id }}">
                                        <input type="checkbox" class="form-check-input"
                                               name="lines[{{ $idx }}][is_completed]" value="1"
                                               {{ (bool) old('lines.'.$idx.'.is_completed', (bool)$l->is_completed) ? 'checked' : '' }}
                                               {{ $dpr->status !== 'draft' ? 'disabled' : '' }}>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $itemLabel }}</div>
                                        @if($l->item_type === 'assembly' && $l->assembly_type)
                                            <div class="small text-muted">{{ $l->assembly_type }}</div>
                                        @endif
                                    </td>
                                    <td class="small">{{ $l->item_description }}</td>

                                    @php
                                        $plannedQty = (float) ($l->planned_qty ?? 0);
                                        $doneQty = (float) ($l->qty_done_before ?? 0);
                                        $remainingQty = (float) ($l->qty_remaining_before ?? max($plannedQty - $doneQty, 0));
                                        $qtyErrKey = 'lines.'.$idx.'.qty';
                                    @endphp

                                    <td class="text-end">{{ $plannedQty }}</td>
                                    <td class="text-end text-muted">{{ $doneQty }}</td>
                                    <td class="text-end">
                                        @if($remainingQty <= 0)
                                            <span class="badge text-bg-secondary">0</span>
                                        @else
                                            <span class="badge text-bg-info">{{ $remainingQty }}</span>
                                        @endif
                                    </td>

                                    <td class="text-end">
                                        <input type="number" step="0.001" min="0" max="{{ $remainingQty }}"
                                               class="form-control form-control-sm text-end {{ $errors->has($qtyErrKey) ? 'is-invalid' : '' }}"
                                               name="lines[{{ $idx }}][qty]"
                                               value="{{ old('lines.'.$idx.'.qty', (float)$l->qty) }}"
                                               {{ $dpr->status !== 'draft' ? 'readonly' : '' }}>
                                        @if($errors->has($qtyErrKey))
                                            <div class="invalid-feedback">{{ $errors->first($qtyErrKey) }}</div>
                                        @endif
                                        @if($dpr->status === 'draft')
                                            <div class="text-muted small">Max: {{ $remainingQty }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $uomId = (int) old('lines.'.$idx.'.qty_uom_id', (int) (($l->item_uom_id ?? null) ?: 0));
                                            if ($uomId <= 0) {
                                                $uomId = (int) (optional($uoms->firstWhere('code','Nos'))->id ?: optional($uoms->firstWhere('code','PCS'))->id);
                                            }
                                            $uomCode = ($uomId > 0 && isset($uoms[$uomId])) ? ($uoms[$uomId]->code ?? 'Nos') : 'Nos';
                                        @endphp

                                        <input type="hidden" name="lines[{{ $idx }}][qty_uom_id]" value="{{ $uomId }}">
                                        <span class="badge text-bg-light">{{ $uomCode }}</span>
                                    </td>
                                    <td>
                                        <input type="number" step="0.1" min="0"
                                               class="form-control form-control-sm"
                                               name="lines[{{ $idx }}][minutes_spent]"
                                               value="{{ old('lines.'.$idx.'.minutes_spent', $l->minutes_spent) }}"
                                               {{ $dpr->status !== 'draft' ? 'readonly' : '' }}>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm"
                                               name="lines[{{ $idx }}][remarks]"
                                               value="{{ old('lines.'.$idx.'.remarks', $l->remarks) }}"
                                               {{ $dpr->status !== 'draft' ? 'readonly' : '' }}>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        No eligible items found for this activity.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($dpr->status === 'submitted')
                    @can('production.dpr.approve')
                        <div class="mt-2">
                            <button form="approveForm" class="btn btn-success btn-sm" type="submit">
                                <i class="bi bi-check2-circle"></i> Approve DPR
                            </button>
                        </div>
                    @endcan
                @endif
            </div>
        </div>
    </form>

    @if($dpr->status === 'submitted')
        <form id="approveForm" method="POST" action="{{ $approveUrl }}">
            @csrf
        </form>
    @endif
</div>

@push('scripts')
<script>
(function(){
    const btn = document.getElementById('btnGeo');
    const statusEl = document.getElementById('geoStatusText');
    const latEl = document.getElementById('geo_latitude');
    const lngEl = document.getElementById('geo_longitude');
    const accEl = document.getElementById('geo_accuracy_m');
    const stEl  = document.getElementById('geo_status');

    function setStatus(msg){ if(statusEl) statusEl.textContent = msg; }

    if(!btn) return;

    btn.addEventListener('click', function(){
        if(!navigator.geolocation){
            setStatus('Geolocation not supported on this device/browser.');
            return;
        }
        setStatus('Capturing location…');
        navigator.geolocation.getCurrentPosition(function(pos){
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = pos.coords.accuracy;
            latEl.value = lat;
            lngEl.value = lng;
            accEl.value = acc;
            stEl.value  = 'captured';
            setStatus(`Captured: ${lat.toFixed(6)}, ${lng.toFixed(6)} (±${Math.round(acc)}m)`);
        }, function(err){
            setStatus('Unable to capture location: ' + (err && err.message ? err.message : 'unknown error'));
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    });
})();
</script>
@endpush

@endsection



