<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\SalesCreditNote;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesCreditNotePostingService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService,
        protected PartyAccountService $partyAccountService
    ) {}

    public function post(SalesCreditNote $note): Voucher
    {
        if (($note->status ?? null) !== 'draft') {
            throw new RuntimeException('Only draft credit notes can be posted.');
        }

        $note->loadMissing(['lines', 'client', 'clientRaBill']);

        if (! $note->client) {
            throw new RuntimeException('Client is required.');
        }

        if ($note->lines->isEmpty()) {
            throw new RuntimeException('Credit note must have at least one line.');
        }

        return DB::transaction(function () use ($note) {
            $companyId = (int) $note->company_id;

            $clientAccount = $this->partyAccountService->syncAccountForParty($note->client, $companyId);
            if (! $clientAccount) {
                throw new RuntimeException('Client ledger not found/created.');
            }

            [$totBasic, $totCgst, $totSgst, $totIgst] = $this->computeTotals($note);
            $totTax = $totCgst + $totSgst + $totIgst;
            $totAmt = $totBasic + $totTax;

            // Project / Cost Center (when linked to a Client RA Bill)
            $projectId = $note->clientRaBill?->project_id;
            $costCenterId = null;
            if (! empty($projectId)) {
                $costCenterId = ProjectCostCenterResolver::resolveId($companyId, (int) $projectId);
            }

            // Resolve GST OUTPUT (sales-side). To reduce output liability we DEBIT output ledgers.
            $outCgst = $this->resolveAccountByConfig('accounting.gst.cgst_output_account_code', $companyId);
            $outSgst = $this->resolveAccountByConfig('accounting.gst.sgst_output_account_code', $companyId);
            $outIgst = $this->resolveAccountByConfig('accounting.gst.igst_output_account_code', $companyId);

            $voucher = new Voucher();
            $voucher->company_id   = $companyId;
            $voucher->voucher_no   = $this->voucherNumberService->next('journal', $companyId, $note->note_date);
            $voucher->voucher_type = 'sales_credit_note';
            $voucher->voucher_date = $note->note_date;
            $voucher->reference    = $note->note_number;
            $voucher->narration    = trim('Sales Credit Note - ' . ($note->client?->name ?? ''));
            $voucher->status       = 'draft';
            $voucher->created_by   = Auth::id();
            $voucher->amount_base  = $totAmt;
            $voucher->exchange_rate = 1;
            $voucher->project_id = $projectId;
            $voucher->cost_center_id = $costCenterId;
            $voucher->save();

            $lineNo = 1;

            // Dr line accounts (sales return/discount/etc.)
            foreach ($note->lines as $l) {
                if ((float) $l->basic_amount <= 0) {
                    continue;
                }

                VoucherLine::create([
                    'voucher_id'  => $voucher->id,
                    'line_no'     => $lineNo++,
                    'account_id'  => $l->account_id,
                    'cost_center_id' => $costCenterId,
                    'description' => $l->description,
                    'debit'       => (float) $l->basic_amount,
                    'credit'      => 0,
                ]);
            }

            // Dr Output GST ledgers (reverse)
            if ($totCgst > 0.009 && $outCgst) {
                VoucherLine::create([
                    'voucher_id' => $voucher->id,
                    'line_no'    => $lineNo++,
                    'account_id' => $outCgst->id,
                    'cost_center_id' => $costCenterId,
                    'description'=> 'Reversal of Output CGST',
                    'debit'      => $totCgst,
                    'credit'     => 0,
                ]);
            }
            if ($totSgst > 0.009 && $outSgst) {
                VoucherLine::create([
                    'voucher_id' => $voucher->id,
                    'line_no'    => $lineNo++,
                    'account_id' => $outSgst->id,
                    'cost_center_id' => $costCenterId,
                    'description'=> 'Reversal of Output SGST',
                    'debit'      => $totSgst,
                    'credit'     => 0,
                ]);
            }
            if ($totIgst > 0.009 && $outIgst) {
                VoucherLine::create([
                    'voucher_id' => $voucher->id,
                    'line_no'    => $lineNo++,
                    'account_id' => $outIgst->id,
                    'cost_center_id' => $costCenterId,
                    'description'=> 'Reversal of Output IGST',
                    'debit'      => $totIgst,
                    'credit'     => 0,
                ]);
            }

            // Cr Client (reduces receivable)
            VoucherLine::create([
                'voucher_id'   => $voucher->id,
                'line_no'      => $lineNo++,
                'account_id'   => $clientAccount->id,
                'cost_center_id' => $costCenterId,
                'description'  => 'Credit Note ' . $note->note_number,
                'debit'        => 0,
                'credit'       => $totAmt,
            ]);

            $voucher->status    = 'posted';
            $voucher->posted_by = Auth::id();
            $voucher->posted_at = now();
            $voucher->save();

            $note->total_basic  = $totBasic;
            $note->total_cgst   = $totCgst;
            $note->total_sgst   = $totSgst;
            $note->total_igst   = $totIgst;
            $note->total_tax    = $totTax;
            $note->total_amount = $totAmt;
            $note->voucher_id   = $voucher->id;
            $note->status       = 'posted';
            $note->posted_by    = Auth::id();
            $note->posted_at    = now();
            $note->save();

            return $voucher;
        });
    }

    public function cancel(SalesCreditNote $note, string $cancelReason = ''): void
    {
        if (($note->status ?? null) !== 'posted' || ! $note->voucher_id) {
            throw new RuntimeException('Only posted credit notes can be cancelled.');
        }

        DB::transaction(function () use ($note, $cancelReason) {
            $orig = Voucher::with('lines')->lockForUpdate()->findOrFail($note->voucher_id);

            $rev = new Voucher();
            $rev->company_id   = $orig->company_id;
            $rev->voucher_no   = $this->voucherNumberService->next('journal', $orig->company_id, now()->toDateString());
            $rev->voucher_type = 'journal';
            $rev->voucher_date = now()->toDateString();
            $rev->reference    = 'CANCEL-CN/' . ($note->note_number ?? $note->id);
            $rev->narration    = trim('Cancel Sales Credit Note ' . ($note->note_number ?? '') . ' ' . $cancelReason);
            $rev->status       = 'draft';
            $rev->created_by   = Auth::id();
            $rev->amount_base  = $orig->amount_base;
            $rev->exchange_rate= $orig->exchange_rate ?? 1;
            $rev->project_id   = $orig->project_id;
            $rev->cost_center_id = $orig->cost_center_id;
            $rev->save();

            $ln = 1;
            foreach ($orig->lines as $l) {
                VoucherLine::create([
                    'voucher_id'  => $rev->id,
                    'line_no'     => $ln++,
                    'account_id'  => $l->account_id,
                    'cost_center_id' => ($l->cost_center_id ?? $orig->cost_center_id),
                    'description' => 'Cancel: ' . ($l->description ?? ''),
                    'debit'       => $l->credit,
                    'credit'      => $l->debit,
                ]);
            }

            $rev->status    = 'posted';
            $rev->posted_by = Auth::id();
            $rev->posted_at = now();
            $rev->save();

            $note->status       = 'cancelled';
            $note->cancelled_by = Auth::id();
            $note->cancelled_at = now();
            $note->remarks      = trim(($note->remarks ?? '') . '\nCancelled: ' . $cancelReason);
            $note->save();
        });
    }

    private function computeTotals(SalesCreditNote $note): array
    {
        $totBasic = 0.0;
        $totCgst  = 0.0;
        $totSgst  = 0.0;
        $totIgst  = 0.0;

        foreach ($note->lines as $l) {
            $totBasic += (float) ($l->basic_amount ?? 0);
            $totCgst  += (float) ($l->cgst_amount ?? 0);
            $totSgst  += (float) ($l->sgst_amount ?? 0);
            $totIgst  += (float) ($l->igst_amount ?? 0);
        }

        return [round($totBasic, 2), round($totCgst, 2), round($totSgst, 2), round($totIgst, 2)];
    }

    private function resolveAccountByConfig(string $configKey, int $companyId): ?Account
    {
        $code = trim((string) config($configKey));
        if ($code === '') {
            return null;
        }

        return Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();
    }
}



