<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Item;
use App\Models\Project;
use App\Models\PurchaseBillLine;
use App\Models\StoreStockItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryValuationReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.reports.view')->only(['index']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    /**
     * Inventory Valuation + Reconciliation report.
     *
     * What it does:
     * - Reads live available stock from store_stock_items.
     * - Values stock using:
     *      1) Posted purchase bills basic_amount aggregated per GRN line (up to As-on date) / GRN received qty
     *      2) Fallback: PO rate (if GRN has no posted bill yet)
     *      3) Client material is always valued at 0 (quantity-only)
     * - Compares computed stock value vs ledger balance of inventory accounts.
     */
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $asOfDate  = $request->date('as_of_date') ?: now();
        $projectId = $request->integer('project_id') ?: null;
        $itemId    = $request->integer('item_id') ?: null;
        $details   = $request->boolean('details');
        $export    = $request->get('export');

        $projects = Project::orderBy('code')->orderBy('name')->get(['id', 'code', 'name']);

        // Only items that appear in store_stock_items (keeps dropdown small-ish)
        $items = Item::query()
            ->whereIn('id', function ($q) {
                $q->select('item_id')->from('store_stock_items')->distinct();
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        // Fallback inventory account (when item.inventory_account_id is not set)
        $fallbackInvCode = Config::get('accounting.store.inventory_consumables_account_code');
        $fallbackInvAccount = $fallbackInvCode
            ? Account::query()->where('code', $fallbackInvCode)->first()
            : null;

        // Pull live available stock
        $stockQuery = StoreStockItem::query()
            ->with([
                'item:id,code,name,inventory_account_id,material_category_id',
                'item.category:id,code,name',
                'project:id,code,name',
                'receiptLine:id,material_receipt_id,received_weight_kg,qty_pcs,purchase_order_item_id',
                'receiptLine.receipt:id,receipt_number,receipt_date',
                'receiptLine.purchaseOrderItem:id,rate',
            ])
            ->where(function ($q) {
                $q->where('weight_kg_available', '>', 0)
                    ->orWhere('qty_pcs_available', '>', 0);
            });

        if ($projectId) {
            $stockQuery->where('project_id', $projectId);
        }

        if ($itemId) {
            $stockQuery->where('item_id', $itemId);
        }

        $stockItems = $stockQuery->get();

        // Collect GRN line IDs (material_receipt_line_id)
        $mrLineIds = $stockItems
            ->pluck('material_receipt_line_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Billed basic totals per GRN line (posted purchase bills only) up to As-on date
        $basicByMrLineId = [];

        if (! empty($mrLineIds)) {
            $basicByMrLineId = PurchaseBillLine::query()
                ->join('purchase_bills as pb', 'pb.id', '=', 'purchase_bill_lines.purchase_bill_id')
                ->where('pb.status', 'posted')
                ->whereDate('pb.bill_date', '<=', $asOfDate->toDateString())
                ->whereIn('purchase_bill_lines.material_receipt_line_id', $mrLineIds)
                ->groupBy('purchase_bill_lines.material_receipt_line_id')
                ->selectRaw('purchase_bill_lines.material_receipt_line_id as mr_line_id, COALESCE(SUM(purchase_bill_lines.basic_amount),0) as total_basic')
                ->pluck('total_basic', 'mr_line_id')
                ->toArray();
        }

        // Aggregates by inventory account
        $byAccount  = [];
        $detailRows = [];

        foreach ($stockItems as $stock) {
            $availableWeight = (float) ($stock->weight_kg_available ?? 0);
            $availablePcs    = (int) ($stock->qty_pcs_available ?? 0);

            if ($availableWeight > 0) {
                $qty    = $availableWeight;
                $qtyUom = 'kg';
            } else {
                $qty    = (float) $availablePcs;
                $qtyUom = 'pcs';
            }

            if ($qty <= 0) {
                continue;
            }

            $item     = $stock->item;
            $project  = $stock->project;
            $category = $item?->category;

            $invAccountId = (int) ($item?->inventory_account_id ?: ($fallbackInvAccount?->id ?: 0));

            $unitRate    = 0.0;
            $costSource  = 'unvalued';
            $billedBasic = null;
            $baseQty     = null;

            if ((bool) $stock->is_client_material === true) {
                // Client-supplied material is quantity-only
                $costSource = 'client';
                $unitRate   = 0.0;
            } elseif (! empty($stock->material_receipt_line_id)) {
                $mrLineId = (int) $stock->material_receipt_line_id;
                $mrLine   = $stock->receiptLine;

                $billedBasic = (float) ($basicByMrLineId[$mrLineId] ?? 0);

                $receivedWeight = (float) ($mrLine?->received_weight_kg ?? 0);
                $receivedPcs    = (float) ($mrLine?->qty_pcs ?? 0);
                $baseQty        = $receivedWeight > 0 ? $receivedWeight : ($receivedPcs > 0 ? $receivedPcs : 0.0);

                if ($billedBasic > 0 && $baseQty > 0) {
                    // IMPORTANT: This matches the current valuation approach used in StoreIssuePostingService
                    $unitRate   = $billedBasic / $baseQty;
                    $costSource = 'invoice';
                } elseif (((float) ($mrLine?->purchaseOrderItem?->rate ?? 0)) > 0) {
                    $unitRate   = (float) $mrLine->purchaseOrderItem->rate;
                    $costSource = 'po';
                } else {
                    $unitRate   = 0.0;
                    $costSource = 'unvalued';
                }
            } else {
                // opening/adjustment/manual stock rows (no GRN link)
                if ((string) ($stock->source_type ?? '') === 'opening' && (float) ($stock->opening_unit_rate ?? 0) > 0) {
                    $costSource = 'opening';
                    $unitRate   = (float) $stock->opening_unit_rate;
                } else {
                    $costSource = $stock->source_type ? ('source:' . $stock->source_type) : 'unvalued';
                    $unitRate   = 0.0;
                }
            }

            $value = 0.0;
            if (! $stock->is_client_material && $unitRate > 0) {
                $value = round($qty * $unitRate, 2);
            }

            $byAccount[$invAccountId] ??= [
                'account_id'          => $invAccountId,
                'stock_value'         => 0.0,
                'line_count'          => 0,
                'unvalued_line_count' => 0,
                'client_line_count'   => 0,
            ];

            $byAccount[$invAccountId]['stock_value'] += $value;
            $byAccount[$invAccountId]['line_count']++;

            if ($costSource === 'client') {
                $byAccount[$invAccountId]['client_line_count']++;
            }

            if (($costSource === 'unvalued' || str_starts_with($costSource, 'source:')) && ! $stock->is_client_material) {
                $byAccount[$invAccountId]['unvalued_line_count']++;
            }

            if ($details) {
                $detailRows[] = [
                    'stock_id'          => $stock->id,
                    'item'              => $item,
                    'project'           => $project,
                    'category'          => $category,
                    'material_category' => $stock->material_category,
                    'grade'             => $stock->grade,
                    'qty'               => $qty,
                    'qty_uom'           => $qtyUom,
                    'unit_rate'         => (float) $unitRate,
                    'value'             => (float) $value,
                    'cost_source'       => $costSource,
                    'mr_line_id'         => $stock->material_receipt_line_id,
                    'receipt_no'        => $stock->receiptLine?->receipt?->receipt_number,
                    'source_reference'  => $stock->source_reference,
                    'is_client_material' => (bool) $stock->is_client_material,
                    'billed_basic'      => $billedBasic,
                    'base_qty'          => $baseQty,
                ];
            }
        }

        // Load Account models
        $accountIds = array_values(array_filter(array_keys($byAccount), fn ($id) => (int) $id > 0));
        $accounts   = ! empty($accountIds)
            ? Account::query()->whereIn('id', $accountIds)->orderBy('code')->get()->keyBy('id')
            : collect();

        // Ledger movements up to As-on date
        $movements = collect();

        if (! empty($accountIds)) {
            $movementQuery = DB::table('voucher_lines as vl')
                ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
                ->where('v.company_id', $companyId)
                ->where('v.status', 'posted')
                ->whereDate('v.voucher_date', '<=', $asOfDate->toDateString())
                ->whereIn('vl.account_id', $accountIds);

            if ($projectId) {
                $movementQuery->where('v.project_id', $projectId);
            }

            $movements = $movementQuery
                ->selectRaw('vl.account_id, COALESCE(SUM(vl.debit),0) as total_debit, COALESCE(SUM(vl.credit),0) as total_credit')
                ->groupBy('vl.account_id')
                ->get()
                ->keyBy('account_id');
        }

        // Build summary
        $summaryRows = [];
        $grandStock  = 0.0;
        $grandLedger = 0.0;
        $grandDiff   = 0.0;

        foreach ($byAccount as $accId => $agg) {
            $accId  = (int) $accId;
            $account = $accId > 0 ? $accounts->get($accId) : null;

            // Opening included only for company-level view (same behaviour as Trial Balance)
            $opening = 0.0;
            if (! $projectId && $account) {
                $opening = (float) ($account->opening_balance ?? 0.0);

                if ($account->opening_balance_date && $account->opening_balance_date->gt($asOfDate)) {
                    $opening = 0.0;
                }

                if ($opening != 0.0) {
                    $opening *= ($account->opening_balance_type === 'cr') ? -1 : 1;
                }
            }

            $m = $movements->get($accId);
            $debit  = $m ? (float) $m->total_debit : 0.0;
            $credit = $m ? (float) $m->total_credit : 0.0;

            // Dr positive, Cr negative
            $ledgerNet  = round($opening + ($debit - $credit), 2);
            $stockValue = round((float) ($agg['stock_value'] ?? 0), 2);
            $diff       = round($stockValue - $ledgerNet, 2);

            $summaryRows[] = [
                'account'             => $account,
                'account_id'          => $accId,
                'ledger_net'          => $ledgerNet,
                'stock_value'         => $stockValue,
                'difference'          => $diff,
                'line_count'          => (int) ($agg['line_count'] ?? 0),
                'unvalued_line_count' => (int) ($agg['unvalued_line_count'] ?? 0),
                'client_line_count'   => (int) ($agg['client_line_count'] ?? 0),
            ];

            $grandStock  += $stockValue;
            $grandLedger += $ledgerNet;
            $grandDiff   += $diff;
        }

        // Sort by account code/name; keep unknown at bottom
        usort($summaryRows, function (array $a, array $b) {
            $aa = $a['account'];
            $bb = $b['account'];

            if (! $aa && $bb) {
                return 1;
            }
            if ($aa && ! $bb) {
                return -1;
            }
            if (! $aa && ! $bb) {
                return 0;
            }

            $c = strcmp((string) $aa->code, (string) $bb->code);
            if ($c !== 0) {
                return $c;
            }

            return strcmp((string) $aa->name, (string) $bb->name);
        });

        if ($export === 'csv') {
            return $this->exportCsv(
                companyId: $companyId,
                asOfDate: $asOfDate,
                projectId: $projectId,
                itemId: $itemId,
                summaryRows: $summaryRows,
                detailRows: $detailRows,
                includeDetails: $details,
            );
        }

        return view('accounting.reports.inventory_valuation', [
            'companyId'               => $companyId,
            'asOfDate'                => $asOfDate,
            'projects'                => $projects,
            'items'                   => $items,
            'projectId'               => $projectId,
            'itemId'                  => $itemId,
            'details'                 => $details,
            'summaryRows'             => $summaryRows,
            'detailRows'              => $detailRows,
            'grandStock'              => $grandStock,
            'grandLedger'             => $grandLedger,
            'grandDiff'               => $grandDiff,
            'fallbackInventoryAccount' => $fallbackInvAccount,
        ]);
    }

    protected function exportCsv(
        int $companyId,
        $asOfDate,
        ?int $projectId,
        ?int $itemId,
        array $summaryRows,
        array $detailRows,
        bool $includeDetails,
    ): StreamedResponse {
        $fileName = 'inventory_valuation_' . $asOfDate->format('Y-m-d')
            . ($projectId ? ('_project_' . $projectId) : '')
            . ($itemId ? ('_item_' . $itemId) : '')
            . ($includeDetails ? '_details' : '')
            . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($companyId, $asOfDate, $projectId, $itemId, $summaryRows, $detailRows, $includeDetails) {
            $handle = fopen('php://output', 'w');

            // Summary section
            fputcsv($handle, ['Inventory Valuation & Reconciliation']);
            fputcsv($handle, ['Company ID', $companyId]);
            fputcsv($handle, ['As On', $asOfDate->toDateString()]);
            fputcsv($handle, ['Project ID', $projectId ?: 'ALL']);
            fputcsv($handle, ['Item ID', $itemId ?: 'ALL']);
            fputcsv($handle, []);

            fputcsv($handle, [
                'Account Code',
                'Account Name',
                'Ledger Balance (Dr + / Cr -)',
                'Stock Value',
                'Difference (Stock - Ledger)',
                'Stock Lines',
                'Unvalued Lines',
                'Client Lines',
            ]);

            foreach ($summaryRows as $row) {
                $acc = $row['account'];

                fputcsv($handle, [
                    $acc?->code ?? 'N/A',
                    $acc?->name ?? 'Unknown/Unmapped',
                    number_format((float) $row['ledger_net'], 2, '.', ''),
                    number_format((float) $row['stock_value'], 2, '.', ''),
                    number_format((float) $row['difference'], 2, '.', ''),
                    (int) $row['line_count'],
                    (int) $row['unvalued_line_count'],
                    (int) $row['client_line_count'],
                ]);
            }

            if ($includeDetails) {
                fputcsv($handle, []);
                fputcsv($handle, ['DETAILS']);
                fputcsv($handle, [
                    'Stock ID',
                    'Item',
                    'Project',
                    'Category',
                    'Material Category',
                    'Grade',
                    'Qty',
                    'Qty UOM',
                    'Unit Rate',
                    'Value',
                    'Cost Source',
                    'GRN No',
                    'Source Reference',
                    'MR Line ID',
                    'Client Material?',
                    'Billed Basic',
                    'GRN Base Qty',
                ]);

                foreach ($detailRows as $d) {
                    $itemLabel = ($d['item']?->code ? ($d['item']->code . ' - ') : '') . ($d['item']?->name ?? '');
                    $projectLabel = ($d['project']?->code ? ($d['project']->code . ' - ') : '') . ($d['project']?->name ?? '');
                    $catLabel = ($d['category']?->code ? ($d['category']->code . ' - ') : '') . ($d['category']?->name ?? '');

                    fputcsv($handle, [
                        $d['stock_id'],
                        $itemLabel,
                        $projectLabel,
                        $catLabel,
                        $d['material_category'],
                        $d['grade'],
                        number_format((float) $d['qty'], 3, '.', ''),
                        $d['qty_uom'],
                        number_format((float) $d['unit_rate'], 4, '.', ''),
                        number_format((float) $d['value'], 2, '.', ''),
                        $d['cost_source'],
                        $d['receipt_no'] ?? '',
                        $d['source_reference'] ?? '',
                        $d['mr_line_id'] ?? '',
                        $d['is_client_material'] ? 'YES' : 'NO',
                        $d['billed_basic'] !== null ? number_format((float) $d['billed_basic'], 2, '.', '') : '',
                        $d['base_qty'] !== null ? number_format((float) $d['base_qty'], 3, '.', '') : '',
                    ]);
                }
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}