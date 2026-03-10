@if(($bill->status ?? null) === 'posted' && $bill->voucher)
    <div class="modal fade" id="changePostingDateModal" tabindex="-1" aria-labelledby="changePostingDateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('purchase.bills.change-posting-date', ['bill' => $bill->id]) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="changePostingDateModalLabel">Change Posting Date</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="posting_date" class="form-label">New Posting Date</label>
                            <input type="date"
                                   id="posting_date"
                                   name="posting_date"
                                   class="form-control"
                                   value="{{ old('posting_date', optional($bill->posting_date ?: $bill->bill_date)->format('Y-m-d')) }}"
                                   required>
                            <div class="form-text">
                                This updates both the purchase bill posting date and the linked accounting voucher date.
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="posting_date_reason" class="form-label">Reason</label>
                            <textarea id="posting_date_reason"
                                      name="reason"
                                      class="form-control"
                                      rows="3"
                                      maxlength="500"
                                      placeholder="Why is the posting date being corrected?"
                                      required>{{ old('reason') }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Posting Date</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
