<?php

namespace App\Services\Accounting;

use App\Models\ActivityLog;
use App\Models\Accounting\AccountBillAllocation;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Voucher Reversal Service (Phase 6)
 *
 * Purpose:
 * - Provide a SAFE way to correct mistakes on posted manual vouchers.
 * - Instead of editing/deleting posted vouchers, we create a reversal voucher
 *   with opposite Dr/Cr lines.
 *
 * Important guardrails:
 * - Only posted vouchers can be reversed.
 * - Only specific voucher types are allowed (default: journal/contra).
 * - Vouchers with bill allocations are NOT auto-reversed here (payments/receipts)
 *   because allocations would also need reversal.
 */
class VoucherReversalService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService
    ) {
    }

    /**
     * Reverse a posted voucher by creating an opposite-entry voucher.
     */
    public function reverse(
        Voucher $original,
        Carbon|string $reversalDate,
        ?string $reason = null,
        ?int $userId = null
    ): Voucher {
        $reversalDate = $reversalDate instanceof Carbon
            ? $reversalDate
            : Carbon::parse($reversalDate);

        $userId = $userId ?? auth()->id();

        if (! $original->isPosted()) {
            throw new RuntimeException('Only posted vouchers can be reversed.');
        }

        if ($original->reversal_voucher_id) {
            throw new RuntimeException('This voucher is already reversed.');
        }

        if ($original->reversal_of_voucher_id) {
            throw new RuntimeException('This voucher is a reversal voucher and cannot be reversed again.');
        }

        // Keep the allowed scope narrow for safety.
        $allowedTypes = ['journal', 'contra'];
        if (! in_array((string) $original->voucher_type, $allowedTypes, true)) {
            throw new RuntimeException('Auto-reversal is allowed only for: ' . implode(', ', $allowedTypes) . '.');
        }

        // Payments/receipts often have allocations; do not auto-reverse.
        if (AccountBillAllocation::where('voucher_id', $original->id)->exists()) {
            throw new RuntimeException('This voucher has bill allocations. Auto-reversal is not supported. Please create a correction journal entry.');
        }

        $original->load(['lines']);

        if ($original->lines->isEmpty()) {
            throw new RuntimeException('Cannot reverse a voucher without lines.');
        }

        return DB::transaction(function () use ($original, $reversalDate, $reason, $userId) {
            $companyId = (int) $original->company_id;

            // Create reversal voucher as DRAFT first, insert lines, then POST.
            $reversal = new Voucher();
            $reversal->company_id   = $companyId;
            $reversal->voucher_type = (string) $original->voucher_type;
            $reversal->voucher_date = $reversalDate;

            // Use the same series key as the original type (journal/contra).
            $reversal->voucher_no   = $this->voucherNumberService->next((string) $original->voucher_type, $companyId, $reversalDate);

            $reversal->reference    = 'REV:' . $original->voucher_no;
            $reversal->narration    = $reason
                ? ('Reversal of ' . $original->voucher_no . ' - ' . $reason)
                : ('Reversal of voucher ' . $original->voucher_no);

            $reversal->project_id     = $original->project_id;
            $reversal->cost_center_id = $original->cost_center_id;
            $reversal->currency_id    = $original->currency_id;
            $reversal->exchange_rate  = $original->exchange_rate ?? 1;
            $reversal->amount_base    = $original->amount_base ?? 0;
            $reversal->status         = 'draft';
            $reversal->created_by     = $userId;

            // Link to the original
            $reversal->reversal_of_voucher_id = $original->id;
            $reversal->save();

            $lineNo = 1;
            foreach ($original->lines->sortBy('line_no') as $line) {
                VoucherLine::create([
                    'voucher_id'     => $reversal->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $line->account_id,
                    'cost_center_id' => $line->cost_center_id,
                    'description'    => 'Reversal: ' . ($line->description ?: ('Line ' . $line->line_no)),
                    // Reverse Dr/Cr
                    'debit'          => (float) $line->credit,
                    'credit'         => (float) $line->debit,
                    // Do NOT copy references/allocations into reversal
                    'reference_type' => null,
                    'reference_id'   => null,
                ]);
            }

            // Post reversal voucher
            $reversal->posted_by = $userId;
            $reversal->posted_at = now();
            $reversal->status    = 'posted';
            $reversal->save();

            // Mark original as reversed
            $original->reversal_voucher_id = $reversal->id;
            $original->reversed_by         = $userId;
            $original->reversed_at         = now();
            $original->reversal_reason     = $reason;
            $original->save();

            // Audit logs
            ActivityLog::logCustom(
                'voucher_reversed',
                'Voucher ' . $original->voucher_no . ' reversed by voucher ' . $reversal->voucher_no,
                $original,
                [
                    'original_voucher_id' => $original->id,
                    'original_voucher_no' => $original->voucher_no,
                    'reversal_voucher_id' => $reversal->id,
                    'reversal_voucher_no' => $reversal->voucher_no,
                    'reason'              => $reason,
                ]
            );

            ActivityLog::logCustom(
                'voucher_created',
                'Created reversal voucher ' . $reversal->voucher_no . ' for ' . $original->voucher_no,
                $reversal,
                [
                    'reversal_of_voucher_id' => $original->id,
                    'reversal_of_voucher_no' => $original->voucher_no,
                    'reason'                 => $reason,
                ]
            );

            return $reversal;
        });
    }
}
