<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Project;
use App\Models\ProductionV2\ProductionBill;
use App\Models\ProductionV2\ProductionBillLine;
use App\Models\ProductionV2\ProductionBillSourceLink;
use App\Models\ProductionV2\ProductionBillingRate;
use App\Support\ProductionV2\BillingEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionV2BillingController extends Controller
{
    public function __construct(private BillingEngine $billingEngine)
    {
        $this->middleware('auth');
        $this->middleware('permission:production.billing.view')->only(['index', 'show', 'create']);
        $this->middleware('permission:production.billing.generate')->only(['store']);
        $this->middleware('permission:production.billing.update')->only(['finalize', 'cancel']);
    }

    public function index(Project $project)
    {
        $rows = ProductionBill::query()
            ->where('project_id', $project->id)
            ->with(['contractor', 'finalizedBy'])
            ->withCount('lines')
            ->orderByDesc('id')
            ->paginate(25);

        return view('production_v2.billing.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Project $project)
    {
        return view('production_v2.billing.create', [
            'project' => $project,
            'contractors' => Party::query()->where('is_contractor', true)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'gstin']),
            'rateSummary' => ProductionBillingRate::query()
                ->where('project_id', $project->id)
                ->where('is_active', true)
                ->with('contractor')
                ->get()
                ->groupBy('contractor_party_id'),
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
            'gst_type' => ['required', 'in:cgst_sgst,igst'],
            'gst_rate' => ['required', 'numeric', 'min:0', 'max:99.99'],
        ]);

        $contractor = Party::query()->findOrFail((int) $data['contractor_party_id']);
        $rateCards = ProductionBillingRate::query()
            ->where('project_id', $project->id)
            ->where('contractor_party_id', $contractor->id)
            ->where('is_active', true)
            ->with(['operationMaster', 'rateUom'])
            ->get();

        if ($rateCards->isEmpty()) {
            return back()->withInput()->with('error', 'No active Production V2 billing rates found for this contractor.');
        }

        $billingRows = $this->billingEngine->collectRows(
            $project->id,
            (int) $contractor->id,
            $data['period_from'],
            $data['period_to'],
            $rateCards
        );

        if ($billingRows['missing_rates']->isNotEmpty()) {
            return back()->withInput()->with('error', 'Cannot generate bill. Missing or unresolved billing rates for: ' . $billingRows['missing_rates']->take(8)->implode(', '));
        }

        if ($billingRows['rows']->isEmpty()) {
            return back()->withInput()->with('error', 'No eligible V2 execution records found for billing in this period.');
        }

        $gstApplicable = ! empty(trim((string) ($contractor->gstin ?? '')));
        $gstType = $gstApplicable ? $data['gst_type'] : 'cgst_sgst';
        $gstRate = $gstApplicable ? (float) $data['gst_rate'] : 0.0;

        $bill = DB::transaction(function () use ($project, $contractor, $data, $billingRows, $gstType, $gstRate) {
            $bill = ProductionBill::query()->create([
                'project_id' => $project->id,
                'contractor_party_id' => $contractor->id,
                'bill_number' => ProductionBill::nextBillNumber($project),
                'bill_date' => $data['bill_date'] ?? now()->toDateString(),
                'period_from' => $data['period_from'],
                'period_to' => $data['period_to'],
                'status' => 'draft',
                'gst_type' => $gstType,
                'gst_rate' => $gstRate,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $grouped = collect($billingRows['rows'])->groupBy(function (array $row) {
                /** @var \App\Models\ProductionV2\ProductionBillingRate $rateCard */
                $rateCard = $row['rate_card'];

                return implode('|', [
                    $rateCard->id,
                    $row['source_type'],
                    $rateCard->operation_master_id ?: 0,
                    $row['qty_uom_id'] ?: 0,
                ]);
            });

            $subtotal = 0.0;
            $cgstTotal = 0.0;
            $sgstTotal = 0.0;
            $igstTotal = 0.0;

            foreach ($grouped as $items) {
                $first = $items->first();
                $rateCard = $first['rate_card'];
                $qtySum = round((float) $items->sum('qty'), 3);
                $rate = (float) $rateCard->rate;
                $amount = round($qtySum * $rate, 2);

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

                ProductionBillLine::query()->create([
                    'production_v2_bill_id' => $bill->id,
                    'billing_rate_id' => $rateCard->id,
                    'source_type' => $first['source_type'],
                    'operation_master_id' => $rateCard->operation_master_id,
                    'description' => $first['description'],
                    'qty' => $qtySum,
                    'qty_uom_id' => $first['qty_uom_id'],
                    'rate' => $rate,
                    'rate_uom_id' => $rateCard->rate_uom_id,
                    'amount' => $amount,
                    'cgst_amount' => $cgst,
                    'sgst_amount' => $sgst,
                    'igst_amount' => $igst,
                    'line_total' => $lineTotal,
                    'source_meta' => [
                        'source_ids' => $items->pluck('source_id')->values()->all(),
                        'source_count' => $items->count(),
                    ],
                ]);

                foreach ($items as $row) {
                    ProductionBillSourceLink::query()->create([
                        'production_v2_bill_id' => $bill->id,
                        'source_type' => $row['source_type'],
                        'source_id' => $row['source_id'],
                    ]);
                }
            }

            $taxTotal = round($cgstTotal + $sgstTotal + $igstTotal, 2);
            $grandTotal = round($subtotal + $taxTotal, 2);

            $bill->update([
                'subtotal' => round($subtotal, 2),
                'cgst_total' => round($cgstTotal, 2),
                'sgst_total' => round($sgstTotal, 2),
                'igst_total' => round($igstTotal, 2),
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
            ]);

            return $bill;
        });

        return redirect()
            ->route('projects.production-v2.billing.show', ['project' => $project->id, 'bill' => $bill->id])
            ->with('success', 'Production V2 bill generated successfully.');
    }

    public function show(Project $project, ProductionBill $bill)
    {
        abort_unless((int) $bill->project_id === (int) $project->id, 404);

        $bill->load(['contractor', 'finalizedBy', 'lines.rateUom', 'lines.qtyUom', 'lines.operationMaster']);

        return view('production_v2.billing.show', [
            'project' => $project,
            'bill' => $bill,
        ]);
    }

    public function finalize(Project $project, ProductionBill $bill)
    {
        abort_unless((int) $bill->project_id === (int) $project->id, 404);

        if (! $bill->isDraft()) {
            return back()->with('error', 'Only draft bills can be finalized.');
        }

        $bill->update([
            'status' => 'finalized',
            'finalized_by' => auth()->id(),
            'finalized_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Production V2 bill finalized.');
    }

    public function cancel(Project $project, ProductionBill $bill)
    {
        abort_unless((int) $bill->project_id === (int) $project->id, 404);

        if ($bill->isCancelled()) {
            return back()->with('error', 'Bill is already cancelled.');
        }

        if ($bill->isFinalized()) {
            return back()->with('error', 'Finalized bill cannot be cancelled.');
        }

        DB::transaction(function () use ($bill) {
            $this->billingEngine->releaseMappings($bill->id);
            $bill->update([
                'status' => 'cancelled',
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Production V2 bill cancelled.');
    }
}
