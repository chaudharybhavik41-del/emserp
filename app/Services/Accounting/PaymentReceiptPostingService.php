<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentReceiptPostingService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService
    ) {
    }

    /**
     * Create a payment voucher (cash/bank outflow).
     */
    public function createPayment(array $data): Voucher
    {
        return $this->createVoucher($data, 'payment');
    }

    /**
     * Create a receipt voucher (cash/bank inflow).
     */
    public function createReceipt(array $data): Voucher
    {
        return $this->createVoucher($data, 'receipt');
    }

    /**
     * Core builder for payment / receipt vouchers.
     *
     * @param  array  $data  Validated payload from form / caller.
     * @param  string $type  'payment' or 'receipt'
     */
    protected function createVoucher(array $data, string $type): Voucher
    {
        if (! in_array($type, ['payment', 'receipt'], true)) {
            throw new RuntimeException('Unsupported voucher type: ' . $type);
        }

        $companyId = (int) ($data['company_id'] ?? Config::get('accounting.default_company_id', 1));

        $bankAccountId = (int) ($data['bank_account_id'] ?? 0);
        if (! $bankAccountId) {
            throw new RuntimeException('Bank / cash account is required.');
        }

        /** @var Account|null $bankAccount */
        $bankAccount = Account::find($bankAccountId);
        if (! $bankAccount) {
            throw new RuntimeException('Bank / cash account not found.');
        }

        $rawLines = $data['lines'] ?? [];
        $lines    = [];
        $total    = 0.0;

        foreach ($rawLines as $row) {
            $accountId = (int) ($row['account_id'] ?? 0);
            $amount    = isset($row['amount']) ? (float) $row['amount'] : 0.0;

            if (! $accountId || $amount <= 0) {
                continue;
            }

            $lines[] = [
                'account_id'     => $accountId,
                'amount'         => round($amount, 2),
                'description'    => $row['description'] ?? null,
                'cost_center_id' => $row['cost_center_id'] ?? null,
                'reference_type' => $row['reference_type'] ?? null,
                'reference_id'   => $row['reference_id'] ?? null,
            ];

            $total += $amount;
        }

        if (empty($lines)) {
            throw new RuntimeException('Please enter at least one line with account and amount.');
        }

        if ($total <= 0) {
            throw new RuntimeException('Total amount must be greater than zero.');
        }

        $voucherDate = $data['voucher_date'] ?? now()->toDateString();
        $voucherDate = Carbon::parse($voucherDate);

        $projectId    = $data['project_id'] ?? null;
        $costCenterId = $data['cost_center_id'] ?? null;
        $currencyId   = $data['currency_id'] ?? null;
        $narration    = trim((string) ($data['narration'] ?? ''));
        $reference    = $data['reference'] ?? null;
        $createdBy    = $data['created_by'] ?? null;

        return DB::transaction(function () use (
            $companyId,
            $type,
            $voucherDate,
            $projectId,
            $costCenterId,
            $currencyId,
            $narration,
            $reference,
            $createdBy,
            $bankAccount,
            $lines,
            $total
        ): Voucher {
            $voucher = new Voucher();
            $voucher->company_id     = $companyId;
            $voucher->project_id     = $projectId;
            $voucher->cost_center_id = $costCenterId;
            $voucher->voucher_date   = $voucherDate->toDateString();
            $voucher->voucher_type   = $type;
            // Centralised voucher numbering (Phase 5a)
            $voucher->voucher_no     = $this->voucherNumberService->next($type, (int) $companyId, $voucherDate);
            $voucher->currency_id    = $currencyId;
            $voucher->exchange_rate  = 1;
            $voucher->amount_base    = round($total, 2);
            // IMPORTANT (Phase 5b): create voucher as DRAFT, insert lines, then POST.
            // This allows the Voucher model guardrail to validate Dr == Cr at posting time.
            $voucher->status         = 'draft';
            $voucher->narration      = $narration;
            $voucher->reference      = $reference;
            $voucher->created_by     = $createdBy;
            $voucher->save();

            $lineNo = 1;

            if ($type === 'payment') {
                // Payment: Dr party / expense, Cr bank
                foreach ($lines as $line) {
                    VoucherLine::create([
                        'voucher_id'     => $voucher->id,
                        'line_no'        => $lineNo++,
                        'account_id'     => $line['account_id'],
                        'cost_center_id' => $line['cost_center_id'],
                        'description'    => $line['description'],
                        'debit'          => $line['amount'],
                        'credit'         => 0,
                        'reference_type' => $line['reference_type'],
                        'reference_id'   => $line['reference_id'],
                    ]);
                }

                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $bankAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => $narration ?: ('Payment via ' . $bankAccount->name),
                    'debit'          => 0,
                    'credit'         => round($total, 2),
                    'reference_type' => null,
                    'reference_id'   => null,
                ]);
            } else {
                // Receipt: Dr bank, Cr party / income
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $bankAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => $narration ?: ('Receipt into ' . $bankAccount->name),
                    'debit'          => round($total, 2),
                    'credit'         => 0,
                    'reference_type' => null,
                    'reference_id'   => null,
                ]);

                foreach ($lines as $line) {
                    VoucherLine::create([
                        'voucher_id'     => $voucher->id,
                        'line_no'        => $lineNo++,
                        'account_id'     => $line['account_id'],
                        'cost_center_id' => $line['cost_center_id'],
                        'description'    => $line['description'],
                        'debit'          => 0,
                        'credit'         => $line['amount'],
                        'reference_type' => $line['reference_type'],
                        'reference_id'   => $line['reference_id'],
                    ]);
                }
            }

            // Finalize posting (validates balance + normalizes amount_base)
            $voucher->posted_by = $createdBy ?? auth()->id();
            $voucher->posted_at = now();
            $voucher->status    = 'posted';
            $voucher->save();

            return $voucher;
        });
    }

    // NOTE: voucher numbering is centralised in VoucherNumberService (Phase 5a)
}
