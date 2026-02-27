<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseBillPostingService
{
    public function __construct(
        protected PartyAccountService $partyAccountService,
        protected ItemAccountingResolver $itemAccountingResolver,
        protected VoucherNumberService $voucherNumberService
    ) {
    }

    /**
     * Post the given purchase bill to accounting, creating a voucher.
     *
     * This will:
     * - Debit item / material / expense accounts line-wise
     * - Debit GST Input accounts (CGST / SGST / IGST) using bill header totals
     * - Handle Reverse Charge (RCM) GST by creating a self-entry:
     *      - Debit Input GST
     *      - Credit Output GST
     * - Debit TCS receivable (if any)
     * - Credit TDS payable (if any)
     * - Credit Supplier with net payable (Invoice total + TCS - TDS)
     *
     * Phase-A: If bill is tagged to a project, expense lines are booked to WIP-OTHER (instead of P&L expense ledger)
     * Phase-B: Expense lines can be split across multiple projects (per expense line project_id)
     *
     * Cost Center rule (Project = Cost Center):
     * - If voucher.project_id is set (unambiguous), voucher.cost_center_id is auto-resolved
     *   and all voucher_lines will carry that cost_center_id.
     * - If multiple projects exist (Phase-B), voucher.project_id stays NULL, and only
     *   project WIP-OTHER lines (and RCM GST lines) will carry per-project cost_center_id.
     *
     * @throws RuntimeException
     */
    public function postBill(PurchaseBill $bill): Voucher
    {
        if ($bill->status === 'posted' && $bill->voucher_id) {
            throw new RuntimeException('Purchase bill is already posted.');
        }

        $supplier = $bill->supplier;
        if (! $supplier) {
            throw new RuntimeException('Supplier is required on purchase bill.');
        }

        $companyId = (int) ($bill->company_id ?: Config::get('accounting.default_company_id', 1));

        $supplierAccount = $this->partyAccountService->syncAccountForParty($supplier, $companyId);
        if (! $supplierAccount instanceof Account) {
            throw new RuntimeException('Unable to resolve supplier ledger for purchase bill posting.');
        }

        $bill->loadMissing('lines.item.type', 'lines.item.subcategory', 'expenseLines.account', 'expenseLines.project');

        if ($bill->lines->isEmpty() && $bill->expenseLines->isEmpty()) {
            throw new RuntimeException('Cannot post a purchase bill without lines.');
        }

        // Header totals (already computed when saving the bill)
        $totalCgst    = (float) $bill->total_cgst;
        $totalSgst    = (float) $bill->total_sgst;
        $totalIgst    = (float) $bill->total_igst;
        $invoiceTotal = (float) $bill->total_amount;

        // Reverse Charge (RCM) totals (computed in PurchaseBillController store/update)
        $rcmCgst = (float) ($bill->total_rcm_cgst ?? 0);
        $rcmSgst = (float) ($bill->total_rcm_sgst ?? 0);
        $rcmIgst = (float) ($bill->total_rcm_igst ?? 0);

        $roundOff  = (float) ($bill->round_off ?? 0);
        $tdsAmount = (float) $bill->tds_amount;
        $tcsAmount = (float) $bill->tcs_amount;

        // Net payable to supplier = Invoice total + TCS - TDS
        $supplierCredit = round($invoiceTotal + $tcsAmount - $tdsAmount, 2);

        if ($supplierCredit < 0) {
            throw new RuntimeException('TDS amount cannot exceed invoice total (including TCS if any).');
        }

        // Voucher Date should follow Posting Date (Books), not Invoice Date.
        // If posting_date is empty (legacy data), fallback to bill_date.
        $voucherDate = $bill->posting_date ?: $bill->bill_date;

        // -----------------------
        // Project Resolution
        // -----------------------
        // 1) Bill header project_id (expense-only bills)
        // 2) Fallback from linked PO project_id (GRN/PO bills)
        $billProjectId = $bill->project_id ?: null;
        if (! $billProjectId && $bill->purchase_order_id) {
            $purchaseOrder = PurchaseOrder::find($bill->purchase_order_id);
            if ($purchaseOrder) {
                $billProjectId = $purchaseOrder->project_id;
            }
        }

        // Phase-B: collect per-expense-line project allocations (line.project_id overrides header)
        $expenseProjectIds = [];
        foreach ($bill->expenseLines as $exp) {
            $pid = (int) ($exp->project_id ?: $billProjectId ?: 0);
            if ($pid > 0) {
                $expenseProjectIds[] = $pid;
            }
        }
        $expenseProjectIds = array_values(array_unique($expenseProjectIds));

        // Voucher header project_id should be set only when it is unambiguous.
        // - If bill/PO has project and expense lines do not conflict -> use it
        // - If expense lines contain exactly one project -> use it
        // - If multiple projects -> keep NULL (line-level cost centers will track projects)
        $voucherProjectId = $billProjectId;
        if (! $voucherProjectId && count($expenseProjectIds) === 1) {
            $voucherProjectId = $expenseProjectIds[0];
        }
        if (count($expenseProjectIds) > 1) {
            $voucherProjectId = null;
        }

        // If any expense line is project-tagged, we will book it to WIP-OTHER (Option-1)
        $wipOtherAccountId = null;
        if (! empty($expenseProjectIds) && $bill->expenseLines && $bill->expenseLines->count()) {
            $wipOtherCode = Config::get('accounting.project_costing.other_direct_account_codes.0') ?: 'WIP-OTHER';
            $wipOtherAccountId = Account::query()->where('code', $wipOtherCode)->value('id');
            if (! $wipOtherAccountId) {
                throw new RuntimeException("WIP account not found for project expenses. Please create ledger code: {$wipOtherCode}");
            }
        }

        // Build grouped debit by account: [account_id => amount]
        $debitByAccount = [];

        // Phase-B: project expense lines => separate list (posted to WIP-OTHER with cost_center_id per project)
        $projectExpenseLines = [];

        // RCM GST lines (Phase-B: will be posted as self-entry split by project)
        $rcmTaxLines = [];

        /**
         * 1) ITEM LINES (Inventory / Service items)
         */
        foreach ($bill->lines as $line) {
            // Resolver decides which ledger to debit
            $accountId = $this->resolvePurchaseAccountForLine($line);

            if (! $accountId) {
                continue;
            }

            $amount = (float) $line->basic_amount;
            if ($amount <= 0) {
                continue;
            }

            $debitByAccount[$accountId] = ($debitByAccount[$accountId] ?? 0) + $amount;
        }

        /**
         * 2) EXPENSE LINES
         *    - If expense line has project (or bill header project), book to WIP-OTHER
         *    - Else, book to normal P&L expense ledger
         */
        foreach ($bill->expenseLines as $exp) {
            $amount = (float) $exp->basic_amount;
            if ($amount <= 0) {
                continue;
            }

            $lineProjectId = (int) ($exp->project_id ?: $billProjectId ?: 0);

            if ($lineProjectId > 0 && $wipOtherAccountId) {
                $origName = $exp->account?->name ?? ('Account#' . ($exp->account_id ?? ''));
                $desc = 'Project Expense: ' . $origName;
                if (! empty($exp->description)) {
                    $desc .= ' - ' . $exp->description;
                }
                $desc = substr($desc, 0, 250);

                $projectExpenseLines[] = [
                    'project_id'  => $lineProjectId,
                    'amount'      => $amount,
                    'description' => $desc,
                ];
            } else {
                // Normal (non-project) behaviour
                $accountId = $exp->account_id ?? null;
                if ($accountId) {
                    $debitByAccount[$accountId] = ($debitByAccount[$accountId] ?? 0) + $amount;
                }
            }

            // RCM tax collection (split by line project)
            if ((bool) ($exp->is_reverse_charge ?? false)) {
                $cg = (float) ($exp->cgst_amount ?? 0);
                $sg = (float) ($exp->sgst_amount ?? 0);
                $ig = (float) ($exp->igst_amount ?? 0);

                if (($cg + $sg + $ig) > 0.0001) {
                    $rcmTaxLines[] = [
                        'project_id' => $lineProjectId,
                        'cgst'       => $cg,
                        'sgst'       => $sg,
                        'igst'       => $ig,
                    ];
                }
            }
        }

        return DB::transaction(function () use (
            $bill,
            $companyId,
            $supplierAccount,
            $debitByAccount,
            $totalCgst,
            $totalSgst,
            $totalIgst,
            $rcmCgst,
            $rcmSgst,
            $rcmIgst,
            $rcmTaxLines,
            $tdsAmount,
            $tcsAmount,
            $supplierCredit,
            $voucherDate,
            $invoiceTotal,
            $roundOff,
            $voucherProjectId,
            $projectExpenseLines,
            $wipOtherAccountId
        ) {
            // Capture old bill attributes for audit
            $oldBillAttributes = $bill->getOriginal();

            // Resolve project cost center once (only when project is unambiguous)
            $voucherCostCenterId = null;
            if (! empty($voucherProjectId)) {
                $voucherCostCenterId = ProjectCostCenterResolver::resolveId((int) $companyId, (int) $voucherProjectId);
            }

            // 1) Voucher header
            $voucher = new Voucher();
            $voucher->company_id = $companyId;

            // Centralised voucher numbering
            $voucher->voucher_no = $this->voucherNumberService->next('purchase', $companyId, $voucherDate);

            $voucher->voucher_type = 'purchase';
            $voucher->voucher_date = $voucherDate;

            // Use existing 'reference' column to store supplier invoice number
            $voucher->reference = $bill->reference_no ?: $bill->bill_number;
            $voucher->narration = trim('Purchase Bill ' . $bill->bill_number . ' - ' . (string) $bill->remarks);

            // Phase-B: only set header project_id if unambiguous
            $voucher->project_id = $voucherProjectId;
            $voucher->cost_center_id = $voucherCostCenterId;

            $voucher->created_by = $bill->created_by;

            // Create voucher as DRAFT, insert lines, then POST (validates Dr==Cr)
            $voucher->status = 'draft';
            $voucher->amount_base = round($invoiceTotal + $tcsAmount, 2);
            $voucher->save();

            $lineNo = 1;

            // 2) Debit grouped material / expense / asset accounts
            foreach ($debitByAccount as $accountId => $amount) {
                $amount = (float) $amount;
                if ($amount <= 0) {
                    continue;
                }

                $acc = Account::find($accountId);
                if (! $acc) {
                    throw new RuntimeException('Debit account not found for id: ' . $accountId);
                }

                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $acc->id,
                    'cost_center_id' => $voucherCostCenterId,
                    'description'    => 'Purchase - ' . $bill->bill_number,
                    'debit'          => round($amount, 2),
                    'credit'         => 0,
                ]);
            }

            // 2b) Debit Project WIP-OTHER for project expense lines
            if ($wipOtherAccountId && ! empty($projectExpenseLines)) {
                $wipAcc = Account::find($wipOtherAccountId);
                if (! $wipAcc) {
                    throw new RuntimeException('WIP-OTHER account not found for id: ' . $wipOtherAccountId);
                }

                $costCenterCache = [];
                if (! empty($voucherProjectId) && ! empty($voucherCostCenterId)) {
                    $costCenterCache[(int) $voucherProjectId] = (int) $voucherCostCenterId;
                }

                foreach ($projectExpenseLines as $pe) {
                    $amount = (float) ($pe['amount'] ?? 0);
                    $pid = (int) ($pe['project_id'] ?? 0);

                    if ($amount <= 0 || $pid <= 0) {
                        continue;
                    }

                    if (! isset($costCenterCache[$pid])) {
                        $costCenterCache[$pid] = ProjectCostCenterResolver::resolveId((int) $companyId, (int) $pid);
                    }

                    $desc = (string) ($pe['description'] ?? ('Project Expense - ' . $bill->bill_number));

                    VoucherLine::create([
                        'voucher_id'     => $voucher->id,
                        'line_no'        => $lineNo++,
                        'account_id'     => $wipAcc->id,
                        'cost_center_id' => $costCenterCache[$pid],
                        'description'    => $desc,
                        'debit'          => round($amount, 2),
                        'credit'         => 0,
                    ]);
                }
            }

            // 3) Debit GST Input accounts (if configured and non-zero)
            $cgstCode = Config::get('accounting.gst.input_cgst_account_code');
            $sgstCode = Config::get('accounting.gst.input_sgst_account_code');
            $igstCode = Config::get('accounting.gst.input_igst_account_code');

            $cgstAccount = null;
            $sgstAccount = null;
            $igstAccount = null;

            if (($totalCgst > 0.0001 || $rcmCgst > 0.0001) && $cgstCode) {
                $cgstAccount = Account::where('code', $cgstCode)->first();
                if (! $cgstAccount) {
                    throw new RuntimeException('GST Input CGST account not found for code: ' . $cgstCode);
                }
            }
            if (($totalSgst > 0.0001 || $rcmSgst > 0.0001) && $sgstCode) {
                $sgstAccount = Account::where('code', $sgstCode)->first();
                if (! $sgstAccount) {
                    throw new RuntimeException('GST Input SGST account not found for code: ' . $sgstCode);
                }
            }
            if (($totalIgst > 0.0001 || $rcmIgst > 0.0001) && $igstCode) {
                $igstAccount = Account::where('code', $igstCode)->first();
                if (! $igstAccount) {
                    throw new RuntimeException('GST Input IGST account not found for code: ' . $igstCode);
                }
            }

            if ($totalCgst > 0.0001 && $cgstAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $cgstAccount->id,
                    'cost_center_id' => $voucherCostCenterId,
                    'description'    => 'Input CGST - ' . $bill->bill_number,
                    'debit'          => round($totalCgst, 2),
                    'credit'         => 0,
                ]);
            }

            if ($totalSgst > 0.0001 && $sgstAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $sgstAccount->id,
                    'cost_center_id' => $voucherCostCenterId,
                    'description'    => 'Input SGST - ' . $bill->bill_number,
                    'debit'          => round($totalSgst, 2),
                    'credit'         => 0,
                ]);
            }

            if ($totalIgst > 0.0001 && $igstAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $igstAccount->id,
                    'cost_center_id' => $voucherCostCenterId,
                    'description'    => 'Input IGST - ' . $bill->bill_number,
                    'debit'          => round($totalIgst, 2),
                    'credit'         => 0,
                ]);
            }

            // 3b) Reverse Charge (RCM) GST self-entry: Input (Dr) + Output (Cr)
            // NOTE:
            // - Supplier invoice payable DOES NOT include RCM GST (handled at bill save time)
            // - Here, we create a balanced self-entry so that:
            //     Output GST liability is recognized (Cr)
            //     Input GST credit is recognized (Dr)
            $rcmTotal = $rcmCgst + $rcmSgst + $rcmIgst;
            if ($rcmTotal > 0.0001) {
                $outCgstCode = Config::get('accounting.gst.cgst_output_account_code');
                $outSgstCode = Config::get('accounting.gst.sgst_output_account_code');
                $outIgstCode = Config::get('accounting.gst.igst_output_account_code');

                $outCgstAccount = null;
                $outSgstAccount = null;
                $outIgstAccount = null;

                if ($rcmCgst > 0.0001) {
                    if (! $outCgstCode) {
                        throw new RuntimeException('RCM CGST exists but output CGST account code is not configured (accounting.gst.cgst_output_account_code).');
                    }
                    $outCgstAccount = Account::where('code', $outCgstCode)->first();
                    if (! $outCgstAccount) {
                        throw new RuntimeException('GST Output CGST account not found for code: ' . $outCgstCode);
                    }
                    if (! $cgstAccount) {
                        throw new RuntimeException('RCM CGST exists but input CGST account is missing.');
                    }
                }

                if ($rcmSgst > 0.0001) {
                    if (! $outSgstCode) {
                        throw new RuntimeException('RCM SGST exists but output SGST account code is not configured (accounting.gst.sgst_output_account_code).');
                    }
                    $outSgstAccount = Account::where('code', $outSgstCode)->first();
                    if (! $outSgstAccount) {
                        throw new RuntimeException('GST Output SGST account not found for code: ' . $outSgstCode);
                    }
                    if (! $sgstAccount) {
                        throw new RuntimeException('RCM SGST exists but input SGST account is missing.');
                    }
                }

                if ($rcmIgst > 0.0001) {
                    if (! $outIgstCode) {
                        throw new RuntimeException('RCM IGST exists but output IGST account code is not configured (accounting.gst.igst_output_account_code).');
                    }
                    $outIgstAccount = Account::where('code', $outIgstCode)->first();
                    if (! $outIgstAccount) {
                        throw new RuntimeException('GST Output IGST account not found for code: ' . $outIgstCode);
                    }
                    if (! $igstAccount) {
                        throw new RuntimeException('RCM IGST exists but input IGST account is missing.');
                    }
                }

                // (A) If voucher is single-project (voucherCostCenterId exists), post consolidated lines
                if (! empty($voucherCostCenterId)) {
                    if ($rcmCgst > 0.0001 && $cgstAccount && $outCgstAccount) {
                        // Dr Input CGST (RCM)
                        VoucherLine::create([
                            'voucher_id'     => $voucher->id,
                            'line_no'        => $lineNo++,
                            'account_id'     => $cgstAccount->id,
                            'cost_center_id' => $voucherCostCenterId,
                            'description'    => 'Input CGST (RCM) - ' . $bill->bill_number,
                            'debit'          => round($rcmCgst, 2),
                            'credit'         => 0,
                        ]);
                        // Cr Output CGST (RCM)
                        VoucherLine::create([
                            'voucher_id'     => $voucher->id,
                            'line_no'        => $lineNo++,
                            'account_id'     => $outCgstAccount->id,
                            'cost_center_id' => $voucherCostCenterId,
                            'description'    => 'Output CGST (RCM) - ' . $bill->bill_number,
                            'debit'          => 0,
                            'credit'         => round($rcmCgst, 2),
                        ]);
                    }

                    if ($rcmSgst > 0.0001 && $sgstAccount && $outSgstAccount) {
                        VoucherLine::create([
                            'voucher_id'     => $voucher->id,
                            'line_no'        => $lineNo++,
                            'account_id'     => $sgstAccount->id,
                            'cost_center_id' => $voucherCostCenterId,
                            'description'    => 'Input SGST (RCM) - ' . $bill->bill_number,
                            'debit'          => round($rcmSgst, 2),
                            'credit'         => 0,
                        ]);
                        VoucherLine::create([
                            'voucher_id'     => $voucher->id,
                            'line_no'        => $lineNo++,
                            'account_id'     => $outSgstAccount->id,
                            'cost_center_id' => $voucherCostCenterId,
                            'description'    => 'Output SGST (RCM) - ' . $bill->bill_number,
                            'debit'          => 0,
                            'credit'         => round($rcmSgst, 2),
                        ]);
                    }

                    if ($rcmIgst > 0.0001 && $igstAccount && $outIgstAccount) {
                        VoucherLine::create([
                            'voucher_id'     => $voucher->id,
                            'line_no'        => $lineNo++,
                            'account_id'     => $igstAccount->id,
                            'cost_center_id' => $voucherCostCenterId,
                            'description'    => 'Input IGST (RCM) - ' . $bill->bill_number,
                            'debit'          => round($rcmIgst, 2),
                            'credit'         => 0,
                        ]);
                        VoucherLine::create([
                            'voucher_id'     => $voucher->id,
                            'line_no'        => $lineNo++,
                            'account_id'     => $outIgstAccount->id,
                            'cost_center_id' => $voucherCostCenterId,
                            'description'    => 'Output IGST (RCM) - ' . $bill->bill_number,
                            'debit'          => 0,
                            'credit'         => round($rcmIgst, 2),
                        ]);
                    }
                } else {
                    // (B) Multi-project: group RCM GST by project -> post per-project cost centers
                    $rcmGrouped = [];
                    foreach ($rcmTaxLines as $row) {
                        $pid = (int) ($row['project_id'] ?? 0);
                        $k = (string) $pid;
                        if (! isset($rcmGrouped[$k])) {
                            $rcmGrouped[$k] = ['project_id' => $pid, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
                        }
                        $rcmGrouped[$k]['cgst'] += (float) ($row['cgst'] ?? 0);
                        $rcmGrouped[$k]['sgst'] += (float) ($row['sgst'] ?? 0);
                        $rcmGrouped[$k]['igst'] += (float) ($row['igst'] ?? 0);
                    }

                    // Fallback: if no lines were captured but header has totals, post once without cost center.
                    if (empty($rcmGrouped)) {
                        $rcmGrouped['0'] = ['project_id' => 0, 'cgst' => $rcmCgst, 'sgst' => $rcmSgst, 'igst' => $rcmIgst];
                    }

                    $ccCache = [];

                    foreach ($rcmGrouped as $g) {
                        $pid = (int) ($g['project_id'] ?? 0);
                        $cg  = (float) ($g['cgst'] ?? 0);
                        $sg  = (float) ($g['sgst'] ?? 0);
                        $ig  = (float) ($g['igst'] ?? 0);

                        if (($cg + $sg + $ig) <= 0.0001) {
                            continue;
                        }

                        $ccid = null;
                        if ($pid > 0) {
                            if (! isset($ccCache[$pid])) {
                                $ccCache[$pid] = ProjectCostCenterResolver::resolveId((int) $companyId, (int) $pid);
                            }
                            $ccid = $ccCache[$pid];
                        }

                        if ($cg > 0.0001 && $cgstAccount && $outCgstAccount) {
                            VoucherLine::create([
                                'voucher_id'     => $voucher->id,
                                'line_no'        => $lineNo++,
                                'account_id'     => $cgstAccount->id,
                                'cost_center_id' => $ccid,
                                'description'    => 'Input CGST (RCM) - ' . $bill->bill_number,
                                'debit'          => round($cg, 2),
                                'credit'         => 0,
                            ]);
                            VoucherLine::create([
                                'voucher_id'     => $voucher->id,
                                'line_no'        => $lineNo++,
                                'account_id'     => $outCgstAccount->id,
                                'cost_center_id' => $ccid,
                                'description'    => 'Output CGST (RCM) - ' . $bill->bill_number,
                                'debit'          => 0,
                                'credit'         => round($cg, 2),
                            ]);
                        }

                        if ($sg > 0.0001 && $sgstAccount && $outSgstAccount) {
                            VoucherLine::create([
                                'voucher_id'     => $voucher->id,
                                'line_no'        => $lineNo++,
                                'account_id'     => $sgstAccount->id,
                                'cost_center_id' => $ccid,
                                'description'    => 'Input SGST (RCM) - ' . $bill->bill_number,
                                'debit'          => round($sg, 2),
                                'credit'         => 0,
                            ]);
                            VoucherLine::create([
                                'voucher_id'     => $voucher->id,
                                'line_no'        => $lineNo++,
                                'account_id'     => $outSgstAccount->id,
                                'cost_center_id' => $ccid,
                                'description'    => 'Output SGST (RCM) - ' . $bill->bill_number,
                                'debit'          => 0,
                                'credit'         => round($sg, 2),
                            ]);
                        }

                        if ($ig > 0.0001 && $igstAccount && $outIgstAccount) {
                            VoucherLine::create([
                                'voucher_id'     => $voucher->id,
                                'line_no'        => $lineNo++,
                                'account_id'     => $igstAccount->id,
                                'cost_center_id' => $ccid,
                                'description'    => 'Input IGST (RCM) - ' . $bill->bill_number,
                                'debit'          => round($ig, 2),
                                'credit'         => 0,
                            ]);
                            VoucherLine::create([
                                'voucher_id'     => $voucher->id,
                                'line_no'        => $lineNo++,
                                'account_id'     => $outIgstAccount->id,
                                'cost_center_id' => $ccid,
                                'description'    => 'Output IGST (RCM) - ' . $bill->bill_number,
                                'debit'          => 0,
                                'credit'         => round($ig, 2),
                            ]);
                        }
                    }
                }
            }

            // 4) Debit TCS receivable (if any)
            if ($tcsAmount > 0) {
                $tcsCode = Config::get('accounting.tcs.tcs_receivable_account_code');
                if (! $tcsCode) {
                    throw new RuntimeException('TCS receivable account code not configured (accounting.tcs.tcs_receivable_account_code).');
                }

                $tcsAccount = Account::where('code', $tcsCode)->first();
                if (! $tcsAccount) {
                    throw new RuntimeException('TCS receivable account not found for code: ' . $tcsCode);
                }

                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $tcsAccount->id,
                    'cost_center_id' => $voucherCostCenterId,
                    'description'    => 'TCS Receivable - ' . $bill->bill_number,
                    'debit'          => round($tcsAmount, 2),
                    'credit'         => 0,
                ]);
            }

            // 5) Credit TDS payable (if any)
            if ($tdsAmount > 0) {
                $tdsCode = Config::get('accounting.tds.tds_payable_account_code');
                if (! $tdsCode) {
                    throw new RuntimeException('TDS payable account code not configured (accounting.tds.tds_payable_account_code).');
                }

                $tdsAccount = Account::where('code', $tdsCode)->first();
                if (! $tdsAccount) {
                    throw new RuntimeException('TDS payable account not found for code: ' . $tdsCode);
                }

                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $tdsAccount->id,
                    'cost_center_id' => $voucherCostCenterId,
                    'description'    => 'TDS Payable - ' . $bill->bill_number,
                    'debit'          => 0,
                    'credit'         => round($tdsAmount, 2),
                ]);
            }

            // 6) Round Off (to match supplier invoice total)
            if (abs($roundOff) > 0.0001) {
                $roundOffCode = Config::get('accounting.round_off.round_off_account_code', 'ROUND-OFF');
                $roundOffAccount = Account::where('code', $roundOffCode)->first();
                if (! $roundOffAccount) {
                    throw new RuntimeException('Round Off account not found for code: ' . $roundOffCode);
                }

                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $roundOffAccount->id,
                    'cost_center_id' => $voucherCostCenterId,
                    'description'    => 'Round Off - ' . $bill->bill_number,
                    'debit'          => $roundOff > 0 ? round($roundOff, 2) : 0,
                    'credit'         => $roundOff < 0 ? round(abs($roundOff), 2) : 0,
                ]);
            }

            // 7) Credit Supplier with net payable
            VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => $lineNo++,
                'account_id'     => $supplierAccount->id,
                'cost_center_id' => $voucherCostCenterId,
                'description'    => 'Supplier - ' . $bill->bill_number,
                'debit'          => 0,
                'credit'         => round($supplierCredit, 2),
            ]);

            // 8) Finalize posting (validates balance + normalizes amount_base)
            $voucher->posted_by = Auth::id();
            $voucher->posted_at = now();
            $voucher->status = 'posted';
            $voucher->save();

            // 9) Mark bill as posted and link voucher
            $bill->voucher_id = $voucher->id;
            $bill->status = 'posted';
            $bill->save();


            // 9.5) Auto-register MACHINERY machines from this posted bill (idempotent)
            // NOTE: This MUST NOT affect accounting posting. We keep it non-blocking by design.
            try {
                if (config('machinery.auto_register_from_purchase_bill', true)) {
                    app(\App\Services\Machinery\MachineAutoRegistrationService::class)
                        ->registerFromPurchaseBill($bill);
                }
            } catch (\Throwable $e) {
                // Swallow to avoid blocking accounting posting
                // (errors will still be visible in logs)
                logger()->warning('Machine auto-register failed for PurchaseBill #' . $bill->id . ': ' . $e->getMessage());
            }

            // 10) Audit logs
            ActivityLog::logUpdated(
                $bill,
                $oldBillAttributes,
                'Purchase bill posted to accounts as voucher ' . $voucher->voucher_no
            );

            ActivityLog::logCustom(
                'posted_to_accounts',
                'Purchase bill ' . ($bill->bill_number ?: ('#' . $bill->id)) . ' posted to accounts as voucher ' . $voucher->voucher_no,
                $voucher,
                [
                    'bill_id'       => $bill->id,
                    'bill_number'   => $bill->bill_number,
                    'voucher_id'    => $voucher->id,
                    'voucher_no'    => $voucher->voucher_no,
                    'voucher_type'  => $voucher->voucher_type,
                    'business_date' => optional($voucherDate)->toDateString(),
                ]
            );

            return $voucher;
        });
    }

    /**
     * Resolve which account to debit for a given purchase bill line.
     */
    protected function resolvePurchaseAccountForLine($line): ?int
    {
        return $this->itemAccountingResolver->resolvePurchaseAccountId($line->item);
    }
}



