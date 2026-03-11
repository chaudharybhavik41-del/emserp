@csrf

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Asset Code <span class="text-danger">*</span></label>
        <input type="text" name="asset_code" class="form-control" value="{{ old('asset_code', $asset->asset_code) }}" required>
    </div>
    <div class="col-md-8">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $asset->name) }}" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Item ID (SKU)</label>
        <input type="number" name="item_id" class="form-control" value="{{ old('item_id', $asset->item_id) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Machine Type</label>
        <select name="machine_type" class="form-select">
            <option value="">Select</option>
            @foreach($machineTypes as $machineType)
                <option value="{{ $machineType }}" {{ old('machine_type', $asset->machine_type) === $machineType ? 'selected' : '' }}>
                    {{ ucfirst(str_replace('_', ' ', $machineType)) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Serial No</label>
        <input type="text" name="serial_no" class="form-control" value="{{ old('serial_no', $asset->serial_no) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Vendor</label>
        <select name="vendor_party_id" class="form-select">
            <option value="">Select vendor</option>
            @foreach($vendors as $vendor)
                <option value="{{ $vendor->id }}" {{ (string) old('vendor_party_id', $asset->vendor_party_id) === (string) $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Make</label>
        <input type="text" name="make" class="form-control" value="{{ old('make', $asset->make) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Model</label>
        <input type="text" name="model" class="form-control" value="{{ old('model', $asset->model) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Capacity</label>
        <input type="text" name="capacity" class="form-control" value="{{ old('capacity', $asset->capacity) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Status <span class="text-danger">*</span></label>
        <select name="status" class="form-select" required>
            @foreach($statuses as $status)
                <option value="{{ $status }}" {{ old('status', $asset->status) === $status ? 'selected' : '' }}>
                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Project</label>
        <select name="project_id" class="form-select">
            <option value="">Select project</option>
            @foreach($projects as $project)
                <option value="{{ $project->id }}" {{ (string) old('project_id', $asset->project_id) === (string) $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Location ID</label>
        <input type="number" name="location_id" class="form-control" value="{{ old('location_id', $asset->location_id) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">Purchase Date</label>
        <input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date', optional($asset->purchase_date)->format('Y-m-d')) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">Put to Use Date</label>
        <input type="date" name="put_to_use_date" class="form-control" value="{{ old('put_to_use_date', optional($asset->put_to_use_date)->format('Y-m-d')) }}">
    </div>

    <div class="col-12"><hr><h6>Opening Values</h6></div>

    <div class="col-md-3">
        <label class="form-label">Opening WDV <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="opening_wdv" class="form-control" value="{{ old('opening_wdv', $asset->opening_wdv ?? 0) }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Opening As Of</label>
        <input type="date" name="opening_as_of" class="form-control" value="{{ old('opening_as_of', optional($asset->opening_as_of)->format('Y-m-d')) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Original Cost</label>
        <input type="number" step="0.01" min="0" name="original_cost" class="form-control" value="{{ old('original_cost', $asset->original_cost) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Accum. Dep Opening</label>
        <input type="number" step="0.01" min="0" name="accum_dep_opening" class="form-control" value="{{ old('accum_dep_opening', $asset->accum_dep_opening) }}">
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger mt-3 mb-0">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
