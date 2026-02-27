<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Project;
use App\Models\Production\ProductionBill;
use App\Models\Production\ProductionBillLine;
use App\Models\Production\ProductionDprLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Production\ProductionAudit;

class ProductionBillingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.billing.view')->only(['index', 'show', 'create']);
        $this->middleware('permission:production.billing.generate')->only(['store']);
        $this->middleware('permission:production.billing.update')->only(['finalize', 'cancel']); // Phase E2 uses update
    }

    public function index(Project $project)
    {
        $bills = ProductionBill::query()
            ->where('project_id', $project->id)
            ->with(['contractor'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('projects.production_billing.index', compact('project', 'bills'));
    }

    public function create(Project $project)
    {
        // Keep list limited to contractors/suppliers if you prefer; for now contractors only.
        $contractors = Party::query()
            ->where('is_contractor', true)
            ->orderBy('name')
            ->get();

        return view('projects.production_billing.create', [
            'project' => $project,
            'contractors' => $contractors,
            'defaultFrom' => now()->startOfMonth()->toDateString(),
            'defaultTo' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'contractor_party_id' => ['required', 'integer', 'exists:parties,id'],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'bill_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],

            // Phase E2 GST inputs
            'gst_type' => ['required', 'in:cgst_sgst,igst'],
            'gst_rate' => ['required', 'numeric', 'min:0', 'max:99.99'],
        ]);

        $contractorId = (int)$data['contractor_party_id'];
        $from = $data['period_from'];
        $to = $data['period_to'];

        // GST: apply only when contractor has GSTIN in Parties.
        $contractor = Party::query()->findOrFail($contractorId);
        $gstApplicable = !empty(trim((string)($contractor->gstin ?? '')));

        $gstType = $data['gst_type'];
        $gstRate = (float)$data['gst_rate'];

        if (!$gstApplicable) {
            // Force GST to 0 if no GSTIN (as per your requirement).
            $gstRate = 0.0;
            $gstType = 'cgst_sgst';
        }

        // Pull eligible DPR lines (Approved DPR only, not yet billed)
        // IMPORTANT: DPR qty is in PCS/Nos. Billing qty must align with Rate UOM.
        // If Rate UOM is weight (KGs/MT), billing qty is computed from plan item planned_weight_kg.
        $rows = ProductionDprLine::query()
            ->join('production_dprs as dpr', 'dpr.id', '=', 'production_dpr_lines.production_dpr_id')
            ->join('production_plans as pp', 'pp.id', '=', 'dpr.production_plan_id')
            ->leftJoin('production_plan_item_activities as pia', 'pia.id', '=', 'production_dpr_lines.production_plan_item_activity_id')
            ->leftJoin('production_plan_items as ppi', 'ppi.id', '=', 'production_dpr_lines.production_plan_item_id')
            ->leftJoin('uoms as ruom', 'ruom.id', '=', 'pia.rate_uom_id')
            ->where('pp.project_id', $project->id)
            ->where('dpr.status', 'approved')
            ->whereBetween('dpr.dpr_date', [$from, $to])
			// A DPR line becomes billable again if the previous bill was cancelled.
			// So we only exclude DPR lines that are mapped to a NON-cancelled bill (draft/finalized).
			->whereNotExists(function ($q) {
				$q->select(DB::raw(1))
					->from('production_bill_dpr_lines as bmap2')
					->join('production_bills as pb2', 'pb2.id', '=', 'bmap2.production_bill_id')
					->whereColumn('bmap2.production_dpr_line_id', 'production_dpr_lines.id')
					->whereIn('pb2.status', ['draft', 'finalized']);
			})
            ->where(function ($q) use ($contractorId) {
                $q->where('dpr.contractor_party_id', $contractorId)
                    ->orWhere('pia.contractor_party_id', $contractorId);
            })
            ->select([
                'production_dpr_lines.id as dpr_line_id',
                'dpr.id as dpr_id',
                'dpr.dpr_date as dpr_date',
                'dpr.production_activity_id as production_activity_id',

                'production_dpr_lines.qty as dpr_qty',
                'production_dpr_lines.qty_uom_id as dpr_qty_uom_id',

                'ppi.id as plan_item_id',
                'ppi.item_code as item_code',
                'ppi.assembly_mark as assembly_mark',
                'ppi.planned_qty as planned_qty',
                'ppi.planned_weight_kg as planned_weight_kg',

                'pia.rate as rate',
                'pia.rate_uom_id as rate_uom_id',

                'ruom.code as rate_uom_code',
                'ruom.category as rate_uom_category',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return back()->withInput()->with('error', 'No eligible approved DPR lines found for billing in this period.');
        }

        // Convert each DPR line into a billing qty aligned with rate UOM.
        $missingWeight = [];

        $rows = $rows->map(function ($r) use (&$missingWeight) {
            $dprQty = (float)($r->dpr_qty ?? 0);
            if ($dprQty < 0) $dprQty = 0;

            $billQty = $dprQty;
            $billQtyUomId = $r->dpr_qty_uom_id ? (int)$r->dpr_qty_uom_id : null;

            $rateUomId = $r->rate_uom_id ? (int)$r->rate_uom_id : null;
            $rateUomCategory = strtolower((string)($r->rate_uom_category ?? ''));
            $rateUomCode = strtoupper((string)($r->rate_uom_code ?? ''));

            if ($rateUomId && $rateUomCategory === 'weight') {
                // Weight-based billing: derive weight for the DPR qty from plan item's planned_weight_kg.
                $plannedQty = (float)($r->planned_qty ?? 0);
                $plannedWeightKg = $r->planned_weight_kg;
                $plannedWeightKg = ($plannedWeightKg === null || $plannedWeightKg === '') ? null : (float)$plannedWeightKg;

                if ($plannedQty > 0 && $plannedWeightKg !== null && $plannedWeightKg > 0) {
                    $weightPerUnitKg = $plannedWeightKg / $plannedQty;
                    $weightKg = $dprQty * $weightPerUnitKg;

                    // Convert KG to target weight UOM
                    if ($rateUomCode === 'MT') {
                        $billQty = $weightKg / 1000;
                    } else {
                        // Default: treat as KG
                        $billQty = $weightKg;
                    }

                    $billQtyUomId = $rateUomId; // show qty in same UOM as rate
                } else {
                    $missingWeight[] = $r;
                }
            } elseif ($rateUomId) {
                // For count-based billing, show qty in Rate UOM if DPR qty_uom is missing.
                if (!$billQtyUomId) {
                    $billQtyUomId = $rateUomId;
                }
            }

            $r->bill_qty = round((float)$billQty, 3);
            $r->bill_qty_uom_id = $billQtyUomId;

            return $r;
        });

        if (!empty($missingWeight)) {
            // Show up to 8 examples to the user.
            $examples = collect($missingWeight)
                ->take(8)
                ->map(function ($r) {
                    $code = $r->item_code ?? ('Item#' . ($r->plan_item_id ?? ''));
                    $asm = $r->assembly_mark ? ('Asm ' . $r->assembly_mark . ' / ') : '';
                    return $asm . $code;
                })
                ->implode(', ');

            return back()->withInput()->with('error', 'Cannot generate bill: some DPR lines are billed by weight (KG/MT) but item planned weight is missing in Production Plan. Please update BOM weights / plan weights and retry. Examples: ' . $examples);
        }

        $bill = null;

        DB::transaction(function () use ($project, $contractorId, $data, $rows, $gstType, $gstRate, &$bill) {

            $bill = ProductionBill::create([
                'project_id' => $project->id,
                'contractor_party_id' => $contractorId,
                'bill_number' => ProductionBill::nextBillNumber($project),
                'bill_date' => $data['bill_date'] ?? now()->toDateString(),
                'period_from' => $data['period_from'],
                'period_to' => $data['period_to'],
                'status' => 'draft',
                'remarks' => $data['remarks'] ?? null,

                'gst_type' => $gstType,
                'gst_rate' => $gstRate,

                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Group by activity+rate+rate_uom+bill_uom
            $grouped = $rows->groupBy(function ($r) {
                return implode('|', [
                    (string)$r->production_activity_id,
                    (string)($r->rate ?? 0),
                    (string)($r->rate_uom_id ?? ''),
                    (string)($r->bill_qty_uom_id ?? ''),
                ]);
            });

            $subtotal = 0.0;
            $cgstTotal = 0.0;
            $sgstTotal = 0.0;
            $igstTotal = 0.0;

            foreach ($grouped as $items) {
                $first = $items->first();

                $qtySum = (float)$items->sum('bill_qty');
                $rate = (float)($first->rate ?? 0);

                $amount = round($qtySum * $rate, 2);

                // GST calculation
                $cgst = 0.0;
                $sgst = 0.0;
                $igst = 0.0;

                if ($gstRate > 0) {
                    if ($gstType === 'igst') {
                        $igst = round($amount * ($gstRate / 100), 2);
                    } else {
                        $half = $gstRate / 2;
                        $cgst = round($amount * ($half / 100), 2);
                        $sgst = round($amount * ($half / 100), 2);
                    }
                }

                $lineTotal = round($amount + $cgst + $sgst + $igst, 2);

                $subtotal += $amount;
                $cgstTotal += $cgst;
                $sgstTotal += $sgst;
                $igstTotal += $igst;

                ProductionBillLine::create([
                    'production_bill_id' => $bill->id,
                    'production_activity_id' => (int)$first->production_activity_id,

                    // Billing qty aligned with rate UOM
                    'qty' => $qtySum,
                    'qty_uom_id' => $first->bill_qty_uom_id ? (int)$first->bill_qty_uom_id : null,

                    'rate' => $rate,
                    'rate_uom_id' => $first->rate_uom_id ? (int)$first->rate_uom_id : null,

                    'amount' => $amount,
                    'cgst_amount' => $cgst,
                    'sgst_amount' => $sgst,
                    'igst_amount' => $igst,
                    'line_total' => $lineTotal,

                    'source_meta' => [
                        'dpr_line_ids' => $items->pluck('dpr_line_id')->values()->all(),
                        'dpr_ids' => $items->pluck('dpr_id')->unique()->values()->all(),
                        'pieces_qty_sum' => round((float)$items->sum('dpr_qty'), 3),
                        'pieces_uom_id' => $first->dpr_qty_uom_id ? (int)$first->dpr_qty_uom_id : null,
                    ],
                ]);

                // mapping table: mark each DPR line as billed
                foreach ($items as $it) {
                    DB::table('production_bill_dpr_lines')->insert([
                        'production_bill_id' => $bill->id,
                        'production_dpr_line_id' => (int)$it->dpr_line_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $subtotal = round($subtotal, 2);
            $cgstTotal = round($cgstTotal, 2);
            $sgstTotal = round($sgstTotal, 2);
            $igstTotal = round($igstTotal, 2);

            $taxTotal = round($cgstTotal + $sgstTotal + $igstTotal, 2);
            $grandTotal = round($subtotal + $taxTotal, 2);

            $bill->subtotal = $subtotal;
            $bill->cgst_total = $cgstTotal;
            $bill->sgst_total = $sgstTotal;
            $bill->igst_total = $igstTotal;
            $bill->tax_total = $taxTotal;
            $bill->grand_total = $grandTotal;
            $bill->save();
        });

        $msg = 'Production bill generated successfully (Draft).';
        if ($gstRate <= 0) {
            $msg .= ' GST not applied (contractor has no GSTIN or GST rate is 0).';
        }

        return redirect()
            ->route('projects.production-billing.show', [$project, $bill])
            ->with('success', $msg);
    }

    public function show(Project $project, ProductionBill $production_bill)
    {
        if ((int)$production_bill->project_id !== (int)$project->id) abort(404);

        $production_bill->load(['contractor', 'finalizedBy', 'lines.activity', 'lines.qtyUom', 'lines.rateUom']);

        return view('projects.production_billing.show', [
            'project' => $project,
            'bill' => $production_bill,
        ]);
    }

    public function finalize(Project $project, ProductionBill $production_bill)
    {
        if ((int)$production_bill->project_id !== (int)$project->id) abort(404);

        if (!$production_bill->isDraft()) {
            return back()->with('error', 'Only draft bills can be finalized.');
        }

        $production_bill->status = 'finalized';
        $production_bill->finalized_by = auth()->id();
        $production_bill->finalized_at = now();
        $production_bill->updated_by = auth()->id();
        $production_bill->save();

        ProductionAudit::log(
            $project->id,
            'bill.finalize',
            'ProductionBill',
            $production_bill->id,
            'Bill finalized',
            ['bill_number' => $production_bill->bill_number]
        );

        return back()->with('success', 'Bill finalized and locked.');
    }

    public function cancel(Project $project, ProductionBill $production_bill)
    {
        if ((int)$production_bill->project_id !== (int)$project->id) abort(404);

        if ($production_bill->isCancelled()) {
            return back()->with('error', 'Bill is already cancelled.');
        }

        if ($production_bill->isFinalized()) {
            return back()->with('error', 'Finalized bill cannot be cancelled.');
        }

		DB::transaction(function () use ($production_bill) {
			// IMPORTANT: When a bill is cancelled, its DPR lines must become billable again.
			// We keep the bill + bill lines for audit/history, but we remove the mapping lock.
			DB::table('production_bill_dpr_lines')
				->where('production_bill_id', $production_bill->id)
				->delete();

			$production_bill->status = 'cancelled';
			$production_bill->updated_by = auth()->id();
			$production_bill->save();
		});

        ProductionAudit::log(
            $project->id,
            'bill.cancel',
            'ProductionBill',
            $production_bill->id,
            'Bill cancelled',
            ['bill_number' => $production_bill->bill_number]
        );

        return back()->with('success', 'Bill cancelled.');
    }
}
