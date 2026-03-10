@extends('layouts.erp')

@section('title', 'DPR')

@php
    $doneCount = (int) collect($lines ?? [])->where('is_completed', 1)->count();
    $totalCount = (int) collect($lines ?? [])->count();
    $pendingCount = max($totalCount - $doneCount, 0);
@endphp

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-clipboard2-check"></i> DPR #{{ $dpr->id }}</h2>
            <div class="small text-muted">
                @if(!empty($project))
                    Project: <strong>{{ $project->code }}</strong> - {{ $project->name }} |
                @endif
                Date: {{ $dpr->dpr_date }} | Plan: {{ $dpr->plan_number }} | Activity: {{ $dpr->activity_name }} ({{ $dpr->activity_code }})
            </div>
            <div class="small text-muted">
                @if(!empty($dpr->cutting_plan_name))
                    Cutting Plan: {{ $dpr->cutting_plan_name }} |
                @endif
                @if(!empty($dpr->mother_plate_number) || !empty($dpr->mother_heat_number))
                    Mother Plate: {{ $dpr->mother_plate_number ?? '-' }} | Heat: {{ $dpr->mother_heat_number ?? '-' }} |
                @endif
                Status: <strong>{{ strtoupper($dpr->status) }}</strong>
            </div>
        </div>
        <div class="d-flex gap-2">
            @if(\Illuminate\Support\Facades\Route::has('production.workbench.project'))
                <a class="btn btn-outline-secondary" href="{{ route('production.workbench.project', ['project' => $projectId]) }}">
                    <i class="bi bi-grid"></i> Workbench
                </a>
            @endif
            <a class="btn btn-outline-secondary" href="{{ url('/projects/'.$projectId.'/production-dprs') }}">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            @can('production.dpr.update')
                @if(in_array($dpr->status, ['draft', 'submitted'], true))
                    <form method="POST" action="{{ url('/projects/'.$projectId.'/production-dprs/'.$dpr->id.'/cancel') }}" onsubmit="return confirm('Cancel this DPR?');">
                        @csrf
                        <button class="btn btn-outline-danger">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    </form>
                @endif
                @if(in_array($dpr->status, ['submitted', 'cancelled'], true))
                    <form method="POST" action="{{ url('/projects/'.$projectId.'/production-dprs/'.$dpr->id.'/reopen') }}" onsubmit="return confirm('Reopen this DPR as draft?');">
                        @csrf
                        <button class="btn btn-outline-warning">
                            <i class="bi bi-arrow-clockwise"></i> Reopen
                        </button>
                    </form>
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

    @if(($lines ?? collect())->isEmpty())
        <div class="alert alert-warning">
            <div class="fw-semibold">No eligible items were generated for this DPR.</div>
            <div class="small text-muted">Complete previous activities/QC or check route setup, then create DPR again.</div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="small text-uppercase text-muted">Total Items</div>
                <div class="h5 mb-0">{{ number_format($totalCount) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="small text-uppercase text-muted">Done</div>
                <div class="h5 mb-0">{{ number_format($doneCount) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="small text-uppercase text-muted">Pending</div>
                <div class="h5 mb-0">{{ number_format($pendingCount) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="small text-uppercase text-muted">DPR Status</div>
                <div class="h6 mb-0 text-uppercase">{{ $dpr->status }}</div>
            </div></div>
        </div>
    </div>

    @if($dpr->status === 'draft')
        <form method="POST" action="{{ url('/projects/'.$projectId.'/production-dprs/'.$dpr->id.'/submit') }}">
            @csrf

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">Location Capture</div>
                            <div class="text-muted small" id="geoStatusText">
                                Tap "Capture Location" before submitting.
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
                                If outside geofence, provide reason to allow submit.
                            </div>
                            <textarea name="geo_override_reason"
                                      class="form-control form-control-sm mt-2"
                                      rows="2"
                                      placeholder="Override reason (required only if outside geofence)">{{ old('geo_override_reason') }}</textarea>
                        </div>
                    @endcan
                </div>
            </div>
    @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Shopfloor Entry</h6>
                    @if($dpr->status === 'draft' && !($lines ?? collect())->isEmpty())
                        <button class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Submit DPR</button>
                    @endif
                </div>

                @forelse($lines as $idx => $l)
                    @php
                        $itemLabel = ($l->item_type === 'assembly')
                            ? ($l->assembly_mark ?: ('#'.$l->production_plan_item_id))
                            : ($l->item_code ?: ('#'.$l->production_plan_item_id));

                        $uomId = (int) old('lines.'.$idx.'.qty_uom_id', (int) (($l->item_uom_id ?? null) ?: 0));
                        if ($uomId <= 0) {
                            $uomId = (int) (optional($uoms->firstWhere('code','Nos'))->id ?: optional($uoms->firstWhere('code','PCS'))->id);
                        }
                        $uomCode = ($uomId > 0 && isset($uoms[$uomId])) ? ($uoms[$uomId]->code ?? 'Nos') : 'Nos';
                    @endphp

                    <div class="border rounded-3 p-3 mb-3">
                        <input type="hidden" name="lines[{{ $idx }}][id]" value="{{ $l->id }}">

                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <div class="fw-semibold">{{ $itemLabel }}</div>
                                <div class="small text-muted">{{ $l->item_description }}</div>
                                @if($l->item_type === 'assembly' && $l->assembly_type)
                                    <div class="small text-muted">{{ $l->assembly_type }}</div>
                                @endif
                            </div>
                            <div class="form-check m-0">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="line_done_{{ $idx }}"
                                       name="lines[{{ $idx }}][is_completed]"
                                       value="1"
                                       {{ (bool) old('lines.'.$idx.'.is_completed', (bool)$l->is_completed) ? 'checked' : '' }}
                                       {{ $dpr->status !== 'draft' ? 'disabled' : '' }}>
                                <label class="form-check-label small" for="line_done_{{ $idx }}">Done</label>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6 col-md-3">
                                <label class="form-label form-label-sm mb-1">Qty</label>
                                <input type="number" step="0.001" min="0"
                                       class="form-control form-control-sm"
                                       name="lines[{{ $idx }}][qty]"
                                       value="{{ old('lines.'.$idx.'.qty', (float)$l->qty) }}"
                                       {{ $dpr->status !== 'draft' ? 'readonly' : '' }}>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label form-label-sm mb-1">UOM</label>
                                <input type="hidden" name="lines[{{ $idx }}][qty_uom_id]" value="{{ $uomId }}">
                                <input type="text" class="form-control form-control-sm" value="{{ $uomCode }}" readonly>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label form-label-sm mb-1">Minutes</label>
                                <input type="number" step="0.1" min="0"
                                       class="form-control form-control-sm"
                                       name="lines[{{ $idx }}][minutes_spent]"
                                       value="{{ old('lines.'.$idx.'.minutes_spent', $l->minutes_spent) }}"
                                       {{ $dpr->status !== 'draft' ? 'readonly' : '' }}>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label form-label-sm mb-1">Remarks</label>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       name="lines[{{ $idx }}][remarks]"
                                       value="{{ old('lines.'.$idx.'.remarks', $l->remarks) }}"
                                       {{ $dpr->status !== 'draft' ? 'readonly' : '' }}>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">No eligible items found for this activity.</div>
                @endforelse

                @if($dpr->status === 'draft' && !($lines ?? collect())->isEmpty())
                    <div class="d-grid d-md-flex justify-content-md-end">
                        <button class="btn btn-primary"><i class="bi bi-send"></i> Submit DPR</button>
                    </div>
                @endif
            </div>
        </div>
    @if($dpr->status === 'draft')
        </form>
    @endif

    @if($dpr->status === 'submitted')
        @can('production.dpr.approve')
            <form id="approveForm" method="POST" action="{{ url('/projects/'.$projectId.'/production-dprs/'.$dpr->id.'/approve') }}" class="mt-3">
                @csrf
                <div class="d-grid d-md-flex justify-content-md-end">
                    <button class="btn btn-success" type="submit">
                        <i class="bi bi-check2-circle"></i> Approve DPR
                    </button>
                </div>
            </form>
        @endcan
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
        setStatus('Capturing location...');
        navigator.geolocation.getCurrentPosition(function(pos){
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = pos.coords.accuracy;
            latEl.value = lat;
            lngEl.value = lng;
            accEl.value = acc;
            stEl.value  = 'captured';
            setStatus(`Captured: ${lat.toFixed(6)}, ${lng.toFixed(6)} (+/-${Math.round(acc)}m)`);
        }, function(err){
            setStatus('Unable to capture location: ' + (err && err.message ? err.message : 'unknown error'));
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    });
})();
</script>
@endpush

@endsection
