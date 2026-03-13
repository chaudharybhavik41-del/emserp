@extends('layouts.erp')

@section('title', $materialRequirement->exists ? 'Edit Production V2 Material Requirement' : 'Create Production V2 Material Requirement')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $materialRequirement->exists ? 'Edit Material Requirement' : 'Create Material Requirement' }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.material-requirements.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
@endsection

@section('content')
    <form method="POST" action="{{ $materialRequirement->exists ? route('projects.production-v2.material-requirements.update', ['project' => $project->id, 'materialRequirement' => $materialRequirement->id]) : route('projects.production-v2.material-requirements.store', ['project' => $project->id]) }}">
        @csrf
        @if($materialRequirement->exists)
            @method('PUT')
        @endif
        <input type="hidden" name="revision_no" value="{{ old('revision_no', $materialRequirement->revision_no ?: 1) }}">

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-2">
                        <label class="form-label">Revision</label>
                        <input class="form-control" value="R{{ old('revision_no', $materialRequirement->revision_no ?: 1) }}" readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Requirement Number</label>
                        <input name="requirement_number" class="form-control" value="{{ old('requirement_number', $defaultRequirementNumber) }}" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Requirement Date</label>
                        <input type="date" name="requirement_date" class="form-control" value="{{ old('requirement_date', $materialRequirement->requirement_date?->format('Y-m-d') ?: now()->toDateString()) }}" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Basis</label>
                        <select name="basis" class="form-select" data-erp-select data-hide-search="true">
                            @foreach(['design_snapshot', 'released_design'] as $basis)
                                <option value="{{ $basis }}" @selected(old('basis', $materialRequirement->basis ?: 'design_snapshot') === $basis)>{{ str_replace('_', ' ', ucfirst($basis)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" data-erp-select data-hide-search="true">
                            @foreach(['draft', 'approved'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $materialRequirement->status ?: 'approved') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control">{{ old('remarks', $materialRequirement->remarks) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Requirement Rows</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Material</th>
                                <th>Category</th>
                                <th>Grade</th>
                                <th>Profile</th>
                                <th class="text-end">Required Qty</th>
                                <th class="text-end">Required Weight</th>
                                <th class="text-end">Planned Cut</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach(old('rows', $seedRows) as $index => $row)
                            <tr>
                                <td>
                                    <input type="hidden" name="rows[{{ $index }}][material_item_id]" value="{{ $row['material_item_id'] ?? '' }}">
                                    {{ $row['material_item_label'] ?? '-' }}
                                </td>
                                <td>
                                    <input type="hidden" name="rows[{{ $index }}][material_category]" value="{{ $row['material_category'] ?? '' }}">
                                    {{ $row['material_category'] ?? '-' }}
                                </td>
                                <td>
                                    <input type="hidden" name="rows[{{ $index }}][material_grade]" value="{{ $row['material_grade'] ?? '' }}">
                                    {{ $row['material_grade'] ?? '-' }}
                                </td>
                                <td>
                                    <input type="hidden" name="rows[{{ $index }}][thickness_mm]" value="{{ $row['thickness_mm'] ?? '' }}">
                                    <input type="hidden" name="rows[{{ $index }}][width_mm]" value="{{ $row['width_mm'] ?? '' }}">
                                    <input type="hidden" name="rows[{{ $index }}][length_mm]" value="{{ $row['length_mm'] ?? '' }}">
                                    <input type="hidden" name="rows[{{ $index }}][profile_text]" value="{{ $row['profile_text'] ?? '' }}">
                                    <input type="hidden" name="rows[{{ $index }}][part_revision_root_ids_json]" value="{{ $row['part_revision_root_ids_json'] ?? '[]' }}">
                                    {{ $row['profile_text'] ?? '-' }}
                                </td>
                                <td class="text-end"><input type="number" step="0.001" min="0.001" name="rows[{{ $index }}][required_qty]" class="form-control form-control-sm text-end" value="{{ $row['required_qty'] ?? '' }}"></td>
                                <td class="text-end"><input type="number" step="0.001" min="0" name="rows[{{ $index }}][required_weight_kg]" class="form-control form-control-sm text-end" value="{{ $row['required_weight_kg'] ?? '' }}"></td>
                                <td class="text-end"><input type="number" step="0.001" min="0" name="rows[{{ $index }}][planned_cut_qty_snapshot]" class="form-control form-control-sm text-end" value="{{ $row['planned_cut_qty_snapshot'] ?? '' }}"></td>
                                <td><input name="rows[{{ $index }}][remarks]" class="form-control form-control-sm" value="{{ $row['remarks'] ?? '' }}"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ $materialRequirement->exists ? 'Update Material Requirement' : 'Create Material Requirement' }}</button>
                <a href="{{ route('projects.production-v2.material-requirements.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
@endsection
