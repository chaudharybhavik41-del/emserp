<div class="modal fade" id="changePostingDateModal" tabindex="-1" aria-labelledby="changePostingDateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('accounting.subcontractor-ra.change-posting-date', ['subcontractorRa' => $subcontractorRa->id]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="changePostingDateModalLabel">Change Posting Date</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="posting_date" class="form-label">Posting Date</label>
                        <input
                            type="date"
                            class="form-control @error('posting_date') is-invalid @enderror"
                            id="posting_date"
                            name="posting_date"
                            value="{{ old('posting_date', optional($subcontractorRa->posting_date ?: $subcontractorRa->bill_date)->format('Y-m-d')) }}"
                            min="{{ optional($subcontractorRa->bill_date)->format('Y-m-d') }}"
                            max="{{ now()->toDateString() }}"
                            required
                        >
                        @error('posting_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label for="posting_date_reason" class="form-label">Reason</label>
                        <textarea
                            class="form-control @error('reason') is-invalid @enderror"
                            id="posting_date_reason"
                            name="reason"
                            rows="3"
                            maxlength="500"
                            required
                        >{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Posting Date</button>
                </div>
            </form>
        </div>
    </div>
</div>
