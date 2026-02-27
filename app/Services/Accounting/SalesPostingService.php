<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use App\Models\ClientRaBill;
use App\Models\Party;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DEV-4: Sales / Client RA Bill Posting Service
 * 
 * Per Development Plan v1.2:
 * - Post Client RA Bills / Sales invoices to accounts
 * - Dr Sundry Debtor
 * - Cr Fabrication Revenue (or other revenue ledgers)
 * - Cr Output GST (CGST/SGST/IGST via tax config)
 * 
 * WIP → COGS:
 * - For Phase 1: keep WIP until project completion
 * - Use manual JV for WIP → COGS as per design doc
 */
class SalesPostingService
{
    public function __construct(
        protected PartyAccountService $partyAccountService,
        protected VoucherNumberService $voucherNumberService
    ) {
    }

    /**
     * Post an approved Client RA Bill to accounting.
     *
     * @throws RuntimeException
     */
    public function post(ClientRaBill $raBill): Voucher
    {
        // Validate status
        if ($raBill->status === 'posted' && $raBill->voucher_id) {
            throw new RuntimeException('Client RA Bill is already posted.');
        }

        if ($raBill->status !== 'approved') {
            throw new RuntimeException('Client RA Bill must be approved before posting.');
        }

        // Validate client
        $client = $raBill->client;
        if (!$client) {
            throw new RuntimeException('Client is required on RA Bill.');
        }

        if (!$client->is_client) {
            throw new RuntimeException('Party must be marked as client.');
        }

        // Validate project
        $project = $raBill->project;
        if (!$project) {
            throw new RuntimeException('Project is required for Client RA Bill.');
        }

        // Validate amount
        if ($raBill->current_amount <= 0) {
            throw new RuntimeException('Current RA amount must be greater than zero.');
        }

        // Get or create client debtor ledger account
        $debtorAccount = $this->partyAccountService->syncAccountForParty(
            $client,
            (int) ($raBill->company_id ?: Config::get('accounting.default_company_id', 1))
        );
        if (!$debtorAccount instanceof Account) {
            throw new RuntimeException('Unable to resolve client debtor ledger account.');
        }

        // Resolve revenue accounts based on lines or default
        $revenueAccounts = $this->resolveRevenueAccounts($raBill);
        if (empty($revenueAccounts)) {
            throw new RuntimeException('No revenue accounts found. Please configure accounting.sales.revenue_account_codes or assign revenue accounts to line items.');
        }

        // Resolve Output GST accounts
        $cgstOutputAccount = $this->resolveGstAccount('cgst_output');
        $sgstOutputAccount = $this->resolveGstAccount('sgst_output');
        $igstOutputAccount = $this->resolveGstAccount('igst_output');

        // Calculate amounts
        $netAmount = (float) $raBill->net_amount;
        $cgstAmount = (float) $raBill->cgst_amount;
        $sgstAmount = (float) $raBill->sgst_amount;
        $igstAmount = (float) $raBill->igst_amount;
        $totalAmount = (float) $raBill->total_amount;

        // TDS on sales (deducted by client): split receivable into
        // - Net receivable from client
        // - TDS Receivable (asset) to be claimed via Form 26AS / 16A
        $tdsAmount = (float) ($raBill->tds_amount ?? 0);
        $receivableAmount = (float) ($raBill->receivable_amount ?? 0);

        if ($receivableAmount <= 0 && $totalAmount > 0) {
            $receivableAmount = $totalAmount - $tdsAmount;
        }

        $tdsReceivableAccount = null;
        if ($tdsAmount > 0) {
            $tdsReceivableAccount = $this->resolveTdsReceivableAccount();
            if (! $tdsReceivableAccount) {
                throw new RuntimeException('TDS Receivable account not found. Please configure accounting.tds.tds_receivable_account_code and create that ledger.');
            }
        }

        return DB::transaction(function () use (
            $raBill,
            $debtorAccount,
            $revenueAccounts,
            $cgstOutputAccount,
            $sgstOutputAccount,
            $igstOutputAccount,
            $netAmount,
            $cgstAmount,
            $sgstAmount,
            $igstAmount,
            $tdsAmount,
            $receivableAmount,
            $tdsReceivableAccount,
            $totalAmount,
            $project
        ) {
            $companyId = $raBill->company_id ?: Config::get('accounting.default_company_id', 1);
            $voucherDate = $raBill->bill_date;

            // Resolve project cost center (Project = Cost Center)
            $costCenterId = ProjectCostCenterResolver::resolveId($companyId, (int) $project->id);

            // Create voucher
            $voucher = new Voucher();
            $voucher->company_id = $companyId;
            // Centralised voucher numbering (Phase 5a)
            $voucher->voucher_no = $this->voucherNumberService->next('sales', $companyId, $voucherDate);
            $voucher->voucher_type = 'sales';
            $voucher->voucher_date = $voucherDate;
            $voucher->reference = $raBill->invoice_number ?: $raBill->ra_number;
            $voucher->narration = trim(
                'Client RA Bill ' . $raBill->ra_number .
                ($raBill->invoice_number ? ' / Inv: ' . $raBill->invoice_number : '') .
                ' - ' . ($raBill->client->name ?? '') .
                ' - ' . ($raBill->remarks ?? '')
            );
            $voucher->project_id = $project->id;
            $voucher->cost_center_id = $costCenterId;
            $voucher->currency_id = null;
            $voucher->exchange_rate = 1;
            // IMPORTANT (Phase 5b): create voucher as DRAFT, insert lines, then POST.
            // This allows the Voucher model guardrail to validate Dr == Cr at posting time.
            $voucher->status = 'draft';
            $voucher->created_by = $raBill->created_by;

            // Total voucher amount (provisional; will be normalized from voucher lines when posted)
            $voucher->amount_base = round($totalAmount, 2);
            $voucher->save();

            $lineNo = 1;

            // 1) Dr Sundry Debtor (Client) for NET receivable (Total - TDS)
            VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => $lineNo++,
                'account_id'     => $debtorAccount->id,
                'cost_center_id' => $costCenterId,
                'description'    => 'Debtor - ' . ($raBill->invoice_number ?: $raBill->ra_number),
                'debit'          => round($receivableAmount, 2),
                'credit'         => 0,
                'reference_type' => ClientRaBill::class,
                'reference_id'   => $raBill->id,
            ]);

            // 1b) Dr TDS Receivable (if applicable)
            if ($tdsAmount > 0 && $tdsReceivableAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $tdsReceivableAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'TDS Receivable ' . (($raBill->tds_section ?? '') ? ($raBill->tds_section . ' - ') : '') . ($raBill->invoice_number ?: $raBill->ra_number),
                    'debit'          => round($tdsAmount, 2),
                    'credit'         => 0,
                    'reference_type' => ClientRaBill::class,
                    'reference_id'   => $raBill->id,
                ]);
            }

            // 2) Cr Revenue accounts (grouped by account)
            foreach ($revenueAccounts as $accountId => $amount) {
                if ($amount <= 0) {
                    continue;
                }

                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $accountId,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'Revenue - ' . ($raBill->invoice_number ?: $raBill->ra_number),
                    'debit'          => 0,
                    'credit'         => round($amount, 2),
                    'reference_type' => ClientRaBill::class,
                    'reference_id'   => $raBill->id,
                ]);
            }

            // 3) Cr Output GST accounts
            if ($cgstAmount > 0 && $cgstOutputAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $cgstOutputAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'Output CGST - ' . ($raBill->invoice_number ?: $raBill->ra_number),
                    'debit'          => 0,
                    'credit'         => round($cgstAmount, 2),
                ]);
            }

            if ($sgstAmount > 0 && $sgstOutputAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $sgstOutputAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'Output SGST - ' . ($raBill->invoice_number ?: $raBill->ra_number),
                    'debit'          => 0,
                    'credit'         => round($sgstAmount, 2),
                ]);
            }

            if ($igstAmount > 0 && $igstOutputAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $igstOutputAccount->id,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'Output IGST - ' . ($raBill->invoice_number ?: $raBill->ra_number),
                    'debit'          => 0,
                    'credit'         => round($igstAmount, 2),
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
            
            // Generate invoice number if not already set
            if (empty($raBill->invoice_number)) {
                // Concurrency-safe-ish approach:
                // - invoice_number has a unique index (company_id + invoice_number)
                // - in rare cases of parallel posting, we retry on duplicate key
                $maxAttempts = 5;
                $saved = false;

                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    $raBill->invoice_number = ClientRaBill::generateNextInvoiceNumber($companyId);

                    try {
                        $raBill->save();
                        $saved = true;
                        break;
                    } catch (QueryException $e) {
                        $msg = $e->getMessage();

                        // MySQL duplicate key message typically contains "Duplicate entry".
                        if (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'client_ra_unique_invoice')) {
                            // Try again with the next number
                            continue;
                        }

                        throw $e;
                    }
                }

                if (! $saved) {
                    throw new RuntimeException('Failed to generate a unique invoice number after ' . $maxAttempts . ' attempts.');
                }
            } else {
                $raBill->save();
            }

            // Audit log
            ActivityLog::logCustom(
                'posted_to_accounts',
                'Client RA Bill ' . $raBill->ra_number . ' posted to accounts as voucher ' . $voucher->voucher_no,
                $raBill,
                [
                    'voucher_id'     => $voucher->id,
                    'voucher_no'     => $voucher->voucher_no,
                    'invoice_number' => $raBill->invoice_number,
                    'project_id'     => $project->id,
                    'net_amount'     => $netAmount,
                    'total_gst'      => $cgstAmount + $sgstAmount + $igstAmount,
                    'total_amount'   => $totalAmount,
                ]
            );

            return $voucher;
        });
    }

    /**
     * Reverse a posted Client RA Bill (create reversal voucher)
     */
    public function reverse(ClientRaBill $raBill, string $reason = ''): Voucher
    {
        if ($raBill->status !== 'posted' || !$raBill->voucher_id) {
            throw new RuntimeException('Only posted RA Bills can be reversed.');
        }

        $originalVoucher = $raBill->voucher;
        if (!$originalVoucher) {
            throw new RuntimeException('Original voucher not found.');
        }

        return DB::transaction(function () use ($raBill, $originalVoucher, $reason) {
            $companyId = (int) ($raBill->company_id ?: Config::get('accounting.default_company_id', 1));
            $voucherDate = now();

            // Create reversal voucher
            $reversalVoucher = new Voucher();
            $reversalVoucher->company_id = $companyId;
            // Centralised reversal numbering (Phase 5a)
            $reversalVoucher->voucher_no = $this->voucherNumberService->next('sales_reversal', $companyId, $voucherDate);
            $reversalVoucher->voucher_type = 'sales_reversal';
            $reversalVoucher->voucher_date = $voucherDate;
            $reversalVoucher->reference = 'REV-' . ($raBill->invoice_number ?: $raBill->ra_number);
            $reversalVoucher->narration = trim('Reversal of ' . $originalVoucher->voucher_no . ' - ' . $reason);
            $reversalVoucher->project_id = $raBill->project_id;
            $reversalVoucher->cost_center_id = $originalVoucher->cost_center_id
                ?: ($raBill->project_id ? ProjectCostCenterResolver::resolveId($companyId, (int) $raBill->project_id) : null);
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
                'Client RA Bill ' . $raBill->ra_number . ' posting reversed. Reversal voucher: ' . $reversalVoucher->voucher_no,
                $raBill,
                [
                    'original_voucher_id' => $originalVoucher->id,
                    'reversal_voucher_id' => $reversalVoucher->id,
                    'reason'              => $reason,
                ]
            );

            return $reversalVoucher;
        });
    }

    /**
     * Resolve revenue accounts from bill lines or default config
     * 
     * @return array<int, float> [account_id => amount]
     */
    protected function resolveRevenueAccounts(ClientRaBill $raBill): array
    {
        $raBill->loadMissing('lines');
        $revenueByAccount = [];

        // First, try to get revenue accounts from line items
        foreach ($raBill->lines as $line) {
            if ($line->revenue_account_id && $line->current_amount > 0) {
                $accountId = $line->revenue_account_id;
                if (!isset($revenueByAccount[$accountId])) {
                    $revenueByAccount[$accountId] = 0;
                }
                $revenueByAccount[$accountId] += (float) $line->current_amount;
            }
        }

        // If no line-level accounts, use default based on revenue type
        if (empty($revenueByAccount)) {
            $defaultAccount = $this->getDefaultRevenueAccount($raBill->revenue_type);
            if ($defaultAccount) {
                // Apply deductions to get net amount for revenue
                $revenueByAccount[$defaultAccount->id] = (float) $raBill->net_amount;
            }
        }

        return $revenueByAccount;
    }

    /**
     * Get default revenue account based on revenue type
     */
    protected function getDefaultRevenueAccount(string $revenueType): ?Account
    {
        $codeMap = [
            'fabrication' => Config::get('accounting.sales.fabrication_revenue_code', 'REV-FABRICATION'),
            'erection'    => Config::get('accounting.sales.erection_revenue_code', 'REV-ERECTION'),
            'supply'      => Config::get('accounting.sales.supply_revenue_code', 'REV-SUPPLY'),
            'service'     => Config::get('accounting.sales.service_revenue_code', 'REV-SERVICE'),
            'other'       => Config::get('accounting.sales.other_revenue_code', 'REV-OTHER'),
        ];

        $code = $codeMap[$revenueType] ?? $codeMap['fabrication'];

        $account = Account::where('code', $code)->first();

        // Fallback to any configured default
        if (!$account) {
            $fallbackCode = Config::get('accounting.sales.default_revenue_code', 'REV-FABRICATION');
            $account = Account::where('code', $fallbackCode)->first();
        }

        return $account;
    }

    /**
     * Resolve GST account by type (Output GST)
     */
    protected function resolveGstAccount(string $type): ?Account
    {
        $codeMap = [
            'cgst_output' => Config::get('accounting.gst.cgst_output_account_code', 'GST-CGST-OUTPUT'),
            'sgst_output' => Config::get('accounting.gst.sgst_output_account_code', 'GST-SGST-OUTPUT'),
            'igst_output' => Config::get('accounting.gst.igst_output_account_code', 'GST-IGST-OUTPUT'),
        ];

        $code = $codeMap[$type] ?? null;
        if (!$code) {
            return null;
        }

        return Account::where('code', $code)->first();
    }

    /**
     * Resolve TDS Receivable account (asset) used for Client TDS deductions.
     */
    protected function resolveTdsReceivableAccount(): ?Account
    {
        $code = Config::get('accounting.tds.tds_receivable_account_code', 'TDS-RECEIVABLE');
        if (! $code) {
            return null;
        }

        return Account::where('code', $code)->first();
    }

    // NOTE: voucher numbering is centralised in VoucherNumberService (Phase 5a)
}
