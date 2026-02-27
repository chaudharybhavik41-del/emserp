<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use App\Models\Party;
use App\Models\SubcontractorRaBill;
use App\Support\MoneyHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DEV-3: Subcontractor RA Bill Posting Service
 *
 * Posting pattern (clean reporting):
 * - Dr Project WIP – Subcontractor (GROSS current amount)
 * - Dr GST Input (if applicable)
 * - Cr Retention Payable (if applicable)
 * - Cr Other Deductions / Recoveries (if applicable)
 * - Cr TDS Payable (if applicable)
 * - Cr Subcontractor Payable (net payable)
 * - Cr Subcontractor Payable (advance recovered) (if applicable)
 *
 * Notes:
 * - We intentionally post WIP as CURRENT (gross) amount to keep project costing correct.
 * - Deductions are posted as separate ledger lines for cleaner reporting.
 * - Advance recovery is posted as an additional credit line to the subcontractor ledger.
 *   This reduces the subcontractor's debit balance (advance) and MUST NOT exceed available advance.
 */
class SubcontractorRaPostingService
{
    public function __construct(
        protected PartyAccountService $partyAccountService,
        protected VoucherNumberService $voucherNumberService
    ) {
    }

    /**
     * Post an approved Subcontractor RA Bill to accounting.
     *
     * @throws RuntimeException
     */
    public function post(SubcontractorRaBill $raBill): Voucher
    {
        // Validate status
        if ($raBill->status === 'posted' && $raBill->voucher_id) {
            throw new RuntimeException('Subcontractor RA Bill is already posted.');
        }

        if ($raBill->status !== 'approved') {
            throw new RuntimeException('Subcontractor RA Bill must be approved before posting.');
        }

        // Validate subcontractor
        $subcontractor = $raBill->subcontractor;
        if (! $subcontractor) {
            throw new RuntimeException('Subcontractor is required on RA Bill.');
        }

        if (! $subcontractor->is_contractor) {
            throw new RuntimeException('Party must be marked as contractor/subcontractor.');
        }

        // Validate project
        $project = $raBill->project;
        if (! $project) {
            throw new RuntimeException('Project is required for Subcontractor RA Bill (per design: Project = Cost Center).');
        }

        // Validate amount
        if ((float) $raBill->current_amount <= 0) {
            throw new RuntimeException('Current RA amount must be greater than zero.');
        }

        $companyId   = (int) ($raBill->company_id ?: Config::get('accounting.default_company_id', 1));
        $voucherDate = $raBill->bill_date;

        // Get or create subcontractor ledger account
        $subcontractorAccount = $this->partyAccountService->syncAccountForParty($subcontractor, $companyId);
        if (! $subcontractorAccount instanceof Account) {
            throw new RuntimeException('Unable to resolve subcontractor ledger account.');
        }

        // Resolve WIP account for subcontractor costs
        $wipAccountCode = (string) Config::get('accounting.subcontractor.project_wip_account_code', 'WIP-SUBCON');
        $wipAccount = Account::where('code', $wipAccountCode)->first();

        if (! $wipAccount) {
            throw new RuntimeException(
                'Project WIP - Subcontractor account not found. Please configure accounting.subcontractor.project_wip_account_code or create account with code: ' . $wipAccountCode
            );
        }

        // Resolve GST Input accounts
        $cgstAccount = $this->resolveGstAccount('cgst_input');
        $sgstAccount = $this->resolveGstAccount('sgst_input');
        $igstAccount = $this->resolveGstAccount('igst_input');

        // Resolve Retention Payable account (separate line for reporting)
        $retentionAccount = null;
        $retentionAmount = (float) ($raBill->retention_amount ?? 0);
        if ($retentionAmount > 0) {
            $retentionCode = (string) Config::get('accounting.subcontractor.retention_payable_account_code', 'RETENTION-PAYABLE');
            $retentionAccount = Account::where('code', $retentionCode)->first();
            if (! $retentionAccount) {
                throw new RuntimeException('Retention Payable account not found for code: ' . $retentionCode . '. Please create the ledger or run migrations.');
            }
        }

        // Resolve Other Deductions / Recoveries account (separate line for reporting)
        $otherDeductionsAccount = null;
        $otherDeductionsAmount = (float) ($raBill->other_deductions ?? 0);
        if ($otherDeductionsAmount > 0) {
            $otherDedCode = (string) Config::get('accounting.subcontractor.other_deductions_account_code', 'SUBCON-DEDUCTIONS');
            $otherDeductionsAccount = Account::where('code', $otherDedCode)->first();
            if (! $otherDeductionsAccount) {
                throw new RuntimeException('Other Deductions account not found for code: ' . $otherDedCode . '. Please create the ledger or run migrations.');
            }
        }

        // Resolve TDS Payable account
        $tdsAccount = null;
        $tdsAmount = (float) ($raBill->tds_amount ?? 0);
        if ($tdsAmount > 0) {
            $tdsAccountCode = (string) Config::get('accounting.tds.tds_payable_account_code', 'TDS-PAYABLE');
            $tdsAccount = Account::where('code', $tdsAccountCode)->first();

            if (! $tdsAccount) {
                throw new RuntimeException('TDS Payable account not found for code: ' . $tdsAccountCode);
            }
        }

        // Amounts
        $currentAmount = (float) $raBill->current_amount;
        $netAmount     = (float) $raBill->net_amount;

        $cgstAmount = (float) $raBill->cgst_amount;
        $sgstAmount = (float) $raBill->sgst_amount;
        $igstAmount = (float) $raBill->igst_amount;

        $advanceRecovery = (float) ($raBill->advance_recovery ?? 0);
        $totalAmount     = (float) $raBill->total_amount;

        $totalGst = $cgstAmount + $sgstAmount + $igstAmount;

        // Guardrails: If GST exists but GST Input ledgers are missing, fail early with a clear message
        if ($cgstAmount > 0 && ! $cgstAccount) {
            throw new RuntimeException('Input CGST account not found. Please configure accounting.gst.input_cgst_account_code and create the ledger (e.g. GST-IN-CGST).');
        }
        if ($sgstAmount > 0 && ! $sgstAccount) {
            throw new RuntimeException('Input SGST account not found. Please configure accounting.gst.input_sgst_account_code and create the ledger (e.g. GST-IN-SGST).');
        }
        if ($igstAmount > 0 && ! $igstAccount) {
            throw new RuntimeException('Input IGST account not found. Please configure accounting.gst.input_igst_account_code and create the ledger (e.g. GST-IN-IGST).');
        }

        // Validate: advance recovery should not exceed available advance (net Dr balance) on subcontractor ledger
        if ($advanceRecovery > 0) {
            $availableAdvancePaise = max(0, $this->netBalancePaise($subcontractorAccount->id, $companyId, $voucherDate));
            $advancePaise = MoneyHelper::toPaise($advanceRecovery);

            if ($advancePaise > $availableAdvancePaise) {
                $available = MoneyHelper::fromPaise($availableAdvancePaise);
                throw new RuntimeException(
                    'Advance recovery exceeds available advance on subcontractor ledger. Available: ₹' .
                    number_format($available, 2, '.', '') .
                    ' | Attempted recovery: ₹' .
                    number_format($advanceRecovery, 2, '.', '')
                );
            }
        }

        // Sanity check: ensure voucher will balance using the designed pattern
        $debitPaise = MoneyHelper::toPaise($currentAmount)
            + MoneyHelper::toPaise($cgstAmount)
            + MoneyHelper::toPaise($sgstAmount)
            + MoneyHelper::toPaise($igstAmount);

        $creditPaise = MoneyHelper::toPaise($totalAmount)
            + MoneyHelper::toPaise($advanceRecovery)
            + MoneyHelper::toPaise($retentionAmount)
            + MoneyHelper::toPaise($otherDeductionsAmount)
            + MoneyHelper::toPaise($tdsAmount);

        if ($debitPaise !== $creditPaise) {
            $diff = MoneyHelper::fromPaise($debitPaise - $creditPaise);
            throw new RuntimeException(
                'Posting would create an unbalanced voucher. Please re-check bill totals. Difference (Dr - Cr): ' . $diff
            );
        }

        return DB::transaction(function () use (
            $raBill,
            $companyId,
            $voucherDate,
            $subcontractorAccount,
            $wipAccount,
            $cgstAccount,
            $sgstAccount,
            $igstAccount,
            $retentionAccount,
            $otherDeductionsAccount,
            $tdsAccount,
            $currentAmount,
            $netAmount,
            $cgstAmount,
            $sgstAmount,
            $igstAmount,
            $retentionAmount,
            $otherDeductionsAmount,
            $tdsAmount,
            $advanceRecovery,
            $totalAmount,
            $project
        ) {
            // Resolve project cost center (Project = Cost Center)
            $costCenterId = ProjectCostCenterResolver::resolveId($companyId, (int) $project->id);

            // Create voucher
            $voucher = new Voucher();
            $voucher->company_id = $companyId;
            // Centralised voucher numbering (Phase 5a)
            $voucher->voucher_no = $this->voucherNumberService->next('subcontractor_ra', $companyId, $voucherDate);
            $voucher->voucher_type = 'subcontractor_ra';
            $voucher->voucher_date = $voucherDate;
            $voucher->reference = $raBill->ra_number;

            // Narration
            $narrParts = array_filter([
                'Subcontractor RA Bill ' . $raBill->ra_number,
                ($raBill->subcontractor->name ?? ''),
                ($raBill->bill_number ? ('Bill# ' . $raBill->bill_number) : ''),
                (trim((string) ($raBill->remarks ?? ''))),
            ]);

            $voucher->narration = trim(implode(' - ', $narrParts));
            $voucher->project_id = $project->id;
            $voucher->cost_center_id = $costCenterId;
            $voucher->currency_id = null;
            $voucher->exchange_rate = 1;

            // IMPORTANT (Phase 5b): create voucher as DRAFT, insert lines, then POST.
            $voucher->status = 'draft';
            $voucher->created_by = $raBill->created_by;

            // Total voucher amount (sum of debits = sum of credits)
            $totalDebit = $currentAmount + $cgstAmount + $sgstAmount + $igstAmount;
            $voucher->amount_base = round($totalDebit, 2);
            $voucher->save();

            $lineNo = 1;

            // 1) Dr Project WIP - Subcontractor (GROSS current amount)
            if ($currentAmount > 0) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $wipAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'Subcontractor Work (Gross) - ' . $raBill->ra_number,
                    'debit'          => round($currentAmount, 2),
                    'credit'         => 0,
                    'reference_type' => SubcontractorRaBill::class,
                    'reference_id'   => $raBill->id,
                ]);
            }

            // 2) Dr GST Input accounts
            if ($cgstAmount > 0 && $cgstAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $cgstAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'Input CGST - ' . $raBill->ra_number,
                    'debit'          => round($cgstAmount, 2),
                    'credit'         => 0,
                    'reference_type' => SubcontractorRaBill::class,
                    'reference_id'   => $raBill->id,
                ]);
            }

            if ($sgstAmount > 0 && $sgstAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $sgstAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'Input SGST - ' . $raBill->ra_number,
                    'debit'          => round($sgstAmount, 2),
                    'credit'         => 0,
                    'reference_type' => SubcontractorRaBill::class,
                    'reference_id'   => $raBill->id,
                ]);
            }

            if ($igstAmount > 0 && $igstAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $igstAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'Input IGST - ' . $raBill->ra_number,
                    'debit'          => round($igstAmount, 2),
                    'credit'         => 0,
                    'reference_type' => SubcontractorRaBill::class,
                    'reference_id'   => $raBill->id,
                ]);
            }

            // 3) Cr Retention Payable (separate line)
            if ($retentionAmount > 0 && $retentionAccount) {
                $retPct = (float) ($raBill->retention_percent ?? 0);
                $retLabel = $retPct > 0 ? ('Retention @ ' . rtrim(rtrim(number_format($retPct, 2, '.', ''), '0'), '.') . '%') : 'Retention';

                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $retentionAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => $retLabel . ' - ' . $raBill->ra_number,
                    'debit'          => 0,
                    'credit'         => round($retentionAmount, 2),
                    'reference_type' => SubcontractorRaBill::class,
                    'reference_id'   => $raBill->id,
                ]);
            }

            // 4) Cr Other Deductions / Recoveries (separate line)
            if ($otherDeductionsAmount > 0 && $otherDeductionsAccount) {
                $remark = trim((string) ($raBill->deduction_remarks ?? ''));
                $desc = 'Other Deductions - ' . $raBill->ra_number;
                if ($remark !== '') {
                    $desc .= ' | ' . mb_substr($remark, 0, 180);
                }

                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $otherDeductionsAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => $desc,
                    'debit'          => 0,
                    'credit'         => round($otherDeductionsAmount, 2),
                    'reference_type' => SubcontractorRaBill::class,
                    'reference_id'   => $raBill->id,
                ]);
            }

            // 5) Cr TDS Payable (separate line)
            if ($tdsAmount > 0 && $tdsAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $tdsAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'TDS Payable ' . ($raBill->tds_section ?? '') . ' - ' . $raBill->ra_number,
                    'debit'          => 0,
                    'credit'         => round($tdsAmount, 2),
                    'reference_type' => SubcontractorRaBill::class,
                    'reference_id'   => $raBill->id,
                ]);
            }

            // 6) Cr Subcontractor Payable (net payable)
            VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => $lineNo++,
                'account_id'     => $subcontractorAccount->id,
                'cost_center_id' => $costCenterId,
                'description'    => 'Subcontractor Payable (Net) - ' . $raBill->ra_number,
                'debit'          => 0,
                'credit'         => round($totalAmount, 2),
                'reference_type' => SubcontractorRaBill::class,
                'reference_id'   => $raBill->id,
            ]);

            // 7) Cr Subcontractor Advance Recovery (additional credit on the party ledger)
            if ($advanceRecovery > 0) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $subcontractorAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'Advance Recovery - ' . $raBill->ra_number,
                    'debit'          => 0,
                    'credit'         => round($advanceRecovery, 2),
                    'reference_type' => SubcontractorRaBill::class,
                    'reference_id'   => $raBill->id,
                ]);
            }

            // Finalize posting (validates balance + normalizes amount_base)
            $voucher->posted_by = Auth::id();
            $voucher->posted_at = now();
            $voucher->status    = 'posted';
            $voucher->save();

            // Update RA Bill status and link voucher
            $raBill->voucher_id = $voucher->id;
            $raBill->status = 'posted';
            $raBill->save();

            // Audit log
            ActivityLog::logCustom(
                'posted_to_accounts',
                'Subcontractor RA Bill ' . $raBill->ra_number . ' posted to accounts as voucher ' . $voucher->voucher_no,
                $raBill,
                [
                    'voucher_id'       => $voucher->id,
                    'voucher_no'       => $voucher->voucher_no,
                    'project_id'       => $project->id,
                    'current_amount'   => $currentAmount,
                    'net_amount'       => $netAmount,
                    'retention_amount' => $retentionAmount,
                    'advance_recovery' => $advanceRecovery,
                    'other_deductions' => $otherDeductionsAmount,
                    'total_gst'        => $cgstAmount + $sgstAmount + $igstAmount,
                    'tds_amount'       => $tdsAmount,
                    'total_amount'     => $totalAmount,
                ]
            );

            return $voucher;
        });
    }

    /**
     * Reverse a posted RA Bill (create reversal voucher)
     */
    public function reverse(SubcontractorRaBill $raBill, string $reason = ''): Voucher
    {
        if ($raBill->status !== 'posted' || ! $raBill->voucher_id) {
            throw new RuntimeException('Only posted RA Bills can be reversed.');
        }

        $originalVoucher = $raBill->voucher;
        if (! $originalVoucher) {
            throw new RuntimeException('Original voucher not found.');
        }

        return DB::transaction(function () use ($raBill, $originalVoucher, $reason) {
            $companyId = $raBill->company_id;
            $voucherDate = now();

            // Create reversal voucher
            $reversalVoucher = new Voucher();
            $reversalVoucher->company_id = $companyId;
            // Centralised reversal numbering (Phase 5a)
            $reversalVoucher->voucher_no = $this->voucherNumberService->next('subcontractor_ra_reversal', (int) $companyId, $voucherDate);
            $reversalVoucher->voucher_type = 'subcontractor_ra_reversal';
            $reversalVoucher->voucher_date = $voucherDate;
            $reversalVoucher->reference = 'REV-' . $raBill->ra_number;
            $reversalVoucher->narration = trim('Reversal of ' . $originalVoucher->voucher_no . ' - ' . $reason);
            $reversalVoucher->project_id = $raBill->project_id;

            // IMPORTANT (Phase 5b): create voucher as DRAFT, insert lines, then POST.
            // This allows the Voucher model guardrail to validate Dr == Cr at posting time.
            $reversalVoucher->status = 'draft';
            $reversalVoucher->created_by = Auth::id();
            $reversalVoucher->amount_base = $originalVoucher->amount_base;
            $reversalVoucher->save();

            // Reverse all lines (swap debit/credit)
            $lineNo = 1;
            foreach ($originalVoucher->lines as $originalLine) {
                VoucherLine::create([
                    'voucher_id'     => $reversalVoucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $originalLine->account_id,
                    'cost_center_id' => $originalLine->cost_center_id,
                    'description'    => 'Reversal: ' . $originalLine->description,
                    'debit'          => $originalLine->credit, // Swap
                    'credit'         => $originalLine->debit,  // Swap
                    'reference_type' => $originalLine->reference_type,
                    'reference_id'   => $originalLine->reference_id,
                ]);
            }

            // Finalize posting (validates balance + normalizes amount_base)
            $reversalVoucher->posted_by = Auth::id();
            $reversalVoucher->posted_at = now();
            $reversalVoucher->status    = 'posted';
            $reversalVoucher->save();

            // Update RA Bill status
            $raBill->status = 'approved'; // Back to approved, can be re-posted
            $raBill->voucher_id = null;
            $raBill->save();

            // Audit log
            ActivityLog::logCustom(
                'posting_reversed',
                'Subcontractor RA Bill ' . $raBill->ra_number . ' posting reversed. Reversal voucher: ' . $reversalVoucher->voucher_no,
                $raBill,
                [
                    'original_voucher_id' => $originalVoucher->id,
                    'reversal_voucher_id' => $reversalVoucher->id,
                    'reason' => $reason,
                ]
            );

            return $reversalVoucher;
        });
    }

    /**
     * Resolve GST account by type
     */
    protected function resolveGstAccount(string $type): ?Account
    {
        // Canonical keys (config/accounting.php):
        // - accounting.gst.input_cgst_account_code
        // - accounting.gst.input_sgst_account_code
        // - accounting.gst.input_igst_account_code
        //
        // Backward compatibility:
        // - also try legacy keys (cgst_input_account_code, etc.) and older fallback codes.
        $codeCandidates = [];

        switch ($type) {
            case 'cgst_input':
                $codeCandidates = [
                    Config::get('accounting.gst.input_cgst_account_code'),
                    Config::get('accounting.gst.cgst_input_account_code'), // legacy
                    'GST-IN-CGST',
                    'GST-CGST-INPUT',
                ];
                break;

            case 'sgst_input':
                $codeCandidates = [
                    Config::get('accounting.gst.input_sgst_account_code'),
                    Config::get('accounting.gst.sgst_input_account_code'), // legacy
                    'GST-IN-SGST',
                    'GST-SGST-INPUT',
                ];
                break;

            case 'igst_input':
                $codeCandidates = [
                    Config::get('accounting.gst.input_igst_account_code'),
                    Config::get('accounting.gst.igst_input_account_code'), // legacy
                    'GST-IN-IGST',
                    'GST-IGST-INPUT',
                ];
                break;
        }

        foreach ($codeCandidates as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }

            $account = Account::where('code', $code)->first();
            if ($account) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Net balance for an account up to a date (Dr - Cr) in paise.
     *
     * Positive = net DR balance (advance)
     * Negative = net CR balance (payable)
     */
    protected function netBalancePaise(int $accountId, int $companyId, $asOfDate): int
    {
        $row = VoucherLine::query()
            ->join('vouchers as v', 'v.id', '=', 'voucher_lines.voucher_id')
            ->where('voucher_lines.account_id', $accountId)
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereDate('v.voucher_date', '<=', $asOfDate)
            ->selectRaw('COALESCE(SUM(voucher_lines.debit),0) as debit_total, COALESCE(SUM(voucher_lines.credit),0) as credit_total')
            ->first();

        $dr = MoneyHelper::toPaise($row?->debit_total ?? 0);
        $cr = MoneyHelper::toPaise($row?->credit_total ?? 0);

        return $dr - $cr;
    }

    // NOTE: voucher numbering is centralised in VoucherNumberService (Phase 5a)
}
