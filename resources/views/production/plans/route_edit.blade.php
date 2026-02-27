@extends('layouts.erp')

@section('title', 'Edit Route')

@section('content')
@php
    $planId = (int) ($plan->id ?? 0);
    $itemId = (int) ($item->id ?? 0);
@endphp

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-sliders"></i> Edit Route</h2>
            <div class="text-muted small">
                Plan: <strong>{{ $plan->plan_number }}</strong> |
                Item:
                <strong>
                    {{ ($item->item_type ?? '') === 'assembly' ? ($item->assembly_mark ?? ('#'.$itemId)) : ($item->item_code ?? ('#'.$itemId)) }}
                </strong>
                @if(($item->item_type ?? '') === 'part' && !empty($item->assembly_mark))
                    <span class="ms-1">(Asm: {{ $item->assembly_mark }})</span>
                @endif
            </div>
        </div>
        <a href="{{ url('/projects/'.$projectId.'/production-plans/'.$planId) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info">
        <div class="fw-semibold">Routing tips</div>
        <ul class="mb-0">
            <li><strong>Enabled</strong> controls whether the activity is in the route.</li>
            <li><strong>Sequence</strong> controls order (lower first).</li>
            <li><strong>Contractor + Rate</strong> is used for subcontractor billing.</li>
            <li><strong>Worker</strong> (optional) can be assigned for internal productivity later.</li>
        </ul>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ url('/projects/'.$projectId.'/production-plans/'.$planId.'/items/'.$itemId.'/route') }}">
                @csrf
                @method('PUT')

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width:70px;">Enabled</th>
                                <th>Activity</th>
                                <th style="width:100px;">Seq</th>
                                <th style="width:220px;">Contractor</th>
                                <th style="width:220px;">Worker</th>
                                @if(!empty($hasMachineId))
                                    <th style="width:220px;">Machine</th>
                                @endif
                                <th style="width:120px;" class="text-end">Rate</th>
                                <th style="width:160px;">Rate UOM</th>
                                <th style="width:140px;">Planned</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($routeRows as $idx => $r)
                                <tr>
                                    <td>
                                        <input type="hidden" name="rows[{{ $idx }}][id]" value="{{ $r->id }}">
                                        <input type="checkbox" class="form-check-input"
                                               name="rows[{{ $idx }}][is_enabled]" value="1"
                                               {{ old('rows.'.$idx.'.is_enabled', (bool)$r->is_enabled) ? 'checked' : '' }}>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $r->activity_name }}</div>
                                        <div class="small text-muted">
                                            <span class="me-2">Code: {{ $r->activity_code }}</span>
                                            @if(!empty($r->is_fitupp))
                                                <span class="badge text-bg-info">Fitup</span>
                                            @endif
                                            @if(!empty($r->requires_machine))
                                                <span class="badge text-bg-secondary">Machine</span>
                                            @endif
                                            @if(!empty($r->requires_qc))
                                                <span class="badge text-bg-warning">QC</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" min="0" class="form-control form-control-sm"
                                               name="rows[{{ $idx }}][sequence_no]"
                                               value="{{ old('rows.'.$idx.'.sequence_no', (int)$r->sequence_no) }}">
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="rows[{{ $idx }}][contractor_party_id]">
                                            <option value="">— None —</option>
                                            @foreach($contractors as $c)
                                                <option value="{{ $c->id }}"
                                                    {{ (string) old('rows.'.$idx.'.contractor_party_id', $r->contractor_party_id) === (string) $c->id ? 'selected' : '' }}>
                                                    {{ $c->name }} ({{ $c->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="rows[{{ $idx }}][worker_user_id]">
                                            <option value="">— None —</option>
                                            @foreach($workers as $w)
                                                <option value="{{ $w->id }}"
                                                    {{ (string) old('rows.'.$idx.'.worker_user_id', $r->worker_user_id) === (string) $w->id ? 'selected' : '' }}>
                                                    {{ $w->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    @if(!empty($hasMachineId))
                                        <td>
                                            <select class="form-select form-select-sm" name="rows[{{ $idx }}][machine_id]">
                                                <option value="">— None —</option>
                                                @foreach(($machines ?? []) as $m)
                                                    @php
                                                        $mLabel = trim(($m->code ?? '').' '.(($m->short_name ?? '') ?: ($m->name ?? '')));
                                                        if ($mLabel === '') { $mLabel = 'Machine#'.$m->id; }
                                                    @endphp
                                                    <option value="{{ $m->id }}"
                                                        {{ (string) old('rows.'.$idx.'.machine_id', $r->machine_id ?? null) === (string) $m->id ? 'selected' : '' }}>
                                                        {{ $mLabel }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    @endif
                                    <td class="text-end">
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end"
                                               name="rows[{{ $idx }}][rate]"
                                               value="{{ old('rows.'.$idx.'.rate', (float)$r->rate) }}">
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="rows[{{ $idx }}][rate_uom_id]">
                                            <option value="">— None —</option>
                                            @foreach($uoms as $u)
                                                <option value="{{ $u->id }}"
                                                    {{ (string) old('rows.'.$idx.'.rate_uom_id', $r->rate_uom_id) === (string) $u->id ? 'selected' : '' }}>
                                                    {{ $u->code }} — {{ $u->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="date" class="form-control form-control-sm"
                                               name="rows[{{ $idx }}][planned_date]"
                                               value="{{ old('rows.'.$idx.'.planned_date', $r->planned_date ? \Carbon\Carbon::parse($r->planned_date)->toDateString() : '') }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i> Save Route
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ url('/projects/'.$projectId.'/production-plans/'.$planId) }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
