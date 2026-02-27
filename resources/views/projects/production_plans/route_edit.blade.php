@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-diagram-3"></i> Edit Route</h2>
            <div class="text-muted small">
                Plan: <span class="fw-semibold">{{ $plan->plan_number }}</span> |
                Item: <span class="fw-semibold">{{ $item->item_code }}</span> |
                Type: {{ ucfirst($item->item_type) }}
            </div>
        </div>
        <a href="{{ route('projects.production-plans.show', [$project, $plan]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    @if($plan->status === 'approved')
        <div class="alert alert-warning">
            This plan is approved. Route is locked.
        </div>
    @endif

    <div class="card">
        <div class="card-body table-responsive">
            <form method="POST" action="{{ route('projects.production-plans.route.update', [$project, $plan, $item]) }}">
                @csrf
                @method('PUT')

                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width:80px;">Seq</th>
                            <th>Activity</th>
                            <th style="width:120px;">Enable</th>
                            <th>Contractor</th>
                            <th style="width:120px;">Rate</th>
                            <th style="width:140px;">Rate UOM</th>
                            <th style="width:160px;">Planned Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($item->activities as $i => $row)
                            <tr>
                                <td>
                                    <input type="hidden" name="rows[{{ $i }}][id]" value="{{ $row->id }}">
                                    <input type="number" class="form-control form-control-sm"
                                           name="rows[{{ $i }}][sequence_no]"
                                           value="{{ old("rows.$i.sequence_no", $row->sequence_no) }}"
                                           {{ $plan->status === 'approved' ? 'disabled' : '' }}>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $row->activity?->name }}</div>
                                    <div class="text-muted small">{{ $row->activity?->code }}</div>
                                </td>
                                <td>
                                    <input type="hidden" name="rows[{{ $i }}][is_enabled]" value="0">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1"
                                               name="rows[{{ $i }}][is_enabled]"
                                               {{ old("rows.$i.is_enabled", $row->is_enabled) ? 'checked' : '' }}
                                               {{ $plan->status === 'approved' ? 'disabled' : '' }}>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm"
                                            name="rows[{{ $i }}][contractor_party_id]"
                                            {{ $plan->status === 'approved' ? 'disabled' : '' }}>
                                        <option value="">—</option>
                                        @foreach($contractors as $c)
                                            <option value="{{ $c->id }}"
                                                {{ (string) old("rows.$i.contractor_party_id", $row->contractor_party_id) === (string) $c->id ? 'selected' : '' }}>
                                                {{ $c->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="form-control form-control-sm"
                                           name="rows[{{ $i }}][rate]"
                                           value="{{ old("rows.$i.rate", $row->rate) }}"
                                           {{ $plan->status === 'approved' ? 'disabled' : '' }}>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm"
                                            name="rows[{{ $i }}][rate_uom_id]"
                                            {{ $plan->status === 'approved' ? 'disabled' : '' }}>
                                        <option value="">—</option>
                                        @foreach($uoms as $u)
                                            <option value="{{ $u->id }}"
                                                {{ (string) old("rows.$i.rate_uom_id", $row->rate_uom_id) === (string) $u->id ? 'selected' : '' }}>
                                                {{ $u->code }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="date" class="form-control form-control-sm"
                                           name="rows[{{ $i }}][planned_date]"
                                           value="{{ old("rows.$i.planned_date", optional($row->planned_date)->format('Y-m-d')) }}"
                                           {{ $plan->status === 'approved' ? 'disabled' : '' }}>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($plan->status !== 'approved')
                    <button class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i> Save Route
                    </button>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
