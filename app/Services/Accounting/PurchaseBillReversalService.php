<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountBillAllocation;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\PurchaseBill;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reverse a Purchase Bill at DOCUMENT level:
 * - Create reversal voucher (swap Dr/Cr of original voucher lines)
 * - Mark bill as cancelled and store reversal linkage
 *
 * This matches design: do not reverse business-doc vouchers directly.
 */
class PurchaseBillReversalService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService
    ) {}

    public function reverseBill(PurchaseBill $bill, string $reversalDate, ?string $reason = null): Voucher
    {
        if (($bill->status ?? null) !== 'posted' || ! $bill->voucher_id) {
            throw new RuntimeException('Only posted purchase bills can be reversed.');
        }

        if (($bill->status ?? null) === 'cancelled' || !empty($bill->reversal_voucher_id) || !empty($bill->reversed_at)) {
            throw new RuntimeException('This purchase bill is already cancelled/reversed.');
        }

        // Block reversal if allocations exist (payments/adjustments posted against this bill)
        $allocated = (float) AccountBillAllocation::query()
            ->join('vouchers', 'vouchers.id', '=', 'account_bill_allocations.voucher_id')
            ->where('account_bill_allocations.bill_type', PurchaseBill::class)
            ->where('account_bill_allocations.bill_id', $bill->id)
            ->where('account_bill_allocations.mode', BillAllocationService::MODE_AGAINST)
            ->where('vouchers.status', 'posted')
            ->sum('account_bill_allocations.amount');

        if ($allocated > 0.009) {
            throw new RuntimeException('Cannot reverse this bill because payments/allocations exist against it.');
        }

        $reason = trim((string)($reason ?? ''));
        $reason = $reason !== '' ? $reason : 'Reversal requested';

        return DB::transaction(function () use ($bill, $reversalDate, $reason) {
            $originalVoucher = Voucher::with('lines')->lockForUpdate()->findOrFail($bill->voucher_id);

            // Create reversal voucher as JOURNAL (safe + balanced)
            $rev = new Voucher();
            $rev->company_id      = $originalVoucher->company_id;
            $rev->voucher_no      = $this->voucherNumberService->next('journal', $originalVoucher->company_id, $reversalDate);
            $rev->voucher_type    = 'journal';
            $rev->voucher_date    = $reversalDate;
            $rev->reference       = 'REV-PB/' . ($bill->bill_number ?? $bill->id);
            $rev->narration       = trim('Reversal of ' . $originalVoucher->voucher_no . ' - ' . $reason);
            $rev->project_id      = $originalVoucher->project_id;
            $rev->cost_center_id  = $originalVoucher->cost_center_id;
            $rev->currency_id     = $originalVoucher->currency_id;
            $rev->exchange_rate   = $originalVoucher->exchange_rate ?? 1;
            $rev->amount_base     = $originalVoucher->amount_base;
            $rev->status          = 'draft';
            $rev->created_by      = Auth::id();
            $rev->save();

            // Swap Dr/Cr for each line
            $lineNo = 1;
            foreach ($originalVoucher->lines as $l) {
                VoucherLine::create([
                    'voucher_id'     => $rev->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $l->account_id,
                    'cost_center_id' => $l->cost_center_id,
                    'description'    => 'Reversal: ' . ($l->description ?? ''),
                    'debit'          => $l->credit,
                    'credit'         => $l->debit,
                    'reference_type' => $l->reference_type,
                    'reference_id'   => $l->reference_id,
                ]);
            }

            // Post (Voucher model validates Dr==Cr)
            $rev->posted_by = Auth::id();
            $rev->posted_at = now();
            $rev->status    = 'posted';
            $rev->save();

            // Mark bill cancelled + link reversal voucher
            $bill->status              = 'cancelled';
            $bill->reversal_voucher_id = $rev->id;
            $bill->reversed_at         = now();
            $bill->reversed_by         = Auth::id();
            $bill->reversal_reason     = $reason;
            $bill->save();

            return $rev;
        });
    }
}
