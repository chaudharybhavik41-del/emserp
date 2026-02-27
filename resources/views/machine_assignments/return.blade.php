@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-arrow-return-left"></i> Return Machine</h2>
        <a href="{{ route('machine-assignments.show', $assignment) }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <!-- Assignment Summary -->
    <div class="card mb-3 bg-light">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <strong>Assignment #:</strong><br>
                    {{ $assignment->assignment_number }}
                </div>
                <div class="col-md-4">
                    <strong>Machine:</strong><br>
                    {{ $assignment->machine->code }} - {{ $assignment->machine->name }}
                </div>
                <div class="col-md-4">
                    <strong>Assigned To:</strong><br>
                    {{ $assignment->getAssignedToName() }}
                </div>
            </div>

            @php
                $treatment = strtolower((string)($assignment->machine->accounting_treatment ?? ''));
            @endphp

            @if($treatment === 'tool_stock')
                <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-info-circle"></i>
                    This machine is marked as <strong>Tool Stock</strong>. Returning / scrapping will automatically create an accounting voucher (Tools Transfer).
                </div>
            @endif
        </div>
    </div>

    <form action="{{ route('machine-assignments.process-return', $assignment) }}" method="POST">
        @csrf

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-8">
                <!-- Return Details -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Return Details</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Return Date <span class="text-danger">*</span></label>
                                <input type="date" name="actual_return_date" 
                                       class="form-control @error('actual_return_date') is-invalid @enderror" 
                                       value="{{ old('actual_return_date', date('Y-m-d')) }}"
                                       max="{{ date('Y-m-d') }}"
                                       required>
                                @error('actual_return_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Return Action <span class="text-danger">*</span></label>
                                <select id="return_disposition" name="return_disposition" class="form-select @error('return_disposition') is-invalid @enderror" required>
                                    <option value="returned" {{ old('return_disposition', 'returned') == 'returned' ? 'selected' : '' }}>Return to Store</option>
                                    <option value="scrapped" {{ old('return_disposition') == 'scrapped' ? 'selected' : '' }}>Scrapped / Not Returnable</option>
                                </select>
                                @error('return_disposition')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Machine Condition <span class="text-danger">*</span></label>
                                <select name="condition_at_return" class="form-select @error('condition_at_return') is-invalid @enderror" required>
                                    <option value="good" {{ old('condition_at_return') == 'good' ? 'selected' : '' }}>Good Condition</option>
                                    <option value="minor_wear" {{ old('condition_at_return') == 'minor_wear' ? 'selected' : '' }}>Minor Wear</option>
                                    <option value="damaged" {{ old('condition_at_return') == 'damaged' ? 'selected' : '' }}>Damaged</option>
                                    <option value="not_returned" {{ old('condition_at_return') == 'not_returned' ? 'selected' : '' }}>Not Returned / Lost</option>
                                </select>
                                @error('condition_at_return')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Meter Reading at Return (hrs)</label>
                                <input type="number" name="meter_reading_at_return" step="0.01" 
                                       class="form-control @error('meter_reading_at_return') is-invalid @enderror" 
                                       value="{{ old('meter_reading_at_return') }}" placeholder="Optional">
                                @error('meter_reading_at_return')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if($assignment->meter_reading_at_issue)
                                    <small class="text-muted">Issued at: {{ number_format($assignment->meter_reading_at_issue, 2) }} hrs</small>
                                @endif
                            </div>
                        </div>

                        <!-- Scrap / Loss Settlement -->
                        <div id="scrapFields" class="border rounded p-3 mb-3" style="display:none;">
                            <h6 class="mb-3"><i class="bi bi-exclamation-triangle"></i> Scrap / Loss Settlement</h6>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Damage Borne By <span class="text-danger">*</span></label>
                                    <select name="damage_borne_by" class="form-select @error('damage_borne_by') is-invalid @enderror">
                                        <option value="">-- Select --</option>
                                        <option value="company" {{ old('damage_borne_by') == 'company' ? 'selected' : '' }}>Company</option>
                                        <option value="contractor" {{ old('damage_borne_by') == 'contractor' ? 'selected' : '' }}>Contractor</option>
                                        <option value="shared" {{ old('damage_borne_by') == 'shared' ? 'selected' : '' }}>Shared (Company + Contractor)</option>
                                    </select>
                                    @error('damage_borne_by')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">If contractor is selected, the recovery will be debited to contractor ledger (deducted from payable).</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Recovery Amount (from Contractor)</label>
                                    <input type="number" name="damage_recovery_amount" step="0.01"
                                           class="form-control @error('damage_recovery_amount') is-invalid @enderror"
                                           value="{{ old('damage_recovery_amount') }}" placeholder="0.00">
                                    @error('damage_recovery_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">If shared, enter partial recovery. Remaining will be booked to Tools Scrap/Loss expense.</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Return Remarks</label>
                            <textarea name="return_remarks" class="form-control @error('return_remarks') is-invalid @enderror" 
                                      rows="4" placeholder="Condition notes, damage details, etc...">{{ old('return_remarks') }}</textarea>
                            @error('return_remarks')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-4">
                <!-- Warning Card -->
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-warning"><strong>Important Notes</strong></div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Ensure machine condition is accurately recorded</li>
                            <li>Update meter reading if applicable</li>
                            <li>For Tool Stock machines, return/scrap creates accounting voucher automatically</li>
                            <li>Scrapped: contractor recovery will be posted to contractor ledger (deduction)</li>
                        </ul>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-body">
                        <button type="submit" class="btn btn-success w-100 mb-2">
                            <i class="bi bi-check-circle"></i> Process Return
                        </button>
                        <a href="{{ route('machine-assignments.show', $assignment) }}" class="btn btn-secondary w-100">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sel = document.getElementById('return_disposition');
        const scrap = document.getElementById('scrapFields');

        function toggleScrap() {
            if (!sel || !scrap) return;
            scrap.style.display = (sel.value === 'scrapped') ? 'block' : 'none';
        }

        if (sel) {
            sel.addEventListener('change', toggleScrap);
            toggleScrap();
        }
    });
</script>
@endpush
