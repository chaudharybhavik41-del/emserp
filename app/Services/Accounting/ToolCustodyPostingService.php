<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Party;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phase-C: Tool Stock custody accounting (short-term tools like grinders, drills, etc.).
 *
 * Concept:
 * - Tools purchased & held in store are in INV-TOOLS (Asset)
 * - When issued to contractor/worker: move value to TOOLS-WITH-CONTRACTOR (Asset)
 * - When returned: reverse back to INV-TOOLS
 * - If scrapped/not returned: credit TOOLS-WITH-CONTRACTOR and debit either
 *   - TOOLS-SCRAP-LOSS (company bears)
 *   - contractor ledger (contractor bears)
 *   - both (shared)
 */
class ToolCustodyPostingService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService,
        protected PartyAccountService $partyAccountService
    ) {
    }

    
    /**
     * Backward-compatible alias used by controllers that call postIssueToCustody().
     * Prefer postIssue() going forward.
     */
    public function postIssueToCustody(MachineAssignment $assignment): ?Voucher
    {
        return $this->postIssue($assignment);
    }

    /**
     * Backward-compatible alias used by controllers that call postReturnFromCustody().
     * Prefer postReturn() going forward.
     */
    public function postReturnFromCustody(MachineAssignment $assignment): ?Voucher
    {
        return $this->postReturn($assignment);
    }

public function postIssue(MachineAssignment $assignment): ?Voucher
    {
        $assignment->loadMissing('machine', 'contractor', 'worker');

        if (! $assignment->machine instanceof Machine) {
            return null;
        }

        if (! $this->isToolStockMachine($assignment->machine)) {
            return null;
        }

        if (! empty($assignment->issue_voucher_id)) {
            return Voucher::find($assignment->issue_voucher_id);
        }

        $amount = (float) ($assignment->machine->purchase_price ?? 0);
        if ($amount <= 0) {
            throw new RuntimeException('Tool stock issue requires Machine Purchase Price. Please update the machine purchase price first.');
        }

        $companyId = (int) Config::get('accounting.default_company_id', 1);
        $invAcc    = $this->accountByCode($companyId, (string) Config::get('accounting.default_accounts.inventory_tools_code', 'INV-TOOLS'));
        $custAcc   = $this->accountByCode($companyId, (string) Config::get('accounting.default_accounts.tools_with_contractor_code', 'TOOLS-WITH-CONTRACTOR'));

        $businessDate = $assignment->assigned_date ? Carbon::parse($assignment->assigned_date) : now();

        return DB::transaction(function () use ($assignment, $companyId, $invAcc, $custAcc, $amount, $businessDate) {
            // Re-lock for update to prevent double posting
            $assignment = MachineAssignment::whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();

            if (! empty($assignment->issue_voucher_id)) {
                return Voucher::find($assignment->issue_voucher_id);
            }

            $machine = $assignment->machine;
            $who     = $assignment->getAssignedToName();

            $voucher = new Voucher();
            $voucher->company_id     = $companyId;
            $voucher->voucher_no     = $this->voucherNumberService->next('tools_transfer', $companyId, $businessDate);
            $voucher->voucher_type   = 'tools_transfer';
            $voucher->voucher_date   = $businessDate->toDateString();
            $voucher->reference      = $assignment->assignment_number;
            $voucher->narration      = 'Tool issued to custody: ' . ($machine->code ?? ('M#' . $machine->id)) . ' - ' . ($machine->name ?? '') . ' | To: ' . $who;
            $voucher->project_id     = null; // not project cost
            $voucher->cost_center_id = null;
            $voucher->currency_id    = null;
            $voucher->exchange_rate  = 1;
            $voucher->status         = 'draft';
            $voucher->created_by     = $assignment->issued_by ?? Auth::id();
            $voucher->amount_base    = round($amount, 2);
            $voucher->save();

            // Dr Tools in Custody
            VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => 1,
                'account_id'     => $custAcc->id,
                'cost_center_id' => null,
                'description'    => 'Issued to custody: ' . ($who),
                'debit'          => round($amount, 2),
                'credit'         => 0,
                'reference_type' => MachineAssignment::class,
                'reference_id'   => $assignment->id,
            ]);

            // Cr Tools Inventory
            VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => 2,
                'account_id'     => $invAcc->id,
                'cost_center_id' => null,
                'description'    => 'Issued out of store: ' . ($machine->code ?? ('M#' . $machine->id)),
                'debit'          => 0,
                'credit'         => round($amount, 2),
                'reference_type' => MachineAssignment::class,
                'reference_id'   => $assignment->id,
            ]);

            // Post voucher
            $voucher->status    = 'posted';
            $voucher->posted_at = now();
            $voucher->save();

            $assignment->issue_voucher_id = $voucher->id;
            $assignment->save();

            return $voucher;
        });
    }

    public function postReturn(MachineAssignment $assignment): ?Voucher
    {
        $assignment->loadMissing('machine', 'contractor', 'worker');

        if (! $assignment->machine instanceof Machine) {
            return null;
        }

        if (! $this->isToolStockMachine($assignment->machine)) {
            return null;
        }

        if (! empty($assignment->return_voucher_id)) {
            return Voucher::find($assignment->return_voucher_id);
        }

        $amount = (float) ($assignment->machine->purchase_price ?? 0);
        if ($amount <= 0) {
            throw new RuntimeException('Tool stock return requires Machine Purchase Price. Please update the machine purchase price first.');
        }

        $companyId = (int) Config::get('accounting.default_company_id', 1);
        $invAcc    = $this->accountByCode($companyId, (string) Config::get('accounting.default_accounts.inventory_tools_code', 'INV-TOOLS'));
        $custAcc   = $this->accountByCode($companyId, (string) Config::get('accounting.default_accounts.tools_with_contractor_code', 'TOOLS-WITH-CONTRACTOR'));

        $businessDate = $assignment->actual_return_date ? Carbon::parse($assignment->actual_return_date) : now();

        return DB::transaction(function () use ($assignment, $companyId, $invAcc, $custAcc, $amount, $businessDate) {
            $assignment = MachineAssignment::whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();

            if (! empty($assignment->return_voucher_id)) {
                return Voucher::find($assignment->return_voucher_id);
            }

            $machine = $assignment->machine;
            $who     = $assignment->getAssignedToName();

            $voucher = new Voucher();
            $voucher->company_id     = $companyId;
            $voucher->voucher_no     = $this->voucherNumberService->next('tools_transfer', $companyId, $businessDate);
            $voucher->voucher_type   = 'tools_transfer';
            $voucher->voucher_date   = $businessDate->toDateString();
            $voucher->reference      = $assignment->assignment_number;
            $voucher->narration      = 'Tool returned from custody: ' . ($machine->code ?? ('M#' . $machine->id)) . ' - ' . ($machine->name ?? '') . ' | From: ' . $who;
            $voucher->project_id     = null;
            $voucher->cost_center_id = null;
            $voucher->currency_id    = null;
            $voucher->exchange_rate  = 1;
            $voucher->status         = 'draft';
            $voucher->created_by     = $assignment->returned_by ?? Auth::id();
            $voucher->amount_base    = round($amount, 2);
            $voucher->save();

            // Dr Tools Inventory
            VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => 1,
                'account_id'     => $invAcc->id,
                'cost_center_id' => null,
                'description'    => 'Returned to store',
                'debit'          => round($amount, 2),
                'credit'         => 0,
                'reference_type' => MachineAssignment::class,
                'reference_id'   => $assignment->id,
            ]);

            // Cr Tools in Custody
            VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => 2,
                'account_id'     => $custAcc->id,
                'cost_center_id' => null,
                'description'    => 'Returned from custody: ' . ($who),
                'debit'          => 0,
                'credit'         => round($amount, 2),
                'reference_type' => MachineAssignment::class,
                'reference_id'   => $assignment->id,
            ]);

            $voucher->status    = 'posted';
            $voucher->posted_at = now();
            $voucher->save();

            $assignment->return_voucher_id = $voucher->id;
            $assignment->save();

            return $voucher;
        });
    }

    /**
     * Scrap / not-returned settlement:
     * - Credits the custody ledger (removes the tool value from custody)
     * - Debits either company scrap loss, contractor ledger, or both.
     */
    public function postScrapSettlement(MachineAssignment $assignment, string $borneBy, float $recoveryAmount = 0): ?Voucher
    {
        $assignment->loadMissing('machine', 'contractor', 'worker');

        if (! $assignment->machine instanceof Machine) {
            return null;
        }

        if (! $this->isToolStockMachine($assignment->machine)) {
            return null;
        }

        if (! empty($assignment->return_voucher_id)) {
            return Voucher::find($assignment->return_voucher_id);
        }

        $borneBy = strtolower(trim($borneBy));
        if (! in_array($borneBy, ['company', 'contractor', 'shared'], true)) {
            throw new RuntimeException('Invalid borneBy. Allowed: company, contractor, shared');
        }

        $cost = (float) ($assignment->machine->purchase_price ?? 0);
        if ($cost <= 0) {
            throw new RuntimeException('Tool scrap settlement requires Machine Purchase Price. Please update the machine purchase price first.');
        }

        $recoveryAmount = (float) $recoveryAmount;
        if ($recoveryAmount < 0) {
            throw new RuntimeException('Recovery amount cannot be negative.');
        }

        if ($borneBy === 'company') {
            $recoveryAmount = 0;
        }

        if ($borneBy === 'contractor') {
            $recoveryAmount = $cost;
        }

        if ($recoveryAmount > $cost) {
            throw new RuntimeException('Recovery amount cannot be more than tool cost.');
        }

        $companyLoss = round($cost - $recoveryAmount, 2);

        $companyId = (int) Config::get('accounting.default_company_id', 1);
        $custAcc   = $this->accountByCode($companyId, (string) Config::get('accounting.default_accounts.tools_with_contractor_code', 'TOOLS-WITH-CONTRACTOR'));
        $lossAcc   = $this->accountByCode($companyId, (string) Config::get('accounting.default_accounts.tools_scrap_loss_code', 'TOOLS-SCRAP-LOSS'));

        $contractorAccount = null;
        if ($recoveryAmount > 0) {
            if ($assignment->assignment_type !== 'contractor') {
                throw new RuntimeException('Recovery can only be posted for Contractor assignments.');
            }

            $party = $assignment->contractor;
            if (! $party instanceof Party) {
                throw new RuntimeException('Contractor party not found for this assignment.');
            }

            $contractorAccount = $this->partyAccountService->syncAccountForParty($party, $companyId);
            if (! $contractorAccount) {
                throw new RuntimeException('Unable to resolve / create contractor ledger account.');
            }
        }

        $businessDate = $assignment->actual_return_date ? Carbon::parse($assignment->actual_return_date) : now();

        return DB::transaction(function () use ($assignment, $companyId, $custAcc, $lossAcc, $contractorAccount, $cost, $recoveryAmount, $companyLoss, $businessDate, $borneBy) {
            $assignment = MachineAssignment::whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();

            if (! empty($assignment->return_voucher_id)) {
                return Voucher::find($assignment->return_voucher_id);
            }

            $machine = $assignment->machine;
            $who     = $assignment->getAssignedToName();

            $voucher = new Voucher();
            $voucher->company_id     = $companyId;
            $voucher->voucher_no     = $this->voucherNumberService->next('tools_transfer', $companyId, $businessDate);
            $voucher->voucher_type   = 'tools_transfer';
            $voucher->voucher_date   = $businessDate->toDateString();
            $voucher->reference      = $assignment->assignment_number;
            $voucher->narration      = 'Tool scrapped / not returned: ' . ($machine->code ?? ('M#' . $machine->id)) . ' - ' . ($machine->name ?? '') . ' | Custody: ' . $who . ' | Borne by: ' . strtoupper($borneBy);
            $voucher->project_id     = null;
            $voucher->cost_center_id = null;
            $voucher->currency_id    = null;
            $voucher->exchange_rate  = 1;
            $voucher->status         = 'draft';
            $voucher->created_by     = $assignment->returned_by ?? Auth::id();
            $voucher->amount_base    = round($cost, 2);
            $voucher->save();

            $lineNo = 1;

            // Debit: contractor recovery (if any)
            if ($recoveryAmount > 0 && $contractorAccount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $contractorAccount->id,
                    'cost_center_id' => null,
                    'description'    => 'Tool recovery from contractor',
                    'debit'          => round($recoveryAmount, 2),
                    'credit'         => 0,
                    'reference_type' => MachineAssignment::class,
                    'reference_id'   => $assignment->id,
                ]);
            }

            // Debit: company loss (if any)
            if ($companyLoss > 0) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $lossAcc->id,
                    'cost_center_id' => null,
                    'description'    => 'Tool scrap/loss booked to company',
                    'debit'          => round($companyLoss, 2),
                    'credit'         => 0,
                    'reference_type' => MachineAssignment::class,
                    'reference_id'   => $assignment->id,
                ]);
            }

            // Credit: Tools in custody (full cost)
            VoucherLine::create([
                'voucher_id'     => $voucher->id,
                'line_no'        => $lineNo++,
                'account_id'     => $custAcc->id,
                'cost_center_id' => null,
                'description'    => 'Tool removed from custody (scrapped/not returned)',
                'debit'          => 0,
                'credit'         => round($cost, 2),
                'reference_type' => MachineAssignment::class,
                'reference_id'   => $assignment->id,
            ]);

            $voucher->status    = 'posted';
            $voucher->posted_at = now();
            $voucher->save();

            // Store settlement amounts for audit
            $assignment->return_voucher_id      = $voucher->id;
            $assignment->damage_borne_by        = $borneBy;
            $assignment->damage_recovery_amount = round($recoveryAmount, 2);
            $assignment->damage_loss_amount     = round($companyLoss, 2);
            $assignment->save();

            return $voucher;
        });
    }

    protected function accountByCode(int $companyId, string $code): Account
    {
        $acc = Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if (! $acc) {
            throw new RuntimeException('Ledger not found for code: ' . $code);
        }

        return $acc;
    }

    protected function isToolStockMachine(Machine $machine): bool
    {
        $treatment = strtolower(trim((string) ($machine->accounting_treatment ?? '')));

        if ($treatment === 'tool_stock') {
            return true;
        }

        if ($treatment === 'fixed_asset') {
            return false;
        }

        // Fallback (if material type is ever extended)
        $usage = strtolower(trim((string) ($machine->materialType?->accounting_usage ?? '')));
        return $usage === 'tool_stock';
    }
}



