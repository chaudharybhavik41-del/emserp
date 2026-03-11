<?php

namespace App\Services\Purchase;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountBillAllocation;
use App\Models\Accounting\TdsCertificate;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use App\Models\PurchaseBill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseTdsRoundingRepairService
{
    protected array $accountCache = [];

    public function dryRun(array $filters = []): array
    {
        return $this->buildSummary($filters);
    }

    public function apply(array $filters = []): array
    {
        $summary = $this->buildSummary($filters);
        $applied = [];

        foreach ($summary['purchase_bill_ids'] as $billId) {
            try {
                $bill = $this->repairBill((int) $billId);
                if ($bill) {
                    $applied[] = (string) ($bill->bill_number ?: ('#' . $bill->id));
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = 'Bill #' . $billId . ': ' . $e->getMessage();
            }
        }

        $summary['applied_purchase_bills'] = $applied;
        $summary['applied_purchase_bill_count'] = count($applied);

        return $summary;
    }

    protected function buildSummary(array $filters): array
    {
        $rows = $this->affectedBills($filters);
        $billIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $billNumbers = $rows->pluck('bill_number')->filter()->values()->all();

        return [
            'purchase_bill_rows' => $rows->count(),
            'purchase_bill_ids' => $billIds,
            'purchase_bill_numbers' => $billNumbers,
            'errors' => [],
        ];
    }

    protected function repairBill(int $billId): ?PurchaseBill
    {
        return DB::transaction(function () use ($billId) {
            $bill = PurchaseBill::query()
                ->with('voucher')
                ->lockForUpdate()
                ->find($billId);

            if (! $bill) {
                return null;
            }

            $oldValues = $bill->getAttributes();
            $oldTds = (float) ($bill->tds_amount ?? 0);
            $newTds = $this->roundedTdsForBill($bill);

            if (abs($oldTds - $newTds) < 0.01) {
                return $bill;
            }

            $oldNetPayable = $this->netPayableForBill($bill, $oldTds);
            $newNetPayable = $this->netPayableForBill($bill, $newTds);

            if ($bill->status === 'posted') {
                $allocated = $this->postedAllocatedAmount($bill);
                if ($allocated - $newNetPayable > 0.0001) {
                    throw new RuntimeException(
                        'Skipping because posted allocations ' . number_format($allocated, 2, '.', '')
                        . ' exceed repaired net payable ' . number_format($newNetPayable, 2, '.', '')
                    );
                }
            }

            DB::table('purchase_bills')
                ->where('id', $bill->id)
                ->update([
                    'tds_amount' => $newTds,
                    'updated_at' => now(),
                ]);

            $bill = $bill->fresh('voucher');

            if ($bill->status === 'posted' && $bill->voucher_id && $bill->voucher) {
                $this->syncPostedVoucher($bill, $newTds, $newNetPayable);
                $this->syncPayableTdsCertificate($bill, $newTds);
            }

            ActivityLog::logUpdated(
                $bill,
                $oldValues,
                'Rounded TDS amount on purchase bill ' . ($bill->bill_number ?: ('#' . $bill->id))
            );

            return $bill;
        });
    }

    protected function syncPostedVoucher(PurchaseBill $bill, float $tdsAmount, float $supplierCredit): void
    {
        $voucher = $bill->voucher;
        if (! $voucher) {
            return;
        }

        $supplierLine = VoucherLine::query()
            ->where('voucher_id', $voucher->id)
            ->where('description', 'Supplier - ' . $bill->bill_number)
            ->first();

        if (! $supplierLine) {
            throw new RuntimeException('Supplier voucher line not found.');
        }

        $supplierLine->debit = 0;
        $supplierLine->credit = round($supplierCredit, 2);
        $supplierLine->save();

        $tdsDescription = 'TDS Payable - ' . $bill->bill_number;
        $tdsLine = VoucherLine::query()
            ->where('voucher_id', $voucher->id)
            ->where('description', $tdsDescription)
            ->first();

        if ($tdsAmount > 0.0001) {
            $tdsAccount = $this->resolveConfigAccount('accounting.tds.tds_payable_account_code', 'TDS Payable');

            if ($tdsLine) {
                $tdsLine->account_id = $tdsAccount->id;
                $tdsLine->debit = 0;
                $tdsLine->credit = round($tdsAmount, 2);
                $tdsLine->save();
            } else {
                $lineNo = (int) VoucherLine::query()
                    ->where('voucher_id', $voucher->id)
                    ->max('line_no') + 1;

                VoucherLine::create([
                    'voucher_id' => $voucher->id,
                    'line_no' => $lineNo,
                    'account_id' => $tdsAccount->id,
                    'cost_center_id' => $voucher->cost_center_id,
                    'description' => $tdsDescription,
                    'debit' => 0,
                    'credit' => round($tdsAmount, 2),
                ]);
            }
        } elseif ($tdsLine) {
            $tdsLine->delete();
        }
    }

    protected function syncPayableTdsCertificate(PurchaseBill $bill, float $tdsAmount): void
    {
        if (! $bill->voucher_id) {
            return;
        }

        $certificate = TdsCertificate::query()
            ->where('company_id', (int) $bill->company_id)
            ->where('direction', 'payable')
            ->where('voucher_id', (int) $bill->voucher_id)
            ->first();

        if ($tdsAmount <= 0.0001) {
            if ($certificate) {
                $certificate->delete();
            }
            return;
        }

        if (! $certificate) {
            return;
        }

        $certificate->tds_amount = round($tdsAmount, 2);
        $certificate->tds_rate = (float) ($bill->tds_rate ?? 0) > 0 ? (float) $bill->tds_rate : null;
        $certificate->tds_section = $bill->tds_section ?: null;
        $certificate->updated_by = auth()->id() ?: $certificate->updated_by;
        $certificate->save();
    }

    protected function postedAllocatedAmount(PurchaseBill $bill): float
    {
        return round((float) AccountBillAllocation::query()
            ->join('vouchers', 'vouchers.id', '=', 'account_bill_allocations.voucher_id')
            ->where('account_bill_allocations.company_id', (int) $bill->company_id)
            ->where('account_bill_allocations.bill_type', PurchaseBill::class)
            ->where('account_bill_allocations.bill_id', (int) $bill->id)
            ->where('account_bill_allocations.mode', 'against')
            ->where('vouchers.status', 'posted')
            ->sum('account_bill_allocations.amount'), 2);
    }

    protected function netPayableForBill(PurchaseBill $bill, float $tdsAmount): float
    {
        return round(
            (float) ($bill->total_amount ?? 0)
            + (float) ($bill->tcs_amount ?? 0)
            - $tdsAmount,
            2
        );
    }

    protected function roundedTdsForBill(PurchaseBill $bill): float
    {
        $rate = (float) ($bill->tds_rate ?? 0);
        $amount = (float) ($bill->tds_amount ?? 0);

        if ($rate <= 0 && $amount <= 0) {
            return 0.0;
        }

        if ($rate <= 0) {
            return 0.0;
        }

        return (float) round(max(0, $amount), 0);
    }

    protected function affectedBills(array $filters): Collection
    {
        $query = PurchaseBill::query()
            ->select(['id', 'bill_number', 'status', 'tds_rate', 'tds_amount'])
            ->where(function ($builder) {
                $builder
                    ->whereRaw('ABS(COALESCE(tds_amount, 0) - ROUND(COALESCE(tds_amount, 0), 0)) >= 0.01')
                    ->orWhere(function ($inner) {
                        $inner->whereRaw('COALESCE(tds_rate, 0) <= 0')
                            ->whereRaw('COALESCE(tds_amount, 0) > 0');
                    });
            })
            ->orderBy('id');

        $billIds = collect($filters['bill_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->all();
        $billNumbers = collect($filters['bill_numbers'] ?? [])->filter()->values()->all();

        if (! empty($billIds)) {
            $query->whereIn('id', $billIds);
        }
        if (! empty($billNumbers)) {
            $query->whereIn('bill_number', $billNumbers);
        }

        return $query->get();
    }

    protected function resolveConfigAccount(string $configKey, string $label): Account
    {
        if (isset($this->accountCache[$configKey])) {
            return $this->accountCache[$configKey];
        }

        $code = Config::get($configKey);
        if (! $code) {
            throw new RuntimeException($label . ' account code is not configured.');
        }

        $account = Account::query()->where('code', $code)->first();
        if (! $account) {
            throw new RuntimeException($label . ' account not found for code: ' . $code);
        }

        return $this->accountCache[$configKey] = $account;
    }
}
