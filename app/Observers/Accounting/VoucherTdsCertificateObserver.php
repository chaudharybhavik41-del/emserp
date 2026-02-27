<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\TdsCertificate;
use App\Models\Accounting\Voucher;
use App\Models\Party;
use App\Models\PurchaseBill;
use App\Models\SubcontractorRaBill;

/**
 * Phase 1.6 / DEV18
 *
 * Auto-create/update TDS Certificate rows for PAYABLE side when a voucher
 * is posted.
 *
 * Why observer (instead of editing posting services)?
 * - Works for Purchase Bills and Subcontractor RA Bills in one place
 * - Avoids fragile changes inside long posting service files
 */
class VoucherTdsCertificateObserver
{
    /**
     * Handle the Voucher "updated" event.
     */
    public function updated(Voucher $voucher): void
    {
        // Only act when voucher becomes posted (draft -> posted)
        if (($voucher->status ?? null) !== 'posted') {
            return;
        }

        $oldStatus = (string) ($voucher->getOriginal('status') ?? '');
        if ($oldStatus === 'posted') {
            return;
        }

        $type = (string) ($voucher->voucher_type ?? '');
        if (! in_array($type, ['purchase', 'subcontractor_ra'], true)) {
            return;
        }

        $companyId = (int) $voucher->company_id;
        if ($companyId <= 0) {
            return;
        }

        $userId = auth()->id() ?: ($voucher->posted_by ?? null);

        if ($type === 'purchase') {
            $this->syncFromPurchaseBillVoucher($voucher, $companyId, $userId);
            return;
        }

        if ($type === 'subcontractor_ra') {
            $this->syncFromSubcontractorRaVoucher($voucher, $companyId, $userId);
            return;
        }
    }

    protected function syncFromPurchaseBillVoucher(Voucher $voucher, int $companyId, $userId = null): void
    {
        /** @var PurchaseBill|null $bill */
        $bill = PurchaseBill::query()
            ->with(['supplier'])
            ->where('company_id', $companyId)
            ->where('voucher_id', $voucher->id)
            ->first();

        if (! $bill) {
            return;
        }

        $tdsAmount = (float) ($bill->tds_amount ?? 0);
        if ($tdsAmount <= 0.009) {
            return;
        }

        $party = $bill->supplier;
        if (! $party) {
            return;
        }

        $partyAccount = $this->resolvePartyAccount($companyId, (int) $party->id);
        if (! $partyAccount) {
            return;
        }

        $this->upsertPayableCertificate(
            $companyId,
            (int) $voucher->id,
            (int) $partyAccount->id,
            $bill->tds_section ?? null,
            $bill->tds_rate ?? null,
            $tdsAmount,
            $userId
        );
    }

    protected function syncFromSubcontractorRaVoucher(Voucher $voucher, int $companyId, $userId = null): void
    {
        /** @var SubcontractorRaBill|null $ra */
        $ra = SubcontractorRaBill::query()
            ->with(['subcontractor'])
            ->where('company_id', $companyId)
            ->where('voucher_id', $voucher->id)
            ->first();

        if (! $ra) {
            return;
        }

        $tdsAmount = (float) ($ra->tds_amount ?? 0);
        if ($tdsAmount <= 0.009) {
            return;
        }

        $party = $ra->subcontractor;
        if (! $party) {
            return;
        }

        $partyAccount = $this->resolvePartyAccount($companyId, (int) $party->id);
        if (! $partyAccount) {
            return;
        }

        $this->upsertPayableCertificate(
            $companyId,
            (int) $voucher->id,
            (int) $partyAccount->id,
            $ra->tds_section ?? null,
            $ra->tds_rate ?? null,
            $tdsAmount,
            $userId
        );
    }

    protected function resolvePartyAccount(int $companyId, int $partyId): ?Account
    {
        return Account::query()
            ->where('company_id', $companyId)
            ->where('related_model_type', Party::class)
            ->where('related_model_id', $partyId)
            ->first();
    }

    protected function upsertPayableCertificate(
        int $companyId,
        int $voucherId,
        int $partyAccountId,
        ?string $tdsSection,
        $tdsRate,
        float $tdsAmount,
        $userId = null
    ): void {
        $cert = TdsCertificate::firstOrNew([
            'company_id' => $companyId,
            'direction'  => 'payable',
            'voucher_id' => $voucherId,
        ]);

        if (! $cert->exists) {
            $cert->created_by = $userId;
        }

        $rate = (float) ($tdsRate ?? 0);

        $cert->party_account_id = $partyAccountId;
        $cert->tds_section      = $tdsSection ?: null;
        $cert->tds_rate         = $rate > 0 ? $rate : null;
        $cert->tds_amount       = round($tdsAmount, 2);
        $cert->updated_by       = $userId;
        $cert->save();
    }
}
