@extends('layouts.erp')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="mb-1">
                    Select Stock for
                    @if($group['category'] === 'plate')
                        Plate
                    @else
                        Section
                    @endif
                </h1>
                <div class="text-muted">
                    Project: {{ $project->code ?? $project->name }} |
                    BOM: {{ $bom->bom_number ?? ('BOM #' . $bom->id) }}
                </div>
                <div class="text-muted small">
                    Grade: {{ $group['grade'] ?? '-' }} |
                    @if($group['category'] === 'plate')
                        Thickness: {{ $group['thickness_mm'] }} mm
                    @else
                        Section: {{ $group['section'] ?? '-' }}
                    @endif
                </div>
                @if(!empty($group['default_item_code']))
                    <div class="text-muted small">
                        Item: {{ $group['default_item_code'] }} - {{ $group['default_item_name'] }}
                    </div>
                @endif
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('projects.boms.material-planning.index', [$project, $bom]) }}"
                   class="btn btn-outline-secondary">
                    Back to Planning Summary
                </a>
                @if($group['category'] === 'plate' && $group['thickness_mm'])
                    <a href="{{ route('projects.boms.cutting-plans.create', [
                        $project,
                        $bom,
                        'grade'        => $group['grade'],
                        'thickness_mm' => $group['thickness_mm'],
                    ]) }}" class="btn btn-outline-primary">
                        Cutting Plan / Nesting
                    </a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning">
                {{ session('warning') }}
            </div>
        @endif

        <div class="row">
            {{-- LEFT SIDE: summary + planning --}}
            <div class="col-md-4">
                {{-- Requirement summary --}}
                <div class="card mb-3">
                    <div class="card-header">
                        Requirement Summary
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-6">Total required weight</dt>
                            <dd class="col-6">{{ number_format($group['total_weight'] ?? 0, 3) }} kg</dd>

                            <dt class="col-6">Reserved weight</dt>
                            <dd class="col-6">{{ number_format($reservedWeight, 3) }} kg</dd>

                            <dt class="col-6">Remaining</dt>
                            <dd class="col-6">
                                @if($reservedWeight > ($group['total_weight'] ?? 0))
                                    <span class="text-danger">
                                        {{ number_format($remainingWeight, 3) }} kg (over-allocated)
                                    </span>
                                @else
                                    {{ number_format($remainingWeight, 3) }} kg
                                @endif
                            </dd>

                            <dt class="col-6">From BOM lines</dt>
                            <dd class="col-6">{{ $group['lines'] ?? 0 }} line(s)</dd>
                        </dl>
                    </div>
                </div>

                {{-- Plates: info only, planning in Cutting Plan --}}
                @if($group['category'] === 'plate')
                    <div class="card mb-3">
                        <div class="card-header">
                            Plates from Cutting Plans
                        </div>
                        <div class="card-body">
                            <p class="mb-1">
                                New plates for this thickness are defined in the
                                <strong>Cutting Plan / Nesting</strong> screen.
                            </p>
                            <p class="small text-muted mb-0">
                                This page is mainly for reviewing and, if needed, adjusting stock
                                reservations for this group. Planned plates created in Cutting Plans
                                automatically appear in the planning summary coverage.
                            </p>
                        </div>
                    </div>
                @else
                    {{-- Sections: plan new stock pieces (lengths) --}}
                    <div class="card mb-3">
                        <div class="card-header">
                            Plan New Stock Pieces for this BOM
                        </div>
                        <div class="card-body">
                            @if(empty($group['default_item_id']))
                                <p class="text-muted mb-0">
                                    No default RAW item found for this group.
                                    Please ensure BOM items are linked to an Item with this grade/section.
                                </p>
                            @else
                                <form method="POST"
                                      action="{{ route('projects.boms.material-planning.add-planned-piece', [$project, $bom]) }}"
                                      class="row g-2">
                                    @csrf
                                    <input type="hidden" name="group_category" value="{{ $group['category'] }}">
                                    <input type="hidden" name="grade" value="{{ $group['grade'] }}">
                                    <input type="hidden" name="thickness_mm" value="{{ $group['thickness_mm'] }}">
                                    <input type="hidden" name="section_profile" value="{{ $group['section'] }}">

                                    <div class="col-4">
                                        <label class="form-label">Quantity</label>
                                        <input type="number"
                                               name="planned_quantity"
                                               value="{{ old('planned_quantity', 1) }}"
                                               min="1"
                                               class="form-control form-control-sm @error('planned_quantity') is-invalid @enderror">
                                        @error('planned_quantity')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-8">
                                        <label class="form-label">Length (mm)</label>
                                        <input type="number"
                                               name="length_mm"
                                               value="{{ old('length_mm') }}"
                                               class="form-control form-control-sm @error('length_mm') is-invalid @enderror">
                                        @error('length_mm')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label">Location</label>
                                        <input type="text"
                                               name="location"
                                               value="{{ old('location') }}"
                                               class="form-control form-control-sm @error('location') is-invalid @enderror">
                                        @error('location')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Remarks</label>
                                        <textarea name="remarks"
                                                  rows="2"
                                                  class="form-control form-control-sm @error('remarks') is-invalid @enderror">{{ old('remarks') }}</textarea>
                                        @error('remarks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">
                                            These planned pieces will be created as stock rows, reserved
                                            for this BOM, and counted as coverage in the planning summary.
                                        </div>
                                    </div>

                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            Add Planned Piece(s)
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Legend --}}
                <div class="card mb-3">
                    <div class="card-header">
                        Legend
                    </div>
                    <div class="card-body">
                        <ul class="mb-0 small">
                            <li><strong>Available pieces</strong> are free to reserve for this BOM.</li>
                            <li><strong>Reserved pieces</strong> below are already tied to this BOM &amp; project.</li>
                            <li><strong>Planned plates</strong> are created in Cutting Plans.</li>
                            <li><strong>Planned section pieces</strong> here are new lengths you intend to purchase for this BOM.</li>
                            <li>No partial allocations yet – selecting a piece reserves the whole plate/length.</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- RIGHT SIDE: stock lists --}}
            <div class="col-md-8">
                {{-- Available pieces --}}
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Available Stock Pieces (matching grade &amp; thickness/section)</span>
                        <small class="text-muted">Showing up to 200 records</small>
                    </div>
                    <div class="card-body p-0">
                        @if($availablePieces->isEmpty())
                            <p class="text-muted p-3 mb-0">
                                No available stock pieces found for this grade / thickness / section.
                            </p>
                        @else
                            <form method="POST"
                                  action="{{ route('projects.boms.material-planning.allocate', [$project, $bom]) }}">
                                @csrf
                                <input type="hidden" name="group_category" value="{{ $group['category'] }}">
                                <input type="hidden" name="grade" value="{{ $group['grade'] }}">
                                <input type="hidden" name="thickness_mm" value="{{ $group['thickness_mm'] }}">
                                <input type="hidden" name="section_profile" value="{{ $group['section'] }}">
                                <input type="hidden" name="action" value="reserve">

                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 align-middle">
                                        <thead>
                                        <tr>
                                            <th style="width: 30px;">
                                                <input type="checkbox"
                                                       class="form-check-input"
                                                       onclick="document.querySelectorAll('.js-piece-checkbox').forEach(cb => cb.checked = this.checked);">
                                            </th>
                                            <th>ID</th>
                                            <th>Size</th>
                                            <th>Weight (kg)</th>
                                            <th>Plate / Piece No.</th>
                                            <th>Heat / MTC</th>
                                            <th>Location</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($availablePieces as $piece)
                                            <tr>
                                                <td>
                                                    <input type="checkbox"
                                                           name="stock_piece_ids[]"
                                                           value="{{ $piece->id }}"
                                                           class="form-check-input js-piece-checkbox">
                                                </td>
                                                <td>{{ $piece->id }}</td>
                                                <td>
                                                    @if($group['category'] === 'plate')
                                                        {{ $piece->thickness_mm }} thk x {{ $piece->width_mm }} x {{ $piece->length_mm }} mm
                                                    @else
                                                        {{ $piece->section_profile }}
                                                        ({{ $piece->length_mm }} mm)
                                                    @endif
                                                </td>
                                                <td>{{ $piece->weight_kg !== null ? number_format($piece->weight_kg, 3) : '-' }}</td>
                                                <td>{{ $piece->plate_number ?? '-' }}</td>
                                                <td>
                                                    <div>{{ $piece->heat_number ?? '-' }}</div>
                                                    <div class="text-muted small">{{ $piece->mtc_number ?? '' }}</div>
                                                </td>
                                                <td>{{ $piece->location ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="border-top p-2 text-end">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        Reserve Selected Pieces
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Reserved pieces --}}
                <div class="card mb-3">
                    <div class="card-header">
                        Already Reserved for this BOM
                    </div>
                    <div class="card-body p-0">
                        @if($reservedPieces->isEmpty())
                            <p class="text-muted p-3 mb-0">
                                No pieces reserved yet for this group.
                            </p>
                        @else
                            <form method="POST"
                                  action="{{ route('projects.boms.material-planning.allocate', [$project, $bom]) }}">
                                @csrf
                                <input type="hidden" name="group_category" value="{{ $group['category'] }}">
                                <input type="hidden" name="grade" value="{{ $group['grade'] }}">
                                <input type="hidden" name="thickness_mm" value="{{ $group['thickness_mm'] }}">
                                <input type="hidden" name="section_profile" value="{{ $group['section'] }}">
                                <input type="hidden" name="action" value="release">

                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 align-middle">
                                        <thead>
                                        <tr>
                                            <th style="width: 30px;">
                                                <input type="checkbox"
                                                       class="form-check-input"
                                                       onclick="document.querySelectorAll('.js-reserved-checkbox').forEach(cb => cb.checked = this.checked);">
                                            </th>
                                            <th>ID</th>
                                            <th>Size</th>
                                            <th>Weight (kg)</th>
                                            <th>Plate / Piece No.</th>
                                            <th>Heat / MTC</th>
                                            <th>Location</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($reservedPieces as $piece)
                                            <tr>
                                                <td>
                                                    <input type="checkbox"
                                                           name="stock_piece_ids[]"
                                                           value="{{ $piece->id }}"
                                                           class="form-check-input js-reserved-checkbox">
                                                </td>
                                                <td>{{ $piece->id }}</td>
                                                <td>
                                                    @if($group['category'] === 'plate')
                                                        {{ $piece->thickness_mm }} thk x {{ $piece->width_mm }} x {{ $piece->length_mm }} mm
                                                    @else
                                                        {{ $piece->section_profile }}
                                                        ({{ $piece->length_mm }} mm)
                                                    @endif
                                                </td>
                                                <td>{{ $piece->weight_kg !== null ? number_format($piece->weight_kg, 3) : '-' }}</td>
                                                <td>{{ $piece->plate_number ?? '-' }}</td>
                                                <td>
                                                    <div>{{ $piece->heat_number ?? '-' }}</div>
                                                    <div class="text-muted small">{{ $piece->mtc_number ?? '' }}</div>
                                                </td>
                                                <td>{{ $piece->location ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="border-top p-2 text-end">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        Release Selected Pieces
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
