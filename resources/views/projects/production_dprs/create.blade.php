
@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-journal-plus"></i> New DPR</h2>
            <div class="text-muted small">
                Plan: <span class="fw-semibold">{{ $plan->plan_number }}</span> |
                Activity: <span class="fw-semibold">{{ $activity->name }}</span> |
                Date: <span class="fw-semibold">{{ $dprDate }}</span>
            </div>
        </div>
        <a href="{{ route('projects.production-dprs.index', $project) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    @if($eligible->isEmpty())
        <div class="alert alert-warning">
            No eligible items found. Reasons can be: previous activity pending, QC pending, or already completed.
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold">Geofence</div>
                    <div class="text-muted small" id="geoStatusText">Tap “Capture Location” to record GPS.</div>
                </div>
                <button type="button" class="btn btn-outline-primary" id="btnGeo">
                    <i class="bi bi-geo-alt"></i> Capture Location
                </button>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('projects.production-dprs.store', $project) }}">
        @csrf

        <input type="hidden" name="production_plan_id" value="{{ $plan->id }}">
        <input type="hidden" name="production_activity_id" value="{{ $activity->id }}">
        <input type="hidden" name="dpr_date" value="{{ $dprDate }}">
        <input type="hidden" name="shift" value="{{ $shift }}">

        <input type="hidden" name="geo_latitude" id="geo_latitude">
        <input type="hidden" name="geo_longitude" id="geo_longitude">
        <input type="hidden" name="geo_accuracy_m" id="geo_accuracy_m">
        <input type="hidden" name="geo_status" id="geo_status" value="captured">

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Contractor (optional)</label>
                        <select name="contractor_party_id" class="form-select">
                            <option value="">—</option>
                            @foreach($contractors as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Worker (optional)</label>
                        <select name="worker_user_id" class="form-select">
                            <option value="">—</option>
                            @foreach($workers as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Machine ID @if($activity->requires_machine)<span class="text-danger">*</span>@endif</label>
                        <input type="number" name="machine_id" class="form-control" placeholder="Enter machine id"
                               @if($activity->requires_machine) required @endif>
                        <div class="form-text">Temporary input. Later we’ll make a proper machine dropdown.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width:60px;">Tick</th>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Assembly</th>
                            <th style="width:140px;">Qty</th>
                            <th style="width:300px;">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($eligible as $i => $pia)
                            <tr>
                                <td>
                                    <input type="hidden" name="rows[{{ $i }}][id]" value="{{ $pia->id }}">
                                    <input class="form-check-input" type="checkbox" value="1" name="rows[{{ $i }}][checked]">
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $pia->planItem?->item_code }}</div>
                                    <div class="text-muted small">{{ $pia->planItem?->description }}</div>
                                    @php
                                        $pi = $pia->planItem;
                                    @endphp
                                    @if($pi && ($pi->unit_cut_length_m !== null || $pi->unit_weld_length_m !== null || $pi->unit_area_m2 !== null))
                                        <div class="text-muted small">
                                            @if($pi->unit_cut_length_m !== null)
                                                Cut: {{ $pi->unit_cut_length_m }} m/pc
                                            @endif
                                            @if($pi->unit_weld_length_m !== null)
                                                @if($pi->unit_cut_length_m !== null) | @endif
                                                Weld: {{ $pi->unit_weld_length_m }} m/pc
                                            @endif
                                            @if($pi->unit_area_m2 !== null)
                                                @if($pi->unit_cut_length_m !== null || $pi->unit_weld_length_m !== null) | @endif
                                                Area: {{ $pi->unit_area_m2 }} m²/pc
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-capitalize">{{ $pia->planItem?->item_type }}</td>
                                <td>{{ $pia->planItem?->assembly_mark ?? '—' }}</td>
                                <td>
                                    <input type="number" step="0.001" class="form-control form-control-sm"
                                           name="rows[{{ $i }}][qty]" placeholder="auto">
                                    <div class="text-muted small">Leave blank for auto.</div>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][remarks]" placeholder="optional">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button class="btn btn-primary">
                    <i class="bi bi-save"></i> Save DPR (Draft)
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('btnGeo')?.addEventListener('click', function() {
    const statusEl = document.getElementById('geoStatusText');
    if (!navigator.geolocation) {
        statusEl.textContent = 'Geolocation not supported in this browser.';
        return;
    }
    statusEl.textContent = 'Capturing GPS… please allow location permission.';
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            document.getElementById('geo_latitude').value = pos.coords.latitude;
            document.getElementById('geo_longitude').value = pos.coords.longitude;
            document.getElementById('geo_accuracy_m').value = pos.coords.accuracy;
            statusEl.textContent = `Captured: ${pos.coords.latitude.toFixed(6)}, ${pos.coords.longitude.toFixed(6)} (±${Math.round(pos.coords.accuracy)}m)`;
        },
        (err) => {
            statusEl.textContent = 'Could not capture GPS: ' + err.message;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
});
</script>
@endsection
