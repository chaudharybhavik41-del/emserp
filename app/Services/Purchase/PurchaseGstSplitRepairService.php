<?php

namespace App\Services\Purchase;

use App\Models\Accounting\Account;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Services\Accounting\ProjectCostCenterResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PurchaseGstSplitRepairService
{
    protected array $columnCache = [];

    protected array $accountCache = [];

    public function dryRun(array $filters = []): array
    {
        return $this->buildSummary($filters);
    }

    public function apply(array $filters = []): array
    {
        $summary = $this->buildSummary($filters);

        $poCodes = [];
        foreach ($summary['purchase_order_ids'] as $poId) {
            try {
                $po = $this->repairPurchaseOrder((int) $poId);
                if ($po) {
                    $poCodes[] = (string) ($po->code ?: ('#' . $po->id));
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = 'PO #' . $poId . ': ' . $e->getMessage();
            }
        }

        $billNumbers = [];
        foreach ($summary['purchase_bill_ids'] as $billId) {
            try {
                $bill = $this->repairPurchaseBill((int) $billId);
                if ($bill) {
                    $billNumbers[] = (string) ($bill->bill_number ?: ('#' . $bill->id));
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = 'Bill #' . $billId . ': ' . $e->getMessage();
            }
        }

        $summary['applied_purchase_orders'] = $poCodes;
        $summary['applied_purchase_bills'] = $billNumbers;
        $summary['applied_purchase_order_count'] = count($poCodes);
        $summary['applied_purchase_bill_count'] = count($billNumbers);

        return $summary;
    }

    protected function buildSummary(array $filters = []): array
    {
        $poRows = $this->affectedPurchaseOrderRows($filters);
        $billLineRows = $this->affectedPurchaseBillLineRows($filters);
        $billExpenseRows = $this->affectedPurchaseBillExpenseRows($filters);

        $purchaseOrderIds = $poRows->pluck('purchase_order_id')->filter()->unique()->values()->map(fn ($id) => (int) $id)->all();
        $purchaseBillIds = $billLineRows->pluck('purchase_bill_id')
            ->merge($billExpenseRows->pluck('purchase_bill_id'))
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();

        $purchaseOrderCodes = PurchaseOrder::query()
            ->whereIn('id', $purchaseOrderIds)
            ->orderBy('id')
            ->pluck('code')
            ->filter()
            ->values()
            ->all();

        $purchaseBillNumbers = PurchaseBill::query()
            ->whereIn('id', $purchaseBillIds)
            ->orderBy('id')
            ->pluck('bill_number')
            ->filter()
            ->values()
            ->all();

        return [
            'purchase_order_item_rows' => $poRows->count(),
            'purchase_bill_line_rows' => $billLineRows->count(),
            'purchase_bill_expense_rows' => $billExpenseRows->count(),
            'purchase_order_ids' => $purchaseOrderIds,
            'purchase_bill_ids' => $purchaseBillIds,
            'purchase_order_codes' => $purchaseOrderCodes,
            'purchase_bill_numbers' => $purchaseBillNumbers,
            'errors' => [],
        ];
    }

    protected function repairPurchaseOrder(int $purchaseOrderId): ?PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrderId) {
            $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->find($purchaseOrderId);
            if (! $purchaseOrder) {
                return null;
            }

            $rows = DB::table('purchase_order_items')
                ->where('purchase_order_id', $purchaseOrder->id)
                ->whereRaw('COALESCE(igst_amount, 0) = 0')
                ->whereRaw('ABS(COALESCE(cgst_amount, 0) - COALESCE(sgst_amount, 0)) >= 0.01')
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                return $purchaseOrder;
            }

            foreach ($rows as $row) {
                $taxable = $this->purchaseOrderItemTaxable($row);
                $taxRate = (float) ($row->tax_percent ?? 0);

                if ($taxable <= 0 || $taxRate <= 0) {
                    continue;
                }

                $split = $this->calculateIntraSplit($taxable, $taxRate);

                $updates = [
                    'cgst_amount' => $split['cgst_amount'],
                    'sgst_amount' => $split['sgst_amount'],
                    'igst_amount' => 0.0,
                    'updated_at' => now(),
                ];

                if ($this->hasColumn('purchase_order_items', 'cgst_percent')) {
                    $updates['cgst_percent'] = $split['half_percent'];
                }
                if ($this->hasColumn('purchase_order_items', 'sgst_percent')) {
                    $updates['sgst_percent'] = $split['half_percent'];
                }
                if ($this->hasColumn('purchase_order_items', 'igst_percent')) {
                    $updates['igst_percent'] = 0.0;
                }
                if ($this->hasColumn('purchase_order_items', 'tax_amount')) {
                    $updates['tax_amount'] = $split['tax_amount'];
                }
                if ($this->hasColumn('purchase_order_items', 'basic_amount')) {
                    $updates['basic_amount'] = round($taxable, 2);
                }
                if ($this->hasColumn('purchase_order_items', 'total_amount')) {
                    $updates['total_amount'] = round($taxable + $split['tax_amount'], 2);
                }
                if ($this->hasColumn('purchase_order_items', 'net_amount')) {
                    $updates['net_amount'] = round($taxable + $split['tax_amount'], 2);
                }

                DB::table('purchase_order_items')
                    ->where('id', $row->id)
                    ->update($updates);
            }

            $sumColumn = $this->hasColumn('purchase_order_items', 'total_amount') ? 'total_amount' : 'net_amount';
            $purchaseOrder->total_amount = round((float) DB::table('purchase_order_items')
                ->where('purchase_order_id', $purchaseOrder->id)
                ->sum($sumColumn), 2);
            $purchaseOrder->save();

            ActivityLog::logCustom(
                'gst_split_repaired',
                'Corrected intra-state GST split rounding on purchase order ' . ($purchaseOrder->code ?: ('#' . $purchaseOrder->id)),
                $purchaseOrder,
                [
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_code' => $purchaseOrder->code,
                ]
            );

            return $purchaseOrder->fresh();
        });
    }

    protected function repairPurchaseBill(int $purchaseBillId): ?PurchaseBill
    {
        return DB::transaction(function () use ($purchaseBillId) {
            $bill = PurchaseBill::query()
                ->with(['lines', 'expenseLines', 'voucher', 'purchaseOrder'])
                ->lockForUpdate()
                ->find($purchaseBillId);

            if (! $bill) {
                return null;
            }

            $changed = false;

            foreach ($bill->lines as $line) {
                if ((float) ($line->igst_amount ?? 0) != 0.0) {
                    continue;
                }
                if (abs((float) ($line->cgst_amount ?? 0) - (float) ($line->sgst_amount ?? 0)) < 0.01) {
                    continue;
                }

                $taxable = (float) ($line->basic_amount ?? 0);
                $taxRate = (float) ($line->tax_rate ?? 0);
                if ($taxable <= 0 || $taxRate <= 0) {
                    continue;
                }

                $split = $this->calculateIntraSplit($taxable, $taxRate);
                $line->tax_amount = $split['tax_amount'];
                $line->cgst_amount = $split['cgst_amount'];
                $line->sgst_amount = $split['sgst_amount'];
                $line->igst_amount = 0.0;
                $line->total_amount = round($taxable + $split['tax_amount'], 2);
                $line->save();
                $changed = true;
            }

            foreach ($bill->expenseLines as $expenseLine) {
                if ((float) ($expenseLine->igst_amount ?? 0) != 0.0) {
                    continue;
                }
                if (abs((float) ($expenseLine->cgst_amount ?? 0) - (float) ($expenseLine->sgst_amount ?? 0)) < 0.01) {
                    continue;
                }

                $taxable = (float) ($expenseLine->basic_amount ?? 0);
                $taxRate = (float) ($expenseLine->tax_rate ?? 0);
                if ($taxable <= 0 || $taxRate <= 0) {
                    continue;
                }

                $split = $this->calculateIntraSplit($taxable, $taxRate);
                $expenseLine->tax_amount = $split['tax_amount'];
                $expenseLine->cgst_amount = $split['cgst_amount'];
                $expenseLine->sgst_amount = $split['sgst_amount'];
                $expenseLine->igst_amount = 0.0;
                $expenseLine->total_amount = $this->expenseLineInvoiceAmount($expenseLine, $split['tax_amount']);
                $expenseLine->save();
                $changed = true;
            }

            if (! $changed) {
                return $bill;
            }

            $oldValues = $bill->getOriginal();
            $header = $this->calculateBillHeader($bill->fresh(['lines', 'expenseLines']));

            $billUpdates = [
                'total_basic' => $header['total_basic'],
                'total_discount' => $header['total_discount'],
                'total_tax' => $header['total_tax'],
                'total_cgst' => $header['total_cgst'],
                'total_sgst' => $header['total_sgst'],
                'total_igst' => $header['total_igst'],
                'total_rcm_tax' => $header['total_rcm_tax'],
                'total_rcm_cgst' => $header['total_rcm_cgst'],
                'total_rcm_sgst' => $header['total_rcm_sgst'],
                'total_rcm_igst' => $header['total_rcm_igst'],
                'round_off' => round(((float) $bill->total_amount) - $header['calculated_invoice_total'], 2),
                'updated_at' => now(),
            ];

            DB::table('purchase_bills')
                ->where('id', $bill->id)
                ->update($billUpdates);

            $bill = $bill->fresh(['lines', 'expenseLines', 'purchaseOrder', 'voucher']);

            if ($bill->status === 'posted' && $bill->voucher_id && $bill->voucher) {
                $this->syncPurchaseBillVoucherTaxLines($bill);
            }

            ActivityLog::logUpdated(
                $bill,
                $oldValues,
                'Corrected intra-state GST split rounding on purchase bill ' . ($bill->bill_number ?: ('#' . $bill->id))
            );

            return $bill->fresh(['lines', 'expenseLines', 'voucher']);
        });
    }

    protected function syncPurchaseBillVoucherTaxLines(PurchaseBill $bill): void
    {
        $voucher = $bill->voucher;
        if (! $voucher) {
            return;
        }

        $descriptions = [
            'Input CGST - ' . $bill->bill_number,
            'Input SGST - ' . $bill->bill_number,
            'Input IGST - ' . $bill->bill_number,
            'Input CGST (RCM) - ' . $bill->bill_number,
            'Input SGST (RCM) - ' . $bill->bill_number,
            'Input IGST (RCM) - ' . $bill->bill_number,
            'Output CGST (RCM) - ' . $bill->bill_number,
            'Output SGST (RCM) - ' . $bill->bill_number,
            'Output IGST (RCM) - ' . $bill->bill_number,
            'Round Off - ' . $bill->bill_number,
        ];

        VoucherLine::query()
            ->where('voucher_id', $voucher->id)
            ->whereIn('description', $descriptions)
            ->delete();

        $lineNo = (int) VoucherLine::query()
            ->where('voucher_id', $voucher->id)
            ->max('line_no');
        $lineNo++;

        $voucherCostCenterId = $voucher->cost_center_id;

        $regularTaxLines = [
            [
                'amount' => (float) $bill->total_cgst,
                'config_key' => 'accounting.gst.input_cgst_account_code',
                'label' => 'GST Input CGST',
                'description' => 'Input CGST - ' . $bill->bill_number,
                'debit' => true,
                'cost_center_id' => $voucherCostCenterId,
            ],
            [
                'amount' => (float) $bill->total_sgst,
                'config_key' => 'accounting.gst.input_sgst_account_code',
                'label' => 'GST Input SGST',
                'description' => 'Input SGST - ' . $bill->bill_number,
                'debit' => true,
                'cost_center_id' => $voucherCostCenterId,
            ],
            [
                'amount' => (float) $bill->total_igst,
                'config_key' => 'accounting.gst.input_igst_account_code',
                'label' => 'GST Input IGST',
                'description' => 'Input IGST - ' . $bill->bill_number,
                'debit' => true,
                'cost_center_id' => $voucherCostCenterId,
            ],
        ];

        foreach ($regularTaxLines as $line) {
            if (($line['amount'] ?? 0) <= 0.0001) {
                continue;
            }

            $this->createVoucherLine(
                $voucher->id,
                $lineNo++,
                $this->resolveConfigAccount($line['config_key'], $line['label']),
                $line['description'],
                (float) $line['amount'],
                true,
                $line['cost_center_id']
            );
        }

        foreach ($this->buildRcmVoucherLines($bill, (int) $voucher->company_id) as $line) {
            if (($line['amount'] ?? 0) <= 0.0001) {
                continue;
            }

            $this->createVoucherLine(
                $voucher->id,
                $lineNo++,
                $line['account'],
                $line['description'],
                (float) $line['amount'],
                (bool) $line['is_debit'],
                $line['cost_center_id'] ?? null
            );
        }

        if (abs((float) ($bill->round_off ?? 0)) > 0.0001) {
            $roundOffAccount = $this->resolveConfigAccount(
                'accounting.round_off.round_off_account_code',
                'Round Off',
                'ROUND-OFF'
            );

            $this->createVoucherLine(
                $voucher->id,
                $lineNo++,
                $roundOffAccount,
                'Round Off - ' . $bill->bill_number,
                abs((float) $bill->round_off),
                (float) $bill->round_off > 0,
                $voucherCostCenterId
            );
        }
    }

    protected function buildRcmVoucherLines(PurchaseBill $bill, int $companyId): array
    {
        $totalRcm = (float) ($bill->total_rcm_cgst ?? 0)
            + (float) ($bill->total_rcm_sgst ?? 0)
            + (float) ($bill->total_rcm_igst ?? 0);

        if ($totalRcm <= 0.0001) {
            return [];
        }

        $needsCgst = (float) ($bill->total_rcm_cgst ?? 0) > 0.0001;
        $needsSgst = (float) ($bill->total_rcm_sgst ?? 0) > 0.0001;
        $needsIgst = (float) ($bill->total_rcm_igst ?? 0) > 0.0001;

        $inputCgst = $needsCgst ? $this->resolveConfigAccount('accounting.gst.input_cgst_account_code', 'GST Input CGST') : null;
        $inputSgst = $needsSgst ? $this->resolveConfigAccount('accounting.gst.input_sgst_account_code', 'GST Input SGST') : null;
        $inputIgst = $needsIgst ? $this->resolveConfigAccount('accounting.gst.input_igst_account_code', 'GST Input IGST') : null;
        $outputCgst = $needsCgst ? $this->resolveConfigAccount('accounting.gst.cgst_output_account_code', 'GST Output CGST') : null;
        $outputSgst = $needsSgst ? $this->resolveConfigAccount('accounting.gst.sgst_output_account_code', 'GST Output SGST') : null;
        $outputIgst = $needsIgst ? $this->resolveConfigAccount('accounting.gst.igst_output_account_code', 'GST Output IGST') : null;

        $voucher = $bill->voucher;
        if ($voucher && $voucher->cost_center_id) {
            return $this->buildSingleCostCenterRcmVoucherLines(
                $bill,
                $voucher->cost_center_id,
                $inputCgst,
                $inputSgst,
                $inputIgst,
                $outputCgst,
                $outputSgst,
                $outputIgst
            );
        }

        $billProjectId = (int) ($bill->project_id ?: ($bill->purchaseOrder?->project_id ?? 0));
        $grouped = [];

        foreach ($bill->expenseLines as $expenseLine) {
            if (! $this->expenseLineIsReverseCharge($expenseLine)) {
                continue;
            }

            $projectId = (int) ($expenseLine->project_id ?: $billProjectId ?: 0);
            $key = (string) $projectId;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'project_id' => $projectId,
                    'cgst' => 0.0,
                    'sgst' => 0.0,
                    'igst' => 0.0,
                ];
            }

            $grouped[$key]['cgst'] += (float) ($expenseLine->cgst_amount ?? 0);
            $grouped[$key]['sgst'] += (float) ($expenseLine->sgst_amount ?? 0);
            $grouped[$key]['igst'] += (float) ($expenseLine->igst_amount ?? 0);
        }

        if (empty($grouped)) {
            $grouped['0'] = [
                'project_id' => 0,
                'cgst' => (float) ($bill->total_rcm_cgst ?? 0),
                'sgst' => (float) ($bill->total_rcm_sgst ?? 0),
                'igst' => (float) ($bill->total_rcm_igst ?? 0),
            ];
        }

        $lines = [];
        foreach ($grouped as $group) {
            $projectId = (int) ($group['project_id'] ?? 0);
            $costCenterId = $projectId > 0
                ? ProjectCostCenterResolver::resolveId($companyId, $projectId)
                : null;

            $lines = array_merge($lines, $this->buildRcmLinesForAmounts(
                $bill,
                $costCenterId,
                (float) ($group['cgst'] ?? 0),
                (float) ($group['sgst'] ?? 0),
                (float) ($group['igst'] ?? 0),
                $inputCgst,
                $inputSgst,
                $inputIgst,
                $outputCgst,
                $outputSgst,
                $outputIgst
            ));
        }

        return $lines;
    }

    protected function buildSingleCostCenterRcmVoucherLines(
        PurchaseBill $bill,
        ?int $costCenterId,
        ?Account $inputCgst,
        ?Account $inputSgst,
        ?Account $inputIgst,
        ?Account $outputCgst,
        ?Account $outputSgst,
        ?Account $outputIgst
    ): array {
        return $this->buildRcmLinesForAmounts(
            $bill,
            $costCenterId,
            (float) ($bill->total_rcm_cgst ?? 0),
            (float) ($bill->total_rcm_sgst ?? 0),
            (float) ($bill->total_rcm_igst ?? 0),
            $inputCgst,
            $inputSgst,
            $inputIgst,
            $outputCgst,
            $outputSgst,
            $outputIgst
        );
    }

    protected function buildRcmLinesForAmounts(
        PurchaseBill $bill,
        ?int $costCenterId,
        float $cgst,
        float $sgst,
        float $igst,
        ?Account $inputCgst,
        ?Account $inputSgst,
        ?Account $inputIgst,
        ?Account $outputCgst,
        ?Account $outputSgst,
        ?Account $outputIgst
    ): array {
        $lines = [];

        if ($cgst > 0.0001 && $inputCgst && $outputCgst) {
            $lines[] = [
                'account' => $inputCgst,
                'description' => 'Input CGST (RCM) - ' . $bill->bill_number,
                'amount' => round($cgst, 2),
                'is_debit' => true,
                'cost_center_id' => $costCenterId,
            ];
            $lines[] = [
                'account' => $outputCgst,
                'description' => 'Output CGST (RCM) - ' . $bill->bill_number,
                'amount' => round($cgst, 2),
                'is_debit' => false,
                'cost_center_id' => $costCenterId,
            ];
        }

        if ($sgst > 0.0001 && $inputSgst && $outputSgst) {
            $lines[] = [
                'account' => $inputSgst,
                'description' => 'Input SGST (RCM) - ' . $bill->bill_number,
                'amount' => round($sgst, 2),
                'is_debit' => true,
                'cost_center_id' => $costCenterId,
            ];
            $lines[] = [
                'account' => $outputSgst,
                'description' => 'Output SGST (RCM) - ' . $bill->bill_number,
                'amount' => round($sgst, 2),
                'is_debit' => false,
                'cost_center_id' => $costCenterId,
            ];
        }

        if ($igst > 0.0001 && $inputIgst && $outputIgst) {
            $lines[] = [
                'account' => $inputIgst,
                'description' => 'Input IGST (RCM) - ' . $bill->bill_number,
                'amount' => round($igst, 2),
                'is_debit' => true,
                'cost_center_id' => $costCenterId,
            ];
            $lines[] = [
                'account' => $outputIgst,
                'description' => 'Output IGST (RCM) - ' . $bill->bill_number,
                'amount' => round($igst, 2),
                'is_debit' => false,
                'cost_center_id' => $costCenterId,
            ];
        }

        return $lines;
    }

    protected function createVoucherLine(
        int $voucherId,
        int $lineNo,
        Account $account,
        string $description,
        float $amount,
        bool $isDebit,
        ?int $costCenterId = null
    ): void {
        VoucherLine::create([
            'voucher_id' => $voucherId,
            'line_no' => $lineNo,
            'account_id' => $account->id,
            'cost_center_id' => $costCenterId,
            'description' => $description,
            'debit' => $isDebit ? round($amount, 2) : 0,
            'credit' => $isDebit ? 0 : round($amount, 2),
        ]);
    }

    protected function calculateBillHeader(PurchaseBill $bill): array
    {
        $totals = [
            'total_basic' => 0.0,
            'total_discount' => 0.0,
            'total_tax' => 0.0,
            'total_cgst' => 0.0,
            'total_sgst' => 0.0,
            'total_igst' => 0.0,
            'total_rcm_tax' => 0.0,
            'total_rcm_cgst' => 0.0,
            'total_rcm_sgst' => 0.0,
            'total_rcm_igst' => 0.0,
            'calculated_invoice_total' => 0.0,
        ];

        foreach ($bill->lines as $line) {
            $totals['total_basic'] += (float) ($line->basic_amount ?? 0);
            $totals['total_discount'] += (float) ($line->discount_amount ?? 0);
            $totals['total_tax'] += (float) ($line->tax_amount ?? 0);
            $totals['total_cgst'] += (float) ($line->cgst_amount ?? 0);
            $totals['total_sgst'] += (float) ($line->sgst_amount ?? 0);
            $totals['total_igst'] += (float) ($line->igst_amount ?? 0);
            $totals['calculated_invoice_total'] += (float) ($line->total_amount ?? 0);
        }

        foreach ($bill->expenseLines as $expenseLine) {
            $totals['total_basic'] += (float) ($expenseLine->basic_amount ?? 0);

            if ($this->expenseLineIsReverseCharge($expenseLine)) {
                $totals['total_rcm_tax'] += (float) ($expenseLine->tax_amount ?? 0);
                $totals['total_rcm_cgst'] += (float) ($expenseLine->cgst_amount ?? 0);
                $totals['total_rcm_sgst'] += (float) ($expenseLine->sgst_amount ?? 0);
                $totals['total_rcm_igst'] += (float) ($expenseLine->igst_amount ?? 0);
            } else {
                $totals['total_tax'] += (float) ($expenseLine->tax_amount ?? 0);
                $totals['total_cgst'] += (float) ($expenseLine->cgst_amount ?? 0);
                $totals['total_sgst'] += (float) ($expenseLine->sgst_amount ?? 0);
                $totals['total_igst'] += (float) ($expenseLine->igst_amount ?? 0);
            }

            $totals['calculated_invoice_total'] += (float) ($expenseLine->total_amount ?? 0);
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return $totals;
    }

    protected function affectedPurchaseOrderRows(array $filters): Collection
    {
        $query = DB::table('purchase_order_items as poi')
            ->select('poi.id', 'poi.purchase_order_id')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->whereRaw('COALESCE(poi.igst_amount, 0) = 0')
            ->whereRaw('ABS(COALESCE(poi.cgst_amount, 0) - COALESCE(poi.sgst_amount, 0)) >= 0.01');

        $this->applyPurchaseOrderFilters($query, $filters);

        return $query->orderBy('poi.id')->get();
    }

    protected function affectedPurchaseBillLineRows(array $filters): Collection
    {
        $query = DB::table('purchase_bill_lines as pbl')
            ->select('pbl.id', 'pbl.purchase_bill_id')
            ->join('purchase_bills as pb', 'pb.id', '=', 'pbl.purchase_bill_id')
            ->whereRaw('COALESCE(pbl.igst_amount, 0) = 0')
            ->whereRaw('ABS(COALESCE(pbl.cgst_amount, 0) - COALESCE(pbl.sgst_amount, 0)) >= 0.01');

        $this->applyPurchaseBillFilters($query, $filters);

        return $query->orderBy('pbl.id')->get();
    }

    protected function affectedPurchaseBillExpenseRows(array $filters): Collection
    {
        if (! Schema::hasTable('purchase_bill_expense_lines')) {
            return collect();
        }

        $query = DB::table('purchase_bill_expense_lines as pbe')
            ->select('pbe.id', 'pbe.purchase_bill_id')
            ->join('purchase_bills as pb', 'pb.id', '=', 'pbe.purchase_bill_id')
            ->whereRaw('COALESCE(pbe.igst_amount, 0) = 0')
            ->whereRaw('ABS(COALESCE(pbe.cgst_amount, 0) - COALESCE(pbe.sgst_amount, 0)) >= 0.01');

        $this->applyPurchaseBillFilters($query, $filters);

        return $query->orderBy('pbe.id')->get();
    }

    protected function applyPurchaseOrderFilters($query, array $filters): void
    {
        $poIds = collect($filters['po_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->all();
        $poCodes = collect($filters['po_codes'] ?? [])->filter()->values()->all();

        if (! empty($poIds)) {
            $query->whereIn('poi.purchase_order_id', $poIds);
        }
        if (! empty($poCodes)) {
            $query->whereIn('po.code', $poCodes);
        }
    }

    protected function applyPurchaseBillFilters($query, array $filters): void
    {
        $billIds = collect($filters['bill_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->all();
        $billNumbers = collect($filters['bill_numbers'] ?? [])->filter()->values()->all();

        if (! empty($billIds)) {
            $query->whereIn('pb.id', $billIds);
        }
        if (! empty($billNumbers)) {
            $query->whereIn('pb.bill_number', $billNumbers);
        }
    }

    protected function purchaseOrderItemTaxable(object $row): float
    {
        if (property_exists($row, 'amount') && $row->amount !== null) {
            return round((float) $row->amount, 2);
        }

        $quantity = (float) ($row->quantity ?? 0);
        $rate = (float) ($row->rate ?? 0);
        $discountPercent = (float) ($row->discount_percent ?? 0);
        $gross = $quantity * $rate;
        $discount = round(($gross * $discountPercent) / 100, 2);

        return round($gross - $discount, 2);
    }

    protected function calculateIntraSplit(float $taxable, float $taxRate): array
    {
        $taxable = round($taxable, 2);
        $halfPercent = round($taxRate / 2, 2);
        $cgstAmount = round(($taxable * $halfPercent) / 100, 2);
        $sgstAmount = round(($taxable * $halfPercent) / 100, 2);

        return [
            'half_percent' => $halfPercent,
            'cgst_amount' => $cgstAmount,
            'sgst_amount' => $sgstAmount,
            'tax_amount' => round($cgstAmount + $sgstAmount, 2),
        ];
    }

    protected function expenseLineInvoiceAmount(object $expenseLine, float $taxAmount): float
    {
        $basic = (float) ($expenseLine->basic_amount ?? 0);

        if ($this->expenseLineIsReverseCharge($expenseLine)) {
            return round($basic, 2);
        }

        return round($basic + $taxAmount, 2);
    }

    protected function expenseLineIsReverseCharge(object $expenseLine): bool
    {
        return (bool) ($expenseLine->is_reverse_charge ?? false);
    }

    protected function resolveConfigAccount(string $configKey, string $label, ?string $defaultCode = null): Account
    {
        $code = Config::get($configKey, $defaultCode);
        $cacheKey = $configKey . '|' . (string) $code;

        if (isset($this->accountCache[$cacheKey])) {
            return $this->accountCache[$cacheKey];
        }

        if (! $code) {
            throw new RuntimeException($label . ' account code is not configured.');
        }

        $account = Account::query()->where('code', $code)->first();
        if (! $account) {
            throw new RuntimeException($label . ' account not found for code: ' . $code);
        }

        return $this->accountCache[$cacheKey] = $account;
    }

    protected function hasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;

        if (! array_key_exists($cacheKey, $this->columnCache)) {
            $this->columnCache[$cacheKey] = Schema::hasColumn($table, $column);
        }

        return $this->columnCache[$cacheKey];
    }
}
