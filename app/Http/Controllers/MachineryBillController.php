<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\MaterialCategory;
use App\Models\MaterialType;
use App\Models\Party;
use App\Models\PurchaseBill;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MachineryBillController extends Controller
{
    /**
     * List Purchase Bills that contain at least one machinery item.
     */
    public function index(Request $request)
    {
        $machineryTypeId = MaterialType::where('code', 'MACHINERY')->value('id');

        // If MACHINERY type isn't present, don't error — just show empty list.
        if (! $machineryTypeId) {
            return view('machinery_bills.index', [
                'bills' => new LengthAwarePaginator([], 0, 25),
                'suppliers' => Party::orderBy('name')->get(),
                'machineryTypeMissing' => true,
                'generatedCounts' => collect(),
                'machineryQtyByBill' => collect(),
            ]);
        }

        $query = PurchaseBill::query()
            ->with(['supplier', 'lines.item'])
            ->whereHas('lines.item', function ($q) use ($machineryTypeId) {
                $q->where('material_type_id', $machineryTypeId);
            });

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('supplier_id')) {
            $query->where('party_id', (int) $request->input('supplier_id'));
        }

        if ($request->filled('q')) {
            $q = trim($request->string('q')->toString());
            $query->where(function ($qq) use ($q) {
                $qq->where('bill_number', 'like', '%' . $q . '%')
                    ->orWhere('invoice_number', 'like', '%' . $q . '%');
            });
        }

        $bills = $query
            ->orderByDesc('bill_date')
            ->paginate(25)
            ->withQueryString();

        $billIds = $bills->getCollection()->pluck('id')->values();

        // Total machinery qty by bill (integer-style; tools qty should be whole numbers)
        $machineryQtyByBill = $bills->getCollection()->mapWithKeys(function ($bill) use ($machineryTypeId) {
            $qty = 0;
            foreach ($bill->lines as $line) {
                if (! $line->item) {
                    continue;
                }
                if ((int) $line->item->material_type_id !== (int) $machineryTypeId) {
                    continue;
                }
                $qty += max(0, (int) round((float) $line->qty));
            }
            return [$bill->id => $qty];
        });

        $generatedCounts = collect();
        if (Schema::hasColumn('machines', 'purchase_bill_id')) {
            $generatedCounts = Machine::query()
                ->whereIn('purchase_bill_id', $billIds)
                ->selectRaw('purchase_bill_id, COUNT(*) as cnt')
                ->groupBy('purchase_bill_id')
                ->pluck('cnt', 'purchase_bill_id');
        }

        $suppliers = Party::orderBy('name')->get();

        return view('machinery_bills.index', compact(
            'bills',
            'suppliers',
            'generatedCounts',
            'machineryQtyByBill'
        ))->with('machineryTypeMissing', false);
    }

    /**
     * Show a single Purchase Bill (machinery-specific view) + machine generation status.
     */
    public function show(PurchaseBill $bill)
    {
        $machineryTypeId = MaterialType::where('code', 'MACHINERY')->value('id');

        $bill->load(['supplier', 'lines.item.type', 'lines.item.category', 'lines.item.subcategory']);

        $machLines = $bill->lines->filter(function ($line) use ($machineryTypeId) {
            return $line->item && (int) $line->item->material_type_id === (int) $machineryTypeId;
        })->values();

        $lineIds = $machLines->pluck('id')->values();

        $machinesByLine = collect();
        $allMachines = collect();

        if (Schema::hasColumn('machines', 'purchase_bill_id')) {
            $allMachines = Machine::query()
                ->where('purchase_bill_id', $bill->id)
                ->orderBy('id')
                ->get();
        }

        if (Schema::hasColumn('machines', 'purchase_bill_line_id') && $lineIds->isNotEmpty()) {
            $machinesByLine = Machine::query()
                ->whereIn('purchase_bill_line_id', $lineIds)
                ->orderBy('id')
                ->get()
                ->groupBy('purchase_bill_line_id');
        }

        $summary = [
            'machinery_qty' => $machLines->sum(fn($l) => max(0, (int) round((float) $l->qty))),
            'generated_qty' => (int) $allMachines->count(),
        ];
        $summary['pending_qty'] = max(0, (int) $summary['machinery_qty'] - (int) $summary['generated_qty']);

        return view('machinery_bills.show', compact('bill', 'machLines', 'machinesByLine', 'allMachines', 'summary'));
    }

    /**
     * Generate missing machines for all machinery lines in a posted Purchase Bill.
     * Idempotent: if you run it again, it only creates the missing quantity per line.
     */
    public function generateMachines(PurchaseBill $bill)
    {
        if ($bill->status !== 'posted') {
            return redirect()
                ->back()
                ->with('error', 'Bill must be POSTED before generating machines.');
        }

        if (! Schema::hasColumn('machines', 'purchase_bill_id') || ! Schema::hasColumn('machines', 'purchase_bill_line_id')) {
            return redirect()
                ->back()
                ->with('error', 'Machine purchase bill linking columns are missing. Please run migrations first.');
        }

        $machineryTypeId = MaterialType::where('code', 'MACHINERY')->value('id');
        if (! $machineryTypeId) {
            return redirect()
                ->back()
                ->with('error', 'MaterialType code MACHINERY not found.');
        }

        $bill->load(['supplier', 'lines.item.type', 'lines.item.category', 'lines.item.subcategory']);

        $fallbackCategoryId = MaterialCategory::where('material_type_id', $machineryTypeId)
            ->where('code', 'OTHER')
            ->value('id');

        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($bill, $machineryTypeId, $fallbackCategoryId, &$created, &$skipped) {

            foreach ($bill->lines as $line) {
                $item = $line->item;

                if (! $item || (int) $item->material_type_id !== (int) $machineryTypeId) {
                    continue;
                }

                $qty = max(0, (int) round((float) $line->qty));
                if ($qty <= 0) {
                    continue;
                }

                $existing = Machine::where('purchase_bill_line_id', $line->id)->count();
                $missing = $qty - $existing;

                if ($missing <= 0) {
                    continue;
                }

                $categoryId = $item->material_category_id ?: $fallbackCategoryId;
                if (! $categoryId) {
                    $skipped[] = "Line #{$line->id} ({$item->name}): missing material category";
                    continue;
                }

                $typeId = $item->material_type_id ?: $machineryTypeId;
                $subcategoryId = $item->material_subcategory_id;

                $costBase = (float) ($line->taxable_amount ?? $line->basic_amount ?? 0);
                $unitCost = $qty > 0 ? round($costBase / $qty, 2) : 0;

                $treatment = $item->accounting_usage_override
                    ?: ($item->type?->accounting_usage);

                for ($i = 0; $i < $missing; $i++) {
                    $code = Machine::generateCode((int) $categoryId);

                    Machine::create([
                        'material_type_id' => $typeId,
                        'material_category_id' => $categoryId,
                        'material_subcategory_id' => $subcategoryId,

                        'code' => $code,
                        'name' => $item->name ?? 'Machinery',
                        'short_name' => $item->name ?? null,

                        // DB constraint: serial_number is required + unique.
                        // Placeholder is acceptable; user can update later.
                        'serial_number' => $code,

                        'supplier_party_id' => $bill->party_id,
                        'purchase_date' => $bill->bill_date,
                        'purchase_price' => $unitCost,
                        'purchase_invoice_no' => $bill->invoice_number,

                        'purchase_bill_id' => $bill->id,
                        'purchase_bill_line_id' => $line->id,

                        'accounting_treatment' => $treatment,

                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);

                    $created++;
                }
            }
        });

        $message = "Created {$created} machine(s).";
        if (! empty($skipped)) {
            $message .= ' Skipped: ' . implode(' | ', $skipped);
        }

        return redirect()
            ->route('machinery-bills.show', $bill)
            ->with('success', $message);
    }
}
