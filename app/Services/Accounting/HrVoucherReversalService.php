<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountBillAllocation;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HrVoucherReversalService
{
    public function __construct(
        protected VoucherReversalService $voucherReversalService,
        protected VoucherNumberService $voucherNumberService,
        protected AccountingDateControlService $accountingDateControlService
    ) {
    }

    public function reverse(
        Voucher $original,
        Carbon|string $reversalDate,
        ?string $reason = null,
        ?int $userId = null
    ): Voucher {
        $type = (string) $original->voucher_type;

        if (in_array($type, ['journal', 'contra'], true)) {
            return $this->voucherReversalService->reverse($original, $reversalDate, $reason, $userId);
        }

        if (! in_array($type, ['payment', 'receipt'], true)) {
            throw new RuntimeException('HR voucher auto-reversal is not supported for voucher type: ' . $type);
        }

        return $this->reverseAsJournal($original, $reversalDate, $reason, $userId);
    }

    protected function reverseAsJournal(
        Voucher $original,
        Carbon|string $reversalDate,
        ?string $reason = null,
        ?int $userId = null
    ): Voucher {
        $reversalDate = $reversalDate instanceof Carbon
            ? $reversalDate
            : Carbon::parse((string) $reversalDate);

        $userId = $userId ?? auth()->id();
        $companyId = (int) $original->company_id;

        if (! $original->isPosted()) {
            throw new RuntimeException('Only posted vouchers can be reversed.');
        }

        if ($original->reversal_voucher_id) {
            throw new RuntimeException('This voucher is already reversed.');
        }

        if ($original->reversal_of_voucher_id) {
            throw new RuntimeException('This voucher is a reversal voucher and cannot be reversed again.');
        }

        if (AccountBillAllocation::query()->where('voucher_id', $original->id)->exists()) {
            throw new RuntimeException('This voucher has bill allocations. Auto-reversal is not supported.');
        }

        $original->loadMissing('lines');
        if ($original->lines->isEmpty()) {
            throw new RuntimeException('Cannot reverse a voucher without lines.');
        }

        $originalVoucherDate = $original->voucher_date
            ? Carbon::parse((string) $original->voucher_date)->startOfDay()
            : null;

        if ($originalVoucherDate && $reversalDate->copy()->startOfDay()->lt($originalVoucherDate)) {
            throw new RuntimeException('Reversal date cannot be earlier than the original voucher date.');
        }

        $this->accountingDateControlService->assertDateOpenForRuntime(
            $reversalDate,
            $companyId,
            'Reversal date'
        );

        return DB::transaction(function () use ($original, $reversalDate, $reason, $userId, $companyId): Voucher {
            $reversal = new Voucher();
            $reversal->company_id = $companyId;
            $reversal->voucher_type = 'journal';
            $reversal->voucher_date = $reversalDate->toDateString();
            $reversal->voucher_no = $this->voucherNumberService->next('journal', $companyId, $reversalDate);
            $reversal->reference = 'REV:' . $original->voucher_no;
            $reversal->narration = $reason
                ? ('Reversal of ' . $original->voucher_no . ' - ' . $reason)
                : ('Reversal of voucher ' . $original->voucher_no);
            $reversal->project_id = $original->project_id;
            $reversal->cost_center_id = $original->cost_center_id;
            $reversal->currency_id = $original->currency_id;
            $reversal->exchange_rate = $original->exchange_rate ?? 1;
            $reversal->amount_base = $original->amount_base ?? 0;
            $reversal->status = 'draft';
            $reversal->created_by = $userId;
            $reversal->reversal_of_voucher_id = $original->id;
            $reversal->save();

            $lineNo = 1;
            foreach ($original->lines->sortBy('line_no') as $line) {
                VoucherLine::create([
                    'voucher_id' => $reversal->id,
                    'line_no' => $lineNo++,
                    'account_id' => $line->account_id,
                    'cost_center_id' => $line->cost_center_id,
                    'description' => 'Reversal: ' . ($line->description ?: ('Line ' . $line->line_no)),
                    'debit' => (float) $line->credit,
                    'credit' => (float) $line->debit,
                    'reference_type' => null,
                    'reference_id' => null,
                ]);
            }

            $reversal->posted_by = $userId;
            $reversal->posted_at = now();
            $reversal->status = 'posted';
            $reversal->save();

            $original->reversal_voucher_id = $reversal->id;
            $original->reversed_by = $userId;
            $original->reversed_at = now();
            $original->reversal_reason = $reason;
            $original->save();

            ActivityLog::logCustom(
                'voucher_reversed',
                'Voucher ' . $original->voucher_no . ' reversed by voucher ' . $reversal->voucher_no,
                $original,
                [
                    'original_voucher_id' => $original->id,
                    'original_voucher_no' => $original->voucher_no,
                    'reversal_voucher_id' => $reversal->id,
                    'reversal_voucher_no' => $reversal->voucher_no,
                    'reason' => $reason,
                ]
            );

            ActivityLog::logCustom(
                'voucher_created',
                'Created reversal voucher ' . $reversal->voucher_no . ' for ' . $original->voucher_no,
                $reversal,
                [
                    'reversal_of_voucher_id' => $original->id,
                    'reversal_of_voucher_no' => $original->voucher_no,
                    'reason' => $reason,
                ]
            );

            return $reversal;
        });
    }
}
