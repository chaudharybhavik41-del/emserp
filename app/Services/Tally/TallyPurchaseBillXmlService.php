<?php

namespace App\Services\Tally;

use App\Models\Accounting\VoucherLine;
use App\Models\Party;
use App\Models\PurchaseBill;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use RuntimeException;

class TallyPurchaseBillXmlService
{
    public function buildForBill(PurchaseBill $bill): string
    {
        return $this->buildForBills(collect([$bill]));
    }

    public function buildForBills(iterable $bills): string
    {
        $billCollection = collect($bills)
            ->filter(fn ($bill) => $bill instanceof PurchaseBill)
            ->values();

        if ($billCollection->isEmpty()) {
            throw new RuntimeException('At least one posted purchase bill is required for Tally export.');
        }

        $billCollection->each(function (PurchaseBill $bill): void {
            $bill->loadMissing([
                'supplier',
                'voucher.lines.account',
            ]);

            $this->assertExportable($bill);
        });

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $envelope = $doc->createElement('ENVELOPE');
        $doc->appendChild($envelope);

        $header = $envelope->appendChild($doc->createElement('HEADER'));
        $this->appendText($doc, $header, 'VERSION', '1');
        $this->appendText($doc, $header, 'TALLYREQUEST', 'Import Data');
        $this->appendText($doc, $header, 'TYPE', 'Data');
        $this->appendText($doc, $header, 'ID', 'Vouchers');

        $body = $envelope->appendChild($doc->createElement('BODY'));
        $importData = $body->appendChild($doc->createElement('IMPORTDATA'));

        $requestDesc = $importData->appendChild($doc->createElement('REQUESTDESC'));
        $this->appendText($doc, $requestDesc, 'REPORTNAME', 'Vouchers');

        $companyName = trim((string) config('tally.company_name', ''));
        if ($companyName !== '') {
            $staticVariables = $requestDesc->appendChild($doc->createElement('STATICVARIABLES'));
            $this->appendText($doc, $staticVariables, 'SVCURRENTCOMPANY', $companyName);
        }

        $requestData = $importData->appendChild($doc->createElement('REQUESTDATA'));

        foreach ($billCollection as $bill) {
            $this->appendBillVoucher($doc, $requestData, $bill);
        }

        return (string) $doc->saveXML();
    }

    protected function appendBillVoucher(DOMDocument $doc, DOMElement $requestData, PurchaseBill $bill): void
    {
        $voucher = $bill->voucher;
        $voucherLines = $voucher->lines
            ->sortBy('line_no')
            ->values();

        $partyLine = $this->resolvePartyLine($bill, $voucherLines);
        $partyLedgerName = $partyLine?->account?->name
            ?: trim((string) ($bill->supplier?->legal_name ?: $bill->supplier?->name ?: ''));

        if ($partyLedgerName === '') {
            throw new RuntimeException('Unable to resolve the supplier ledger name for purchase bill ' . ($bill->bill_number ?: ('#' . $bill->id)) . '.');
        }

        $voucherDate = ($bill->posting_date ?: $bill->bill_date);
        if (! $voucherDate) {
            throw new RuntimeException('Posting date or bill date is required for Tally export on purchase bill ' . ($bill->bill_number ?: ('#' . $bill->id)) . '.');
        }

        $tallyMessage = $requestData->appendChild($doc->createElement('TALLYMESSAGE'));
        $tallyMessage->setAttribute('xmlns:UDF', 'TallyUDF');

        $voucherNode = $tallyMessage->appendChild($doc->createElement('VOUCHER'));
        $voucherNode->setAttribute('VCHTYPE', (string) config('tally.purchase_voucher_type', 'Purchase'));
        $voucherNode->setAttribute('ACTION', 'Create');
        $voucherNode->setAttribute('OBJVIEW', 'Accounting Voucher View');

        $this->appendText($doc, $voucherNode, 'DATE', $voucherDate->format('Ymd'));
        $this->appendText($doc, $voucherNode, 'EFFECTIVEDATE', $voucherDate->format('Ymd'));
        $this->appendText($doc, $voucherNode, 'VOUCHERTYPENAME', (string) config('tally.purchase_voucher_type', 'Purchase'));
        $this->appendText($doc, $voucherNode, 'VOUCHERNUMBER', (string) ($voucher->voucher_no ?: $bill->bill_number ?: $bill->id));
        $this->appendText($doc, $voucherNode, 'REFERENCE', (string) ($bill->reference_no ?: $bill->bill_number ?: $voucher->voucher_no ?: $bill->id));
        $this->appendText($doc, $voucherNode, 'PARTYLEDGERNAME', $partyLedgerName);
        $this->appendText($doc, $voucherNode, 'PARTYNAME', $partyLedgerName);
        $this->appendText($doc, $voucherNode, 'PERSISTEDVIEW', 'Accounting Voucher View');
        $this->appendText($doc, $voucherNode, 'ISINVOICE', 'No');

        $narration = trim((string) ($voucher->narration ?: ('Purchase Bill ' . ($bill->bill_number ?: ('#' . $bill->id)))));
        if ($narration !== '') {
            $this->appendText($doc, $voucherNode, 'NARRATION', $narration);
        }

        foreach ($voucherLines as $line) {
            $entry = $this->buildLedgerEntry($doc, $line, $partyLine);
            if ($entry) {
                $voucherNode->appendChild($entry);
            }
        }
    }

    protected function buildLedgerEntry(DOMDocument $doc, VoucherLine $line, ?VoucherLine $partyLine): ?DOMElement
    {
        $account = $line->account;
        if (! $account || trim((string) $account->name) === '') {
            return null;
        }

        $debit = (float) ($line->debit ?? 0);
        $credit = (float) ($line->credit ?? 0);
        if ($debit <= 0 && $credit <= 0) {
            return null;
        }

        $isDebit = $debit > 0;
        $signedAmount = $isDebit ? -$debit : $credit;

        $entry = $doc->createElement('ALLLEDGERENTRIES.LIST');
        $this->appendText($doc, $entry, 'LEDGERNAME', (string) $account->name);
        $this->appendText($doc, $entry, 'ISDEEMEDPOSITIVE', $isDebit ? 'Yes' : 'No');

        if ($partyLine && $partyLine->is($line)) {
            $this->appendText($doc, $entry, 'ISPARTYLEDGER', 'Yes');
        }

        $this->appendText($doc, $entry, 'AMOUNT', $this->formatAmount($signedAmount));

        return $entry;
    }

    protected function resolvePartyLine(PurchaseBill $bill, Collection $voucherLines): ?VoucherLine
    {
        $supplierId = (int) ($bill->supplier_id ?? 0);

        $line = $voucherLines->first(function (VoucherLine $line) use ($supplierId) {
            $account = $line->account;

            return $account
                && $supplierId > 0
                && (string) $account->related_model_type === Party::class
                && (int) $account->related_model_id === $supplierId;
        });

        if ($line instanceof VoucherLine) {
            return $line;
        }

        return $voucherLines
            ->filter(fn (VoucherLine $line) => (float) ($line->credit ?? 0) > 0)
            ->sortByDesc(fn (VoucherLine $line) => (float) ($line->credit ?? 0))
            ->first();
    }

    protected function assertExportable(PurchaseBill $bill): void
    {
        if (($bill->status ?? null) !== 'posted') {
            throw new RuntimeException('Only posted purchase bills can be exported to Tally.');
        }

        if (! $bill->voucher || ($bill->voucher->status ?? null) !== 'posted') {
            throw new RuntimeException('The linked accounting voucher is missing or not posted for purchase bill ' . ($bill->bill_number ?: ('#' . $bill->id)) . '.');
        }

        if ($bill->voucher->lines->isEmpty()) {
            throw new RuntimeException('The linked accounting voucher has no lines for purchase bill ' . ($bill->bill_number ?: ('#' . $bill->id)) . '.');
        }
    }

    protected function appendText(DOMDocument $doc, DOMElement $parent, string $name, string $value): DOMElement
    {
        $node = $doc->createElement($name);
        $node->appendChild($doc->createTextNode($value));
        $parent->appendChild($node);

        return $node;
    }

    protected function formatAmount(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
