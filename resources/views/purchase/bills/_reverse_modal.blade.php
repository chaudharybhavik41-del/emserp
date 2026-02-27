@if(($bill->status ?? null) === 'posted' && empty($bill->reversal_voucher_id) && empty($bill->reversed_at))
    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reversePurchaseBillModal">
        <i class="bi bi-arrow-counterclockwise"></i> Reverse Bill
    </button>

    <div class="modal fade" id="reversePurchaseBillModal" tabindex="-1" aria-labelledby="reversePurchaseBillModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-warning">
                <div class="modal-header bg-warning-subtle">
                    <h5 class="modal-title" id="reversePurchaseBillModalLabel">Reverse Purchase Bill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <form method="POST" action="{{ route('purchase.bills.reverse', $bill) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-warning small mb-3">
                            This will create a <strong>reversal voucher</strong> and mark this bill as <strong>cancelled</strong>.
                            If payments/allocations exist against this bill, reversal will be blocked.
                        </div>

                        <div class="mb-3">
                            <label class="form-label form-label-sm">Reversal Date</label>
                            <input type="date" name="reversal_date" class="form-control form-control-sm"
                                   value="{{ now()->toDateString() }}" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label form-label-sm">Reason (optional)</label>
                            <textarea name="reason" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning btn-sm"
                                onclick="return confirm('Reverse this Purchase Bill? This cannot be undone.')">
                            <i class="bi bi-check2-circle"></i> Confirm Reverse
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
