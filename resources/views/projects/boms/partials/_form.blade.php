@csrf

<div class="mb-3">
    <label class="form-label">Project</label>
    <input type="text"
           class="form-control"
           value="{{ $project->code }} - {{ $project->name }}"
           disabled>
</div>

<div class="mb-3">
    <label class="form-label">BOM Number</label>
    <input type="text"
           class="form-control"
           value="{{ $bom->bom_number ?? 'Auto-generated on save' }}"
           disabled>
</div>

<div class="mb-3">
    <label for="version" class="form-label">Version</label>
    <input type="number"
           name="version"
           id="version"
           class="form-control @error('version') is-invalid @enderror"
           value="{{ old('version', $bom->version) }}">
    @error('version')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <input type="text"
           class="form-control"
           value="{{ $bom->status->value ?? 'draft' }}"
           disabled>
</div>

<div class="mb-3">
    <label for="metadata_remarks" class="form-label">Remarks (Metadata)</label>
    <textarea name="metadata[remarks]"
              id="metadata_remarks"
              rows="3"
              class="form-control @error('metadata.remarks') is-invalid @enderror">{{ old('metadata.remarks', $bom->metadata['remarks'] ?? '') }}</textarea>
    @error('metadata.remarks')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
