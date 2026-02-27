@php
    /** @var \App\Models\CrmLead|null $lead */
    $isEdit = isset($lead) && $lead->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('crm.leads.update', $lead) : route('crm.leads.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row mb-3">
        <div class="col-md-3">
            <label for="code" class="form-label">
                Code
            </label>
            <input type="text"
                   id="code"
                   name="code"
                   class="form-control @error('code') is-invalid @enderror"
                   value="{{ old('code', $lead->code ?? $nextCode ?? '') }}"
                   maxlength="50">
            @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-5">
            <label for="title" class="form-label">
                Title
            </label>
            <input type="text"
                   id="title"
                   name="title"
                   class="form-control @error('title') is-invalid @enderror"
                   value="{{ old('title', $lead->title ?? '') }}"
                   maxlength="255">
            @error('title')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="party_id" class="form-label">Client</label>
            <select id="party_id"
                    name="party_id"
                    class="form-select select2-basic @error('party_id') is-invalid @enderror">
                <option value="">-- Select Client --</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}"
                        {{ (int) old('party_id', $lead->party_id ?? 0) === $client->id ? 'selected' : '' }}>
                        {{ $client->code }} - {{ $client->name }}
                    </option>
                @endforeach
            </select>
            @error('party_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="contact_name" class="form-label">Contact Name</label>
            <input type="text"
                   id="contact_name"
                   name="contact_name"
                   class="form-control @error('contact_name') is-invalid @enderror"
                   value="{{ old('contact_name', $lead->contact_name ?? '') }}"
                   maxlength="255">
            @error('contact_name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="contact_email" class="form-label">Contact Email</label>
            <input type="email"
                   id="contact_email"
                   name="contact_email"
                   class="form-control @error('contact_email') is-invalid @enderror"
                   value="{{ old('contact_email', $lead->contact_email ?? '') }}"
                   maxlength="255">
            @error('contact_email')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="contact_phone" class="form-label">Contact Phone</label>
            <input type="text"
                   id="contact_phone"
                   name="contact_phone"
                   class="form-control @error('contact_phone') is-invalid @enderror"
                   value="{{ old('contact_phone', $lead->contact_phone ?? '') }}"
                   maxlength="50">
            @error('contact_phone')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <label for="lead_source_id" class="form-label">Source</label>
            <select id="lead_source_id"
                    name="lead_source_id"
                    class="form-select select2-basic @error('lead_source_id') is-invalid @enderror">
                <option value="">-- Select Source --</option>
                @foreach($sources as $source)
                    <option value="{{ $source->id }}"
                        {{ (int) old('lead_source_id', $lead->lead_source_id ?? 0) === $source->id ? 'selected' : '' }}>
                        {{ $source->code }} - {{ $source->name }}
                    </option>
                @endforeach
            </select>
            @error('lead_source_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="lead_stage_id" class="form-label">Stage</label>
            <select id="lead_stage_id"
                    name="lead_stage_id"
                    class="form-select select2-basic @error('lead_stage_id') is-invalid @enderror">
                <option value="">-- Select Stage --</option>
                @foreach($stages as $stage)
                    <option value="{{ $stage->id }}"
                        {{ (int) old('lead_stage_id', $lead->lead_stage_id ?? 0) === $stage->id ? 'selected' : '' }}>
                        {{ $stage->code }} - {{ $stage->name }}
                    </option>
                @endforeach
            </select>
            @error('lead_stage_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="lead_date" class="form-label">Lead Date</label>
            <input type="date"
                   id="lead_date"
                   name="lead_date"
                   class="form-control @error('lead_date') is-invalid @enderror"
                   value="{{ old('lead_date', optional($lead->lead_date ?? null)->format('Y-m-d')) }}">
            @error('lead_date')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="expected_close_date" class="form-label">Expected Close</label>
            <input type="date"
                   id="expected_close_date"
                   name="expected_close_date"
                   class="form-control @error('expected_close_date') is-invalid @enderror"
                   value="{{ old('expected_close_date', optional($lead->expected_close_date ?? null)->format('Y-m-d')) }}">
            @error('expected_close_date')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <label for="expected_value" class="form-label">Expected Value</label>
            <input type="number"
                   step="0.01"
                   id="expected_value"
                   name="expected_value"
                   class="form-control @error('expected_value') is-invalid @enderror"
                   value="{{ old('expected_value', $lead->expected_value ?? '') }}">
            @error('expected_value')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="probability" class="form-label">Probability (%)</label>
            <input type="number"
                   step="1"
                   min="0"
                   max="100"
                   id="probability"
                   name="probability"
                   class="form-control @error('probability') is-invalid @enderror"
                   value="{{ old('probability', $lead->probability ?? '') }}">
            @error('probability')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="owner_id" class="form-label">Owner</label>
            <select id="owner_id"
                    name="owner_id"
                    class="form-select select2-basic @error('owner_id') is-invalid @enderror">
                <option value="">-- Select Owner --</option>
                @foreach($owners as $owner)
                    <option value="{{ $owner->id }}"
                        {{ (int) old('owner_id', $lead->owner_id ?? auth()->id()) === $owner->id ? 'selected' : '' }}>
                        {{ $owner->name }}
                    </option>
                @endforeach
            </select>
            @error('owner_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="department_id" class="form-label">Department</label>
            <select id="department_id"
                    name="department_id"
                    class="form-select select2-basic @error('department_id') is-invalid @enderror">
                <option value="">-- Select Department --</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}"
                        {{ (int) old('department_id', $lead->department_id ?? 0) === $department->id ? 'selected' : '' }}>
                        {{ $department->code }} - {{ $department->name }}
                    </option>
                @endforeach
            </select>
            @error('department_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="mb-3">
        <label for="notes" class="form-label">Notes</label>
        <textarea id="notes"
                  name="notes"
                  rows="4"
                  class="form-control @error('notes') is-invalid @enderror"
        >{{ old('notes', $lead->notes ?? '') }}</textarea>
        @error('notes')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <a href="{{ route('crm.leads.index') }}" class="btn btn-link">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Lead' : 'Create Lead' }}
        </button>
    </div>
</form>
