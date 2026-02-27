@csrf

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Code</label>
        <input type="text" name="code" class="form-control form-control-sm"
               value="{{ old('code', $term->code) }}" required>
        <div class="form-text">Unique code, e.g. PO_STD_MATERIAL</div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control form-control-sm"
               value="{{ old('name', $term->name) }}" required>
    </div>

    <div class="col-md-2">
        <label class="form-label">Module</label>
        <input type="text" name="module" class="form-control form-control-sm"
               value="{{ old('module', $term->module ?: 'purchase') }}" required>
        <div class="form-text">e.g. purchase, sales</div>
    </div>

    <div class="col-md-2">
        <label class="form-label">Sub Module</label>
        <input type="text" name="sub_module" class="form-control form-control-sm"
               value="{{ old('sub_module', $term->sub_module ?: 'po') }}">
        <div class="form-text">e.g. po, rfq</div>
    </div>

    <div class="col-md-2">
        <label class="form-label">Version</label>
        <input type="number" name="version" class="form-control form-control-sm"
               value="{{ old('version', $term->version ?: 1) }}">
    </div>

    <div class="col-md-2">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" class="form-control form-control-sm"
               value="{{ old('sort_order', $term->sort_order ?: 0) }}">
    </div>

    <div class="col-md-2 form-check mt-4">
        <input class="form-check-input" type="checkbox" value="1"
               id="is_active"
               name="is_active"
               @checked(old('is_active', $term->is_active ?? true))>
        <label class="form-check-label" for="is_active">
            Active
        </label>
    </div>

    <div class="col-md-2 form-check mt-4">
        <input class="form-check-input" type="checkbox" value="1"
               id="is_default"
               name="is_default"
               @checked(old('is_default', $term->is_default))>
        <label class="form-check-label" for="is_default">
            Default for module
        </label>
    </div>

    <div class="col-12">
        <label class="form-label">Content (Terms &amp; Conditions)</label>
        <textarea name="content" rows="10" class="form-control" required>{{ old('content', $term->content) }}</textarea>
        <div class="form-text">
            You can store plain text or simple HTML. This text will be copied into each document as snapshot.
        </div>
    </div>
</div>
