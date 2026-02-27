@php
    /** @var \App\Models\Project|null $project */
    $project = $project ?? null;
    $isEdit  = $project && $project->exists;

    // Ensure view vars always exist (even if not passed for some reason)
    $clients    = $clients    ?? collect();
    $tpiParties = $tpiParties ?? collect();

    // Handle dates safely (works for Carbon or plain strings)
    $startDateValue = old('start_date');
    if ($startDateValue === null && $project && $project->start_date) {
        $d = $project->start_date;
        $startDateValue = $d instanceof \DateTimeInterface
            ? $d->format('Y-m-d')
            : \Illuminate\Support\Carbon::parse($d)->format('Y-m-d');
    }

    $endDateValue = old('end_date');
    if ($endDateValue === null && $project && $project->end_date) {
        $d = $project->end_date;
        $endDateValue = $d instanceof \DateTimeInterface
            ? $d->format('Y-m-d')
            : \Illuminate\Support\Carbon::parse($d)->format('Y-m-d');
    }

    $poDateValue = old('po_date');
    if ($poDateValue === null) {
        if ($project && $project->po_date) {
            $d = $project->po_date;
            $poDateValue = $d instanceof \DateTimeInterface
                ? $d->format('Y-m-d')
                : \Illuminate\Support\Carbon::parse($d)->format('Y-m-d');
        } elseif ($project && $project->quotation && $project->quotation->po_date) {
            $d = $project->quotation->po_date;
            $poDateValue = $d instanceof \DateTimeInterface
                ? $d->format('Y-m-d')
                : \Illuminate\Support\Carbon::parse($d)->format('Y-m-d');
        }
    }

    $statusValue = old('status', $project->status ?? 'active');
@endphp

<form method="POST"
      action="{{ $isEdit ? route('projects.update', $project) : route('projects.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h5 class="mb-1">{{ $isEdit ? 'Edit Project' : 'Create Project' }}</h5>
            <p class="text-muted small mb-0">
                Maintain project master, site details, TPI and commercial terms.
            </p>
        </div>

        @if($isEdit)
            <div class="text-end small text-muted">
                @if($project->lead)
                    <div>
                        Lead:
                        <a href="{{ route('crm.leads.show', $project->lead) }}">
                            {{ $project->lead->code }}
                        </a>
                    </div>
                @endif

                @if($project->quotation)
                    <div class="mt-1">
                        Quotation:
                        <a href="{{ route('crm.quotations.show', $project->quotation) }}">
                            {{ $project->quotation->code }}
                            (Rev {{ $project->quotation->revision_no }})
                        </a>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <hr class="mb-4">

    {{-- BASIC PROJECT DETAILS --}}
    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label">Project Code</label>
            <input type="text"
                   name="code"
                   class="form-control @error('code') is-invalid @enderror"
                   value="{{ old('code', $project->code ?? '') }}"
                   {{ $isEdit ? 'readonly' : '' }}>
            @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            @unless($isEdit)
                <div class="form-text">Leave blank to auto-generate.</div>
            @endunless
        </div>

        <div class="col-md-5">
            <label class="form-label">Project Name</label>
            <input type="text"
                   name="name"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $project->name ?? '') }}">
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Client</label>
            <select name="client_party_id"
                    class="form-select @error('client_party_id') is-invalid @enderror">
                <option value="">-- Select client --</option>
                @foreach($clients as $party)
                    <option value="{{ $party->id }}"
                        {{ (int) old('client_party_id', $project->client_party_id ?? 0) === $party->id ? 'selected' : '' }}>
                        {{ $party->code }} - {{ $party->name }}
                    </option>
                @endforeach
            </select>
            @error('client_party_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- DATES & STATUS --}}
    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date"
                   name="start_date"
                   class="form-control @error('start_date') is-invalid @enderror"
                   value="{{ $startDateValue }}">
            @error('start_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date"
                   name="end_date"
                   class="form-control @error('end_date') is-invalid @enderror"
                   value="{{ $endDateValue }}">
            @error('end_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select @error('status') is-invalid @enderror">
                <option value="active"    {{ $statusValue === 'active'    ? 'selected' : '' }}>Active</option>
                <option value="on-hold"   {{ $statusValue === 'on-hold'   ? 'selected' : '' }}>On Hold</option>
                <option value="completed" {{ $statusValue === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="cancelled" {{ $statusValue === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label class="form-label">Internal Description</label>
            <textarea name="description"
                      rows="1"
                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $project->description ?? '') }}</textarea>
            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Visible internally only.</div>
        </div>
    </div>

    {{-- COMMERCIAL / PO DETAILS --}}
    <hr class="my-4">
    <h6 class="text-muted text-uppercase small mb-3">Commercial / PO Details</h6>

    <div class="row mb-3">
        <div class="col-md-4">
            <label class="form-label">Client PO / Work Order No.</label>
            <input type="text"
                   name="po_number"
                   class="form-control @error('po_number') is-invalid @enderror"
                   value="{{ old(
                        'po_number',
                        $project->po_number
                            ?? optional(optional($project)->quotation)->client_po_number
                            ?? ''
                   ) }}">
            @error('po_number')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">PO Date</label>
            <input type="date"
                   name="po_date"
                   class="form-control @error('po_date') is-invalid @enderror"
                   value="{{ $poDateValue }}">
            @error('po_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Payment Terms (days)</label>
            <input type="number"
                   min="0"
                   name="payment_terms_days"
                   class="form-control @error('payment_terms_days') is-invalid @enderror"
                   value="{{ old(
                        'payment_terms_days',
                        $project->payment_terms_days
                            ?? optional(optional($project)->quotation)->payment_terms_days
                            ?? ''
                   ) }}">
            @error('payment_terms_days')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
                Used by accounts to calculate payment due dates & reminders.
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Freight / Delivery Terms</label>
            <input type="text"
                   name="freight_terms"
                   class="form-control @error('freight_terms') is-invalid @enderror"
                   value="{{ old(
                        'freight_terms',
                        $project->freight_terms
                            ?? optional(optional($project)->quotation)->freight_terms
                            ?? ''
                   ) }}">
            @error('freight_terms')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">Special Project Notes</label>
            <textarea name="project_special_notes"
                      rows="2"
                      class="form-control @error('project_special_notes') is-invalid @enderror">{{ old(
                        'project_special_notes',
                        $project->project_special_notes
                            ?? optional(optional($project)->quotation)->project_special_notes
                            ?? ''
                      ) }}</textarea>
            @error('project_special_notes')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- SITE INFORMATION --}}
    <hr class="my-4">
    <h6 class="text-muted text-uppercase small mb-3">Site Information</h6>

    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Site Location / Address</label>
            <input type="text"
                   name="site_location"
                   class="form-control @error('site_location') is-invalid @enderror"
                   value="{{ old('site_location', $project->site_location ?? '') }}">
            @error('site_location')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Site Location URL (Google Maps)</label>
            <input type="url"
                   name="site_location_url"
                   class="form-control @error('site_location_url') is-invalid @enderror"
                   value="{{ old('site_location_url', $project->site_location_url ?? '') }}">
            @error('site_location_url')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Paste Google Maps link if available.</div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label class="form-label">Site Contact Name</label>
            <input type="text"
                   name="site_contact_name"
                   class="form-control @error('site_contact_name') is-invalid @enderror"
                   value="{{ old('site_contact_name', $project->site_contact_name ?? '') }}">
            @error('site_contact_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Site Contact Phone</label>
            <input type="text"
                   name="site_contact_phone"
                   class="form-control @error('site_contact_phone') is-invalid @enderror"
                   value="{{ old('site_contact_phone', $project->site_contact_phone ?? '') }}">
            @error('site_contact_phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Site Contact Email</label>
            <input type="email"
                   name="site_contact_email"
                   class="form-control @error('site_contact_email') is-invalid @enderror"
                   value="{{ old('site_contact_email', $project->site_contact_email ?? '') }}">
            @error('site_contact_email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- TPI DETAILS --}}
    <hr class="my-4">
    <h6 class="text-muted text-uppercase small mb-3">TPI Details (if applicable)</h6>

    <div class="row mb-3">
        <div class="col-md-4">
            <label class="form-label">TPI Agency</label>
            <select name="tpi_party_id"
                    class="form-select @error('tpi_party_id') is-invalid @enderror">
                <option value="">-- Select TPI agency --</option>
                @foreach($tpiParties as $party)
                    <option value="{{ $party->id }}"
                        {{ (int) old('tpi_party_id', $project->tpi_party_id ?? 0) === $party->id ? 'selected' : '' }}>
                        {{ $party->code }} - {{ $party->name }}
                    </option>
                @endforeach
            </select>
            @error('tpi_party_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">TPI Contact Name</label>
            <input type="text"
                   name="tpi_contact_name"
                   class="form-control @error('tpi_contact_name') is-invalid @enderror"
                   value="{{ old('tpi_contact_name', $project->tpi_contact_name ?? '') }}">
            @error('tpi_contact_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">TPI Contact Phone</label>
            <input type="text"
                   name="tpi_contact_phone"
                   class="form-control @error('tpi_contact_phone') is-invalid @enderror"
                   value="{{ old('tpi_contact_phone', $project->tpi_contact_phone ?? '') }}">
            @error('tpi_contact_phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <label class="form-label">TPI Contact Email</label>
            <input type="email"
                   name="tpi_contact_email"
                   class="form-control @error('tpi_contact_email') is-invalid @enderror"
                   value="{{ old('tpi_contact_email', $project->tpi_contact_email ?? '') }}">
            @error('tpi_contact_email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">TPI Notes</label>
            <textarea name="tpi_notes"
                      rows="2"
                      class="form-control @error('tpi_notes') is-invalid @enderror">{{ old('tpi_notes', $project->tpi_notes ?? '') }}</textarea>
            @error('tpi_notes')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- ACTIONS --}}
    <div class="d-flex justify-content-between">
        <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Project' : 'Create Project' }}
        </button>
    </div>
</form>
