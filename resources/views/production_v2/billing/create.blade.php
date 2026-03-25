@extends('layouts.erp')

@section('title', 'Generate Production V2 Bill')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Generate Production V2 Bill</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.billing-rates.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">Rate Cards</a>
        <a href="{{ route('projects.production-v2.billing.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'billing'])

    <form method="post" action="{{ route('projects.production-v2.billing.store', ['project' => $project->id]) }}" class="row g-3">
        @csrf
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Contractor</label>
                            <select name="contractor_party_id" class="form-select @error('contractor_party_id') is-invalid @enderror" required>
                                <option value="">Select contractor</option>
                                @foreach($contractors as $contractor)
                                    <option value="{{ $contractor->id }}" @selected((int) old('contractor_party_id') === (int) $contractor->id)>{{ $contractor->name }}</option>
                                @endforeach
                            </select>
                            @error('contractor_party_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">Period From</label>
                            <input type="date" name="period_from" value="{{ old('period_from', now()->startOfMonth()->toDateString()) }}" class="form-control @error('period_from') is-invalid @enderror" required>
                            @error('period_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">Period To</label>
                            <input type="date" name="period_to" value="{{ old('period_to', now()->toDateString()) }}" class="form-control @error('period_to') is-invalid @enderror" required>
                            @error('period_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">Bill Date</label>
                            <input type="date" name="bill_date" value="{{ old('bill_date', now()->toDateString()) }}" class="form-control @error('bill_date') is-invalid @enderror">
                            @error('bill_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">GST Type</label>
                            <select name="gst_type" class="form-select">
                                <option value="cgst_sgst" @selected(old('gst_type', 'cgst_sgst') === 'cgst_sgst')>CGST + SGST</option>
                                <option value="igst" @selected(old('gst_type') === 'igst')>IGST</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">GST Rate</label>
                            <input type="number" step="0.01" min="0" max="99.99" name="gst_rate" value="{{ old('gst_rate', '18') }}" class="form-control">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="remarks" value="{{ old('remarks') }}" class="form-control" placeholder="Billing remarks">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header">Active Rate Coverage</div>
                <div class="card-body">
                    @if($rateSummary->isEmpty())
                        <div class="text-muted">No active rate cards created yet.</div>
                    @else
                        @foreach($rateSummary as $contractorId => $rows)
                            <div class="mb-3">
                                <div class="fw-semibold">{{ $rows->first()?->contractor?->name ?: 'Contractor #' . $contractorId }}</div>
                                <div class="small text-body-secondary">
                                    {{ $rows->map(fn($row) => $row->source_type === 'operation' ? ('operation:' . ($row->operationMaster?->code ?: 'n/a')) : $row->source_type)->implode(', ') }}
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div class="small text-body-secondary">Billing uses approved V2 execution records with contractor mapping and active rate cards. Already billed source records are skipped automatically.</div>
                    <button type="submit" class="btn btn-primary">Generate Bill</button>
                </div>
            </div>
        </div>
    </form>
@endsection
