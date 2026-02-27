<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreStockRegisterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:store.stock.view');
    }

    public function index(Request $request): View
    {
        $items    = Item::orderBy('name')->get();
        $projects = Project::orderBy('code')->get();

        $itemId      = $request->integer('item_id') ?: null;
        $projectId   = $request->integer('project_id') ?: null;
        $fromDate    = $request->input('from_date');
        $toDate      = $request->input('to_date');
        $includeRaw  = $request->boolean('include_raw');

        $rawCategories = ['steel_plate', 'steel_section'];

        /*
         * Build movement queries with SAME column list:
         * - txn_date
         * - source_type (grn / adjustment / issue / return / vendor_return / production_*)
         * - txn_type (GRN / Opening / Increase / Decrease / Issue / Return / Vendor Return / Production Consume / Remnant In)
         * - reference_number
         * - item_id, project_id
         * - qty_in_pcs, qty_out_pcs
         * - weight_in_kg, weight_out_kg
         */

        // GRN (inward)
        $grn = DB::table('material_receipt_lines as l')
            ->join('material_receipts as h', 'h.id', '=', 'l.material_receipt_id')
            ->when(! $includeRaw, function ($q) use ($rawCategories) {
                $q->whereNotIn('l.material_category', $rawCategories);
            })
            ->selectRaw("
                h.receipt_date              as txn_date,
                'grn'                       as source_type,
                'GRN'                       as txn_type,
                h.receipt_number            as reference_number,
                l.item_id                   as item_id,
                h.project_id                as project_id,
                l.qty_pcs                   as qty_in_pcs,
                0                           as qty_out_pcs,
                l.received_weight_kg        as weight_in_kg,
                0                           as weight_out_kg
            ");

        // Stock adjustments (opening/increase/decrease)
        // Note: designed for non-raw adjustments (weight-based). For raw, use Production Traceability / Scrap.
        $adj = DB::table('store_stock_adjustment_lines as l')
            ->join('store_stock_adjustments as h', 'h.id', '=', 'l.store_stock_adjustment_id')
            ->selectRaw("
                h.adjustment_date           as txn_date,
                'adjustment'                as source_type,
                h.adjustment_type           as txn_type,
                h.reference_number          as reference_number,
                l.item_id                   as item_id,
                l.project_id                as project_id,
                0                           as qty_in_pcs,
                0                           as qty_out_pcs,
                CASE WHEN l.quantity > 0 THEN l.quantity ELSE 0 END as weight_in_kg,
                CASE WHEN l.quantity < 0 THEN -l.quantity ELSE 0 END as weight_out_kg
            ");

        // Issues (outward)
        $issue = DB::table('store_issue_lines as l')
            ->join('store_issues as h', 'h.id', '=', 'l.store_issue_id')
            ->selectRaw("
                h.issue_date                as txn_date,
                'issue'                     as source_type,
                'Issue'                     as txn_type,
                h.issue_number              as reference_number,
                l.item_id                   as item_id,
                h.project_id                as project_id,
                0                           as qty_in_pcs,
                l.issued_qty_pcs            as qty_out_pcs,
                0                           as weight_in_kg,
                l.issued_weight_kg          as weight_out_kg
            ");

        // Returns (inward)
        $return = DB::table('store_return_lines as l')
            ->join('store_returns as h', 'h.id', '=', 'l.store_return_id')
            ->selectRaw("
                h.return_date               as txn_date,
                'return'                    as source_type,
                'Return'                    as txn_type,
                h.return_number             as reference_number,
                l.item_id                   as item_id,
                h.project_id                as project_id,
                l.returned_qty_pcs          as qty_in_pcs,
                0                           as qty_out_pcs,
                l.returned_weight_kg        as weight_in_kg,
                0                           as weight_out_kg
            ");

        // Vendor Returns (outward) - returned back to Supplier/Client against GRN
        $vendorReturn = DB::table('material_vendor_return_lines as l')
            ->join('material_vendor_returns as h', 'h.id', '=', 'l.material_vendor_return_id')
            ->when(! $includeRaw, function ($q) use ($rawCategories) {
                $q->whereNotIn('l.material_category', $rawCategories);
            })
            ->groupBy('h.return_date', 'h.vendor_return_number', 'l.item_id', 'h.project_id')
            ->selectRaw("
                h.return_date               as txn_date,
                'vendor_return'             as source_type,
                'Vendor Return'             as txn_type,
                h.vendor_return_number      as reference_number,
                l.item_id                   as item_id,
                h.project_id                as project_id,
                0                           as qty_in_pcs,
                SUM(l.returned_qty_pcs)     as qty_out_pcs,
                0                           as weight_in_kg,
                SUM(l.returned_weight_kg)   as weight_out_kg
            ");

        /*
         * UNION ALL sources into one movement list.
         * Filters are applied on the OUTER query (derived table) because txn_date is an alias in inner selects.
         */
        $union = $grn
            ->unionAll($adj)
            ->unionAll($issue)
            ->unionAll($return)
            ->unionAll($vendorReturn);

        // Optional RAW movements: production consumption + usable remnant generation
        if ($includeRaw) {
            // Production consumption (outward): mother stock consumed in DPR cutting
            $prodConsumeBase = DB::table('store_stock_items as s')
                ->join('production_pieces as pp', 'pp.mother_stock_item_id', '=', 's.id')
                ->join('production_dpr_lines as dl', 'dl.id', '=', 'pp.production_dpr_line_id')
                ->join('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
                ->whereIn('s.material_category', $rawCategories)
                ->where('s.status', 'consumed')
                ->select([
                    's.id as stock_id',
                    'd.dpr_date as txn_date',
                    'd.id as dpr_id',
                    's.item_id as item_id',
                    's.project_id as project_id',
                    's.weight_kg_total as weight_out_kg',
                ])
                ->distinct();

            $prodConsume = DB::query()
                ->fromSub($prodConsumeBase, 'x')
                ->groupBy('x.txn_date', 'x.dpr_id', 'x.item_id', 'x.project_id')
                ->selectRaw("
                    x.txn_date                as txn_date,
                    'production_consume'      as source_type,
                    'Production Consume'      as txn_type,
                    CONCAT('DPR-', x.dpr_id)  as reference_number,
                    x.item_id                 as item_id,
                    x.project_id              as project_id,
                    0                         as qty_in_pcs,
                    COUNT(*)                  as qty_out_pcs,
                    0                         as weight_in_kg,
                    SUM(COALESCE(x.weight_out_kg,0)) as weight_out_kg
                ");

            // Usable remnants created (inward)
            $prodRemnant = DB::table('production_remnants as r')
                ->join('production_dpr_lines as dl', 'dl.id', '=', 'r.production_dpr_line_id')
                ->join('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
                ->join('store_stock_items as s', 's.id', '=', 'r.remnant_stock_item_id')
                ->whereNotNull('r.remnant_stock_item_id')
                ->whereIn('s.material_category', $rawCategories)
                ->groupBy('d.dpr_date', 'd.id', 's.item_id', 's.project_id')
                ->selectRaw("
                    d.dpr_date                as txn_date,
                    'production_remnant'      as source_type,
                    'Remnant In'              as txn_type,
                    CONCAT('DPR-', d.id)      as reference_number,
                    s.item_id                 as item_id,
                    s.project_id              as project_id,
                    COUNT(*)                  as qty_in_pcs,
                    0                         as qty_out_pcs,
                    SUM(COALESCE(s.weight_kg_total,0)) as weight_in_kg,
                    0                         as weight_out_kg
                ");

            $union = $union
                ->unionAll($prodConsume)
                ->unionAll($prodRemnant);
        }

        $movementsQuery = DB::query()->fromSub($union, 'm');

        if ($itemId) {
            $movementsQuery->where('m.item_id', $itemId);
        }
        if ($projectId) {
            $movementsQuery->where('m.project_id', $projectId);
        }
        if ($fromDate) {
            $movementsQuery->whereDate('m.txn_date', '>=', $fromDate);
        }
        if ($toDate) {
            $movementsQuery->whereDate('m.txn_date', '<=', $toDate);
        }

        $movements = $movementsQuery
            ->orderBy('txn_date')
            ->orderBy('source_type')
            ->orderBy('reference_number')
            ->get();

        /*
         * Optional running balance (kg): computed only when item + project selected.
         */
        $runningBalance = null;

        if ($itemId && $projectId) {
            $balance = 0.0;

            $runningBalance = $movements->map(function ($row) use (&$balance) {
                $balance += (float) $row->weight_in_kg - (float) $row->weight_out_kg;
                $row->balance_kg = $balance;
                return $row;
            });
        } else {
            $runningBalance = $movements;
        }

        return view('store.stock_register.index', [
            'items'             => $items,
            'projects'          => $projects,
            'movements'         => $runningBalance,
            'selectedItemId'    => $itemId,
            'selectedProjectId' => $projectId,
            'fromDate'          => $fromDate,
            'toDate'            => $toDate,
            'includeRaw'        => $includeRaw,
        ]);
    }
}
