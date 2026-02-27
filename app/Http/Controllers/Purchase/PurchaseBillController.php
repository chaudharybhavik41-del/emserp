<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseBillRequest;
use App\Http\Requests\UpdatePurchaseBillRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\TdsSection;
use App\Models\Item;
use App\Models\Party;
use App\Models\PartyBranch;
use App\Models\Project;
use App\Models\PurchaseBill;
use App\Models\PurchaseBillLine;
use App\Models\Uom;
use App\Models\PurchaseOrder;
use App\Models\MaterialReceipt;
use App\Models\Company;
use App\Models\Attachment;
use App\Services\Accounting\PurchaseBillPostingService;
use App\Support\GstHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\ItemGstResolver;
use App\Services\Accounting\AccountGstResolver;
use App\Models\PurchaseBillExpenseLine;
use App\Services\Accounting\PurchaseBillReversalService;
use App\Models\Accounting\AccountBillAllocation;
use App\Models\Accounting\VoucherLine;

class PurchaseBillController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // public function index(Request $request)
    // {
    //     $query = PurchaseBill::with(['supplier', 'voucher', 'purchaseOrder.project', 'project', 'expenseLines.project'])
    //         // Sort by Posting Date (Voucher date) first for accounting users
    //         ->orderByDesc('posting_date')
    //         ->orderByDesc('bill_date')
    //         ->orderByDesc('id');

    //     if ($supplierId = $request->get('supplier_id')) {
    //         $query->where('supplier_id', $supplierId);
    //     }

    //     if ($status = $request->get('status')) {
    //         $query->where('status', $status);
    //     }

    //     if ($projectId = $request->get('project_id')) {
    //         $query->where(function ($q) use ($projectId) {
    //             $q->where('project_id', $projectId)
    //               ->orWhereHas('purchaseOrder', function ($qpo) use ($projectId) {
    //                   $qpo->where('project_id', $projectId);
    //               })
    //               ->orWhereHas('expenseLines', function ($qe) use ($projectId) {
    //                   $qe->where('project_id', $projectId);
    //               });
    //         });
    //     }

    //     if ($search = trim($request->get('q', ''))) {
    //         $query->where(function ($q) use ($search) {
    //             $q->where('bill_number', 'like', '%' . $search . '%')
    //                 ->orWhere('reference_no', 'like', '%' . $search . '%');
    //         });
    //     }

    //     $bills     = $query->paginate(25)->withQueryString();

    //     // Suppliers + Contractors only (so purchase bills stay consistent)
    //     $suppliers = Party::query()
    //         ->where(function ($q) {
    //             $q->where('is_supplier', true)
    //               ->orWhere('is_contractor', true);
    //         })
    //         ->orderBy('name')
    //         ->get();

    //     // Projects (for filter / display)
    //     $projects = Project::query()
    //         ->orderBy('code')
    //         ->orderBy('name')
    //         ->get();

    //     return view('purchase.bills.index', compact('bills', 'suppliers', 'projects'));
    // }


public function index(Request $request)
{
    $query = PurchaseBill::with([
            'supplier',
            'voucher',
            'purchaseOrder.project',
            'project',
            'expenseLines.project'
        ])
        ->orderByDesc('posting_date')
        ->orderByDesc('bill_date')
        ->orderByDesc('id');

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    // Supplier Filter
    if ($request->filled('supplier_id')) {
        $query->where('supplier_id', $request->supplier_id);
    }

    // Status Filter
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    // Project Filter (direct / PO / Expense line)
    if ($request->filled('project_id')) {
        $projectId = $request->project_id;

        $query->where(function ($q) use ($projectId) {
            $q->where('project_id', $projectId)
              ->orWhereHas('purchaseOrder', function ($qpo) use ($projectId) {
                  $qpo->where('project_id', $projectId);
              })
              ->orWhereHas('expenseLines', function ($qe) use ($projectId) {
                  $qe->where('project_id', $projectId);
              });
        });
    }

    // Search Filter
    if ($request->filled('q')) {
        $search = trim($request->q);

        $query->where(function ($q) use ($search) {
            $q->where('bill_number', 'like', "%{$search}%")
              ->orWhere('reference_no', 'like', "%{$search}%");
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    $bills = $query->paginate(25);

    /*
    |--------------------------------------------------------------------------
    | AJAX Response (No Page Reload)
    |--------------------------------------------------------------------------
    */

    if ($request->ajax()) {
        return view('purchase.bills.partials.table', compact('bills'))->render();
    }

    /*
    |--------------------------------------------------------------------------
    | Normal Page Load Data
    |--------------------------------------------------------------------------
    */

    $suppliers = Party::where(function ($q) {
            $q->where('is_supplier', true)
              ->orWhere('is_contractor', true);
        })
        ->orderBy('name')
        ->get();

    $projects = Project::orderBy('code')
        ->orderBy('name')
        ->get();

    return view('purchase.bills.index', compact('bills', 'suppliers', 'projects'));
}

    public function create()
	{
    $bill            = new PurchaseBill();
    // Bill Date = Invoice Date (GST)
    $bill->bill_date = now()->toDateString();
    // Posting Date = Voucher/Books date (Tally style)
    $bill->posting_date = $bill->bill_date;

    $company   = Company::where('is_default', true)->first();
    $companyId = $company?->id ?? 1;

    // Auto PB number in format PB/YYYY-YY/0001
    $bill->bill_number = PurchaseBill::generateNextBillNumber($companyId, $bill->bill_date);

    $suppliers  = Party::query()
        ->where(function ($q) {
            $q->where('is_supplier', true)
              ->orWhere('is_contractor', true);
        })
        ->orderBy('name')
        ->get();
    $items      = Item::orderBy('code')->get();
    $uoms       = Uom::orderBy('code')->get();
    $accounts   = Account::orderBy('name')->get();

    $projects   = Project::query()->orderBy('code')->orderBy('name')->get();

    // TDS Sections master (for dropdown)
    $tdsSections = TdsSection::query()
        ->where('company_id', $companyId)
        ->where('is_active', true)
        ->orderBy('code')
        ->get();

    $emptyLines = 5;

    return view('purchase.bills.create', compact('bill', 'suppliers', 'items', 'uoms', 'accounts', 'tdsSections', 'emptyLines', 'company', 'projects'));
	}



	public function store(
	    StorePurchaseBillRequest $request,
	    ItemGstResolver $itemGstResolver,
	    AccountGstResolver $accountGstResolver
	) {
	    $data = $request->validated();

	    // Only GRN-linked item lines are checked; expense lines are ignored by this
	    $this->validateGrnBalances($data['lines'] ?? []);

	    $bill = DB::transaction(function () use ($data, $itemGstResolver, $accountGstResolver) {
	        $userId    = Auth::id();
	        $company   = Company::where('is_default', true)->first();
	        $companyId = $company?->id ?? 1;
	        $supplier  = Party::find($data['supplier_id']);

		// Branch GSTIN selection (optional). If not selected, fallback to PO vendor branch.
		$supplierBranchId = $data['supplier_branch_id'] ?? null;
		if (empty($supplierBranchId) && ! empty($data['purchase_order_id'])) {
		    $poBranch = PurchaseOrder::find((int) $data['purchase_order_id']);
		    if ($poBranch && (int) $poBranch->vendor_party_id === (int) $data['supplier_id'] && $poBranch->vendor_branch_id) {
		        $supplierBranchId = (int) $poBranch->vendor_branch_id;
		    }
		}

		// Use branch GST/state for GST split calculation only (ledger remains against main party).
		$supplierForGst = $supplier;
		if ($supplier && ! empty($supplierBranchId)) {
		    $branch = PartyBranch::query()
		        ->where('party_id', $supplier->id)
		        ->where('id', $supplierBranchId)
		        ->first();

		    if ($branch) {
		        $supplierForGst = $supplier->replicate();
		        $supplierForGst->gstin = $branch->gstin;
		        $supplierForGst->gst_state_code = $branch->gst_state_code;
		        $supplierForGst->state = $branch->state;
		    }
		}


		// Project handling (for project direct expenses)
		$projectId = $data['project_id'] ?? null;
		if (!empty($data['purchase_order_id'])) {
			$po = PurchaseOrder::find((int) $data['purchase_order_id']);
			if ($po && $po->project_id) {
				$projectId = $po->project_id;
			}
		}

	        $billData = [
	            'company_id'        => $companyId,
	            'supplier_id'       => $data['supplier_id'],
		    'supplier_branch_id' => $supplierBranchId,
	            'purchase_order_id' => $data['purchase_order_id'] ?? null,
	            'project_id'        => $projectId,
	            'bill_number'       => $data['bill_number'],
	            'bill_date'         => $data['bill_date'],
	            // Voucher / books date (can differ from invoice date)
	            'posting_date'      => $data['posting_date'] ?? $data['bill_date'],
	            'due_date'          => $data['due_date'] ?? null,
	            // Reference No is the Supplier Invoice No.
	            'reference_no'      => $data['reference_no'] ?? null,
	            'challan_number'    => $data['challan_number'] ?? null,
	            'remarks'           => $data['remarks'] ?? null,
	            'currency'          => $data['currency'] ?? 'INR',
	            'exchange_rate'     => $data['exchange_rate'] ?? 1,
	            'tds_rate'          => $data['tds_rate'] ?? 0,
	            'tds_amount'        => $data['tds_amount'] ?? 0,
	            'tds_section'       => $data['tds_section'] ?? null,
	            'tcs_rate'          => $data['tcs_rate'] ?? 0,
	            'tcs_amount'        => $data['tcs_amount'] ?? 0,
	            'tcs_section'       => $data['tcs_section'] ?? null,
	            'status'            => 'draft',
	            'created_by'        => $userId,
	            'updated_by'        => $userId,
	        ];

	        $totalBasic    = 0.0;
	        $totalDiscount = 0.0;
	        $totalTax      = 0.0;
	        $totalCgst     = 0.0;
	        $totalSgst     = 0.0;
	        $totalIgst     = 0.0;
	        // Reverse Charge (RCM) totals – NOT added to invoice payable.
	        $totalRcmTax   = 0.0;
	        $totalRcmCgst  = 0.0;
	        $totalRcmSgst  = 0.0;
	        $totalRcmIgst  = 0.0;
	        $totalAmount   = 0.0;

	        $bill   = PurchaseBill::create($billData);
	        $lineNo = 1;

	        // 1) ITEM LINES (with ItemGstResolver)
	        foreach (($data['lines'] ?? []) as $lineInput) {
	            if (empty($lineInput['item_id']) || empty($lineInput['qty'])) {
	                continue;
	            }

	            $qty = (float) $lineInput['qty'];
	            if ($qty <= 0) {
	                continue;
	            }

	            $rate            = (float) ($lineInput['rate'] ?? 0);
	            $discountPercent = (float) ($lineInput['discount_percent'] ?? 0);
	            $taxRate         = (float) ($lineInput['tax_rate'] ?? 0);

	            // Auto GST % from item if not provided
	            if ($taxRate <= 0) {
	                $item = Item::find($lineInput['item_id']);
	                if ($item) {
	                    $rateRow = $itemGstResolver->getRateForItemOnDate($item, $bill->bill_date);
	                    if ($rateRow) {
	                        $taxRate = (float) $rateRow->igst_rate; // total GST %
	                    }
	                }
	            }

	            $gross    = $qty * $rate;
	            $discount = round($gross * $discountPercent / 100, 2);
	            $taxable  = $gross - $discount;

	            [$gstType, $cgstPct, $sgstPct, $igstPct, $cgstAmt, $sgstAmt, $igstAmt, $taxAmt] =
	                GstHelper::calculateSplit($company, $supplierForGst, $taxable, $taxRate);

	            $basic     = $taxable;
	            $totalLine = $basic + $taxAmt;

	            $totalBasic    += $basic;
	            $totalDiscount += $discount;
	            $totalTax      += $taxAmt;
	            $totalCgst     += $cgstAmt;
	            $totalSgst     += $sgstAmt;
	            $totalIgst     += $igstAmt;
	            $totalAmount   += $totalLine;

	            PurchaseBillLine::create([
	                'purchase_bill_id'         => $bill->id,
	                'material_receipt_id'      => $lineInput['material_receipt_id'] ?? null,
	                'material_receipt_line_id' => $lineInput['material_receipt_line_id'] ?? null,
	                'item_id'                  => $lineInput['item_id'],
	                'uom_id'                   => $lineInput['uom_id'] ?? null,
	                'qty'                      => $qty,
	                'rate'                     => $rate,
	                'discount_percent'         => $discountPercent,
	                'discount_amount'          => $discount,
	                'basic_amount'             => $basic,
	                'tax_rate'                 => $taxRate,
	                'tax_amount'               => $taxAmt,
	                'cgst_amount'              => $cgstAmt,
	                'sgst_amount'              => $sgstAmt,
	                'igst_amount'              => $igstAmt,
	                'total_amount'             => $totalLine,
	                'account_id'               => $lineInput['account_id'] ?? null, // override account allowed
	                'line_no'                  => $lineNo++,
	            ]);
	        }

	        // 2) EXPENSE LINES (Non-item, with AccountGstResolver)
	        if (! empty($data['expense_lines']) && is_array($data['expense_lines'])) {
	            foreach ($data['expense_lines'] as $expInput) {
	                $accountId   = $expInput['account_id'] ?? null;
	                $amount      = (float) ($expInput['amount'] ?? 0);
	                $description = $expInput['description'] ?? null;

                // Phase-B: allow splitting expense lines across projects
                // If line project is empty, default to bill header project (or PO project).
                $lineProjectId = $expInput['project_id'] ?? null;
                if (empty($lineProjectId) && !empty($projectId)) {
                    $lineProjectId = $projectId;
                }

                // If bill is linked to a PO, keep project strictly same as PO project
                if (!empty($data['purchase_order_id']) && !empty($projectId)) {
                    $lineProjectId = $projectId;
                }

	                if (! $accountId || $amount <= 0) {
	                    continue;
	                }

	                $account = Account::find($accountId);
	                if (! $account) {
	                    continue;
	                }

	                // Resolve GST slab for this ledger (used for both rate % and RCM flag).
	                $taxRate = (float) ($expInput['tax_rate'] ?? 0);
	                $rateRow = $accountGstResolver->getRateForAccountOnDate($account, $bill->bill_date);
	                $isReverseCharge = $rateRow ? (bool) $rateRow->is_reverse_charge : false;
	                if ($taxRate <= 0 && $rateRow) {
	                    $taxRate = (float) $rateRow->igst_rate;
	                }

	                $taxable  = $amount;
	                $discount = 0.0;

	                [$gstType, $cgstPct, $sgstPct, $igstPct, $cgstAmt, $sgstAmt, $igstAmt, $taxAmt] =
	                    GstHelper::calculateSplit($company, $supplierForGst, $taxable, $taxRate);

	                $basic = $taxable;
	                // Under RCM, supplier invoice does NOT include GST; we still store computed GST on the line,
	                // but exclude it from payable invoice total.
	                $totalLine = $isReverseCharge ? $basic : ($basic + $taxAmt);

	                $totalBasic    += $basic;
	                $totalDiscount += $discount;
	                if ($isReverseCharge) {
	                    $totalRcmTax  += $taxAmt;
	                    $totalRcmCgst += $cgstAmt;
	                    $totalRcmSgst += $sgstAmt;
	                    $totalRcmIgst += $igstAmt;
	                } else {
	                    $totalTax  += $taxAmt;
	                    $totalCgst += $cgstAmt;
	                    $totalSgst += $sgstAmt;
	                    $totalIgst += $igstAmt;
	                }
	                $totalAmount   += $totalLine;

	                $bill->expenseLines()->create([
	                    'account_id'   => $accountId,
	                    'project_id'   => $lineProjectId,
                    'is_reverse_charge' => $isReverseCharge,
	                    'description'  => $description,
	                    'basic_amount' => $basic,
	                    'tax_rate'     => $taxRate,
	                    'tax_amount'   => $taxAmt,
	                    'cgst_amount'  => $cgstAmt,
	                    'sgst_amount'  => $sgstAmt,
	                    'igst_amount'  => $igstAmt,
	                    'total_amount' => $totalLine,
	                    'line_no'      => $lineNo++,
	                ]);
	            }
	        }

	        // 3) Totals on bill header (items + expenses)
	        $bill->total_basic    = round($totalBasic, 2);
	        $bill->total_discount = round($totalDiscount, 2);
	        $bill->total_tax      = round($totalTax, 2);
	        $bill->total_cgst     = round($totalCgst, 2);
	        $bill->total_sgst     = round($totalSgst, 2);
	        $bill->total_igst     = round($totalIgst, 2);
	        $bill->total_rcm_tax  = round($totalRcmTax, 2);
	        $bill->total_rcm_cgst = round($totalRcmCgst, 2);
	        $bill->total_rcm_sgst = round($totalRcmSgst, 2);
	        $bill->total_rcm_igst = round($totalRcmIgst, 2);
	        // Invoice total + Round off (Tally style)
	        // - $totalAmount is calculated from line totals (RCM lines exclude GST from payable).
	        $calculatedTotal = round($totalAmount, 2);
	        $invoiceTotalInput = (array_key_exists('invoice_total', $data) && $data['invoice_total'] !== null && $data['invoice_total'] !== '')
	            ? (float) $data['invoice_total']
	            : (float) round($calculatedTotal);

	        $invoiceTotal = round($invoiceTotalInput, 2);
	        $roundOff = round($invoiceTotal - $calculatedTotal, 2);

	        // Safety: prevent huge round-off differences (usually it should be within a few rupees)
	        if (abs($roundOff) > 5) {
	            throw ValidationException::withMessages([
	                'invoice_total' => 'Invoice Total differs too much from calculated total. Please check amounts / GST / lines.',
	            ]);
	        }

	        $bill->round_off    = $roundOff;
	        $bill->total_amount = $invoiceTotal;

	        // Apply TDS section master defaults & optional auto-calculation (based on Total Basic)
	        $this->applyTdsFromMaster($bill, $data, $companyId);

	        $bill->save();

	        return $bill;
	    });

	    // Attachments are saved outside DB transaction (so the DB commit is not blocked by filesystem).
	    $this->storeBillAttachments($request, $bill);

	    return redirect()
	        ->route('purchase.bills.edit', $bill)
	        ->with('success', 'Purchase bill created as draft.');
	}

	
    public function edit(PurchaseBill $bill)
    {
        $bill->load('lines.item', 'expenseLines.account', 'expenseLines.project', 'supplier', 'purchaseOrder.project', 'project', 'attachments');

        $suppliers  = Party::query()
            ->where(function ($q) {
                $q->where('is_supplier', true)
                  ->orWhere('is_contractor', true);
            })
            ->orderBy('name')
            ->get();
        $items      = Item::orderBy('code')->get();
        $uoms       = Uom::orderBy('code')->get();
        $accounts   = Account::orderBy('name')->get();

    $projects   = Project::query()->orderBy('code')->orderBy('name')->get();

        $companyId = (int) ($bill->company_id ?: 1);
        // TDS Sections master (for dropdown)
        $tdsSections = TdsSection::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $emptyLines = 3;

        $company = Company::find($companyId) ?: Company::where('is_default', true)->first();

        return view('purchase.bills.edit', compact('bill', 'suppliers', 'items', 'uoms', 'accounts', 'tdsSections', 'emptyLines', 'company', 'projects'));
    }

   public function show(PurchaseBill $bill)
	{
	    $bill->load('lines.item', 'expenseLines.account', 'expenseLines.project', 'supplier', 'voucher', 'purchaseOrder.project', 'project', 'attachments');

    return view('purchase.bills.show', compact('bill'));
	}

  
	public function update(
	    UpdatePurchaseBillRequest $request,
	    PurchaseBill $bill,
	    ItemGstResolver $itemGstResolver,
	    AccountGstResolver $accountGstResolver
	) {
	    $data = $request->validated();

	    if ($bill->status === 'posted') {
	        return redirect()
	            ->route('purchase.bills.edit', $bill)
	            ->with('error', 'Posted bills cannot be edited.');
	    }

	    $this->validateGrnBalances($data['lines'] ?? [], $bill);

	    $bill = DB::transaction(function () use ($data, $bill, $itemGstResolver, $accountGstResolver) {
	        $userId  = Auth::id();
	        $company = Company::where('is_default', true)->first();

	        // Update header
	        $bill->supplier_id       = $data['supplier_id'];
	        $bill->purchase_order_id = $data['purchase_order_id'] ?? null;
		// Branch GSTIN selection (optional). If not selected, fallback to PO vendor branch.
		$supplierBranchId = $data['supplier_branch_id'] ?? null;
		if (empty($supplierBranchId) && ! empty($data['purchase_order_id'])) {
		    $poBranch = PurchaseOrder::find((int) $data['purchase_order_id']);
		    if ($poBranch && $poBranch->vendor_branch_id) {
		        $supplierBranchId = (int) $poBranch->vendor_branch_id;
		    }
		}
		$bill->supplier_branch_id = $supplierBranchId;

		// Project handling (for project direct expenses)
		$projectId = $data['project_id'] ?? null;
		if (!empty($data['purchase_order_id'])) {
			$po = PurchaseOrder::find((int) $data['purchase_order_id']);
			if ($po && $po->project_id) {
				$projectId = $po->project_id;
			}
		}
		$bill->project_id = $projectId;
	        $bill->bill_number       = $data['bill_number'];
	        $bill->bill_date         = $data['bill_date'];
	        $bill->posting_date      = $data['posting_date'] ?? $data['bill_date'];
	        $bill->due_date          = $data['due_date'] ?? null;
	        // Reference No is the Supplier Invoice No.
	        $bill->reference_no      = $data['reference_no'] ?? null;
	        $bill->challan_number    = $data['challan_number'] ?? null;
	        $bill->remarks           = $data['remarks'] ?? null;
	        $bill->currency          = $data['currency'] ?? 'INR';
	        $bill->exchange_rate     = $data['exchange_rate'] ?? 1;
	        $bill->tds_rate          = $data['tds_rate'] ?? 0;
	        $bill->tds_amount        = $data['tds_amount'] ?? 0;
	        $bill->tds_section       = $data['tds_section'] ?? null;
	        $bill->tcs_rate          = $data['tcs_rate'] ?? 0;
	        $bill->tcs_amount        = $data['tcs_amount'] ?? 0;
	        $bill->tcs_section       = $data['tcs_section'] ?? null;
	        $bill->status            = $data['status'] ?? $bill->status;
	        $bill->updated_by        = $userId;
	        $bill->save();

	        $supplier = Party::find($bill->supplier_id);

		// Use branch GST/state for GST split calculation only
		$supplierForGst = $supplier;
		if ($supplier && ! empty($supplierBranchId)) {
		    $branch = PartyBranch::query()
		        ->where('party_id', $supplier->id)
		        ->where('id', $supplierBranchId)
		        ->first();

		    if ($branch) {
		        $supplierForGst = $supplier->replicate();
		        $supplierForGst->gstin = $branch->gstin;
		        $supplierForGst->gst_state_code = $branch->gst_state_code;
		        $supplierForGst->state = $branch->state;
		    }
		}


	        // Track item lines for delete detection
	        $existingItemIds = $bill->lines()
	            ->whereNotNull('item_id')
	            ->pluck('id')
	            ->all();
	        $keptIds = [];

	        $totalBasic    = 0.0;
	        $totalDiscount = 0.0;
	        $totalTax      = 0.0;
	        $totalCgst     = 0.0;
	        $totalSgst     = 0.0;
	        $totalIgst     = 0.0;
	        // Reverse Charge (RCM) totals – NOT added to invoice payable.
	        $totalRcmTax   = 0.0;
	        $totalRcmCgst  = 0.0;
	        $totalRcmSgst  = 0.0;
	        $totalRcmIgst  = 0.0;
	        $totalAmount   = 0.0;

	        $lineNo = 1;

	        // 1) ITEM LINES
	        foreach (($data['lines'] ?? []) as $lineInput) {
	            if (empty($lineInput['item_id']) || empty($lineInput['qty'])) {
	                continue;
	            }

	            $lineId = $lineInput['id'] ?? null;

	            $qty = (float) $lineInput['qty'];
	            if ($qty <= 0) {
	                continue;
	            }

	            $rate            = (float) ($lineInput['rate'] ?? 0);
	            $discountPercent = (float) ($lineInput['discount_percent'] ?? 0);
	            $taxRate         = (float) ($lineInput['tax_rate'] ?? 0);

	            if ($taxRate <= 0) {
	                $item = Item::find($lineInput['item_id']);
	                if ($item) {
	                    $rateRow = $itemGstResolver->getRateForItemOnDate($item, $bill->bill_date);
	                    if ($rateRow) {
	                        $taxRate = (float) $rateRow->igst_rate;
	                    }
	                }
	            }

	            $gross    = $qty * $rate;
	            $discount = round($gross * $discountPercent / 100, 2);
	            $taxable  = $gross - $discount;

	            [$gstType, $cgstPct, $sgstPct, $igstPct, $cgstAmt, $sgstAmt, $igstAmt, $taxAmt] =
	                GstHelper::calculateSplit($company, $supplierForGst, $taxable, $taxRate);

	            $basic     = $taxable;
	            $totalLine = $basic + $taxAmt;

	            $totalBasic    += $basic;
	            $totalDiscount += $discount;
	            $totalTax      += $taxAmt;
	            $totalCgst     += $cgstAmt;
	            $totalSgst     += $sgstAmt;
	            $totalIgst     += $igstAmt;
	            $totalAmount   += $totalLine;

	            $payload = [
	                'material_receipt_id'      => $lineInput['material_receipt_id'] ?? null,
	                'material_receipt_line_id' => $lineInput['material_receipt_line_id'] ?? null,
	                'item_id'                  => $lineInput['item_id'],
	                'uom_id'                   => $lineInput['uom_id'] ?? null,
	                'qty'                      => $qty,
	                'rate'                     => $rate,
	                'discount_percent'         => $discountPercent,
	                'discount_amount'          => $discount,
	                'basic_amount'             => $basic,
	                'tax_rate'                 => $taxRate,
	                'tax_amount'               => $taxAmt,
	                'cgst_amount'              => $cgstAmt,
	                'sgst_amount'              => $sgstAmt,
	                'igst_amount'              => $igstAmt,
	                'total_amount'             => $totalLine,
	                'account_id'               => $lineInput['account_id'] ?? null,
	                'line_no'                  => $lineNo++,
	            ];

	            if ($lineId) {
	                $line = PurchaseBillLine::find($lineId);
	                if ($line) {
	                    $line->update($payload);
	                    $keptIds[] = $line->id;
	                }
	            } else {
	                $line      = $bill->lines()->create($payload);
	                $keptIds[] = $line->id;
	            }
	        }

	        // Delete removed ITEM lines
	        $toDelete = array_diff($existingItemIds, $keptIds);
	        if (! empty($toDelete)) {
	            PurchaseBillLine::whereIn('id', $toDelete)->delete();
	        }

	        // 2) EXPENSE LINES – wipe & rebuild
	        $bill->expenseLines()->delete();

	        if (! empty($data['expense_lines']) && is_array($data['expense_lines'])) {
	            foreach ($data['expense_lines'] as $expInput) {
	                $accountId   = $expInput['account_id'] ?? null;
	                $amount      = (float) ($expInput['amount'] ?? 0);
	                $description = $expInput['description'] ?? null;

                // Phase-B: allow splitting expense lines across projects
                $lineProjectId = $expInput['project_id'] ?? null;
                if (empty($lineProjectId) && !empty($projectId)) {
                    $lineProjectId = $projectId;
                }

                // If bill is linked to a PO, keep project strictly same as PO project
                if (!empty($data['purchase_order_id']) && !empty($projectId)) {
                    $lineProjectId = $projectId;
                }

	                if (! $accountId || $amount <= 0) {
	                    continue;
	                }

	                $account = Account::find($accountId);
	                if (! $account) {
	                    continue;
	                }

	                // Resolve GST slab for this ledger (used for both rate % and RCM flag).
	                $taxRate = (float) ($expInput['tax_rate'] ?? 0);
	                $rateRow = $accountGstResolver->getRateForAccountOnDate($account, $bill->bill_date);
	                $isReverseCharge = $rateRow ? (bool) $rateRow->is_reverse_charge : false;
	                if ($taxRate <= 0 && $rateRow) {
	                    $taxRate = (float) $rateRow->igst_rate;
	                }

	                $taxable  = $amount;
	                $discount = 0.0;

	                [$gstType, $cgstPct, $sgstPct, $igstPct, $cgstAmt, $sgstAmt, $igstAmt, $taxAmt] =
	                    GstHelper::calculateSplit($company, $supplierForGst, $taxable, $taxRate);

	                $basic = $taxable;
	                // Under RCM, supplier invoice does NOT include GST; we still store computed GST on the line,
	                // but exclude it from payable invoice total.
	                $totalLine = $isReverseCharge ? $basic : ($basic + $taxAmt);

	                $totalBasic    += $basic;
	                $totalDiscount += $discount;
	                if ($isReverseCharge) {
	                    $totalRcmTax  += $taxAmt;
	                    $totalRcmCgst += $cgstAmt;
	                    $totalRcmSgst += $sgstAmt;
	                    $totalRcmIgst += $igstAmt;
	                } else {
	                    $totalTax  += $taxAmt;
	                    $totalCgst += $cgstAmt;
	                    $totalSgst += $sgstAmt;
	                    $totalIgst += $igstAmt;
	                }
	                $totalAmount   += $totalLine;

	                $bill->expenseLines()->create([
	                    'account_id'   => $accountId,
	                    'project_id'   => $lineProjectId,
                    'is_reverse_charge' => $isReverseCharge,
	                    'description'  => $description,
	                    'basic_amount' => $basic,
	                    'tax_rate'     => $taxRate,
	                    'tax_amount'   => $taxAmt,
	                    'cgst_amount'  => $cgstAmt,
	                    'sgst_amount'  => $sgstAmt,
	                    'igst_amount'  => $igstAmt,
	                    'total_amount' => $totalLine,
	                    'line_no'      => $lineNo++,
	                ]);
	            }
	        }

	        // 3) Totals on bill header
	        $bill->total_basic    = round($totalBasic, 2);
	        $bill->total_discount = round($totalDiscount, 2);
	        $bill->total_tax      = round($totalTax, 2);
	        $bill->total_cgst     = round($totalCgst, 2);
	        $bill->total_sgst     = round($totalSgst, 2);
	        $bill->total_igst     = round($totalIgst, 2);
	        $bill->total_rcm_tax  = round($totalRcmTax, 2);
	        $bill->total_rcm_cgst = round($totalRcmCgst, 2);
	        $bill->total_rcm_sgst = round($totalRcmSgst, 2);
	        $bill->total_rcm_igst = round($totalRcmIgst, 2);
	        // Invoice total + Round off (Tally style)
	        // - $totalAmount is calculated from line totals (RCM lines exclude GST from payable).
	        $calculatedTotal = round($totalAmount, 2);
	        $invoiceTotalInput = (array_key_exists('invoice_total', $data) && $data['invoice_total'] !== null && $data['invoice_total'] !== '')
	            ? (float) $data['invoice_total']
	            : (float) round($calculatedTotal);

	        $invoiceTotal = round($invoiceTotalInput, 2);
	        $roundOff = round($invoiceTotal - $calculatedTotal, 2);

	        // Safety: prevent huge round-off differences (usually it should be within a few rupees)
	        if (abs($roundOff) > 5) {
	            throw ValidationException::withMessages([
	                'invoice_total' => 'Invoice Total differs too much from calculated total. Please check amounts / GST / lines.',
	            ]);
	        }

	        $bill->round_off    = $roundOff;
	        $bill->total_amount = $invoiceTotal;

	        // Apply TDS section master defaults & optional auto-calculation (based on Total Basic)
	        $companyIdForTds = (int) ($bill->company_id ?: ($company?->id ?? 1));
	        $this->applyTdsFromMaster($bill, $data, $companyIdForTds);

	        $bill->save();

	        return $bill;
	    });

	    // Optional: delete selected attachments
	    $this->deleteBillAttachments($bill, $data['attachments_delete'] ?? []);
	    // Add new attachments (if any)
	    $this->storeBillAttachments($request, $bill);

	    return redirect()
	        ->route('purchase.bills.edit', $bill)
	        ->with('success', 'Purchase bill updated.');
	}

    /**
     * Fetch POs for supplier (for Fetch from PO/GRN popup).
     * Includes:
     *  - POs where vendor_party_id = supplier_id
     *  - POs used in any GRN for that supplier
     */
    public function ajaxPurchaseOrdersForSupplier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:parties,id'],
        ]);

        $supplierId = (int) $data['supplier_id'];

        $orders = PurchaseOrder::query()
            ->with('project')
            ->where('vendor_party_id', $supplierId)
            ->whereIn('status', ['draft', 'approved'])
            ->orderByDesc('po_date')
            ->limit(100)
            ->get();

        $mapped = $orders->map(function (PurchaseOrder $po) {
            return [
                'id'           => $po->id,
                'code'         => $po->code,
                'po_date'      => $po->po_date ? $po->po_date->format('Y-m-d') : null,
                'project_id'   => $po->project_id,
                'project_code' => $po->project?->code,
                'project_name' => $po->project?->name,
                'total_amount' => (float) $po->total_amount,
				'vendor_branch_id' => $po->vendor_branch_id,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'orders'  => $mapped,
        ]);
    }

    public function ajaxGrnLinesForPurchaseOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'       => ['required', 'integer', 'exists:parties,id'],
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'bill_id'           => ['nullable', 'integer', 'exists:purchase_bills,id'],
        ]);

        $excludeBillId = ! empty($data['bill_id']) ? (int) $data['bill_id'] : null;

        $rows = DB::table('material_receipts as mr')
            ->join('material_receipt_lines as mrl', 'mrl.material_receipt_id', '=', 'mr.id')
            // Sum billed qty; while editing an existing bill, exclude its own lines so "remaining" is accurate.
            ->leftJoin('purchase_bill_lines as pbl', function ($join) use ($excludeBillId) {
                $join->on('pbl.material_receipt_line_id', '=', 'mrl.id');
                if ($excludeBillId) {
                    $join->where('pbl.purchase_bill_id', '<>', $excludeBillId);
                }
            })
            ->leftJoin('purchase_order_items as poi', 'poi.id', '=', 'mrl.purchase_order_item_id')
            ->leftJoin('items as it', 'it.id', '=', 'mrl.item_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'mrl.uom_id')
            ->where('mr.purchase_order_id', $data['purchase_order_id'])
            ->where('mr.supplier_id', $data['supplier_id'])
            ->whereIn('mr.status', ['qc_passed', 'QC_PASSED'])
            ->groupBy(
                'mr.id',
                'mr.receipt_number',
                'mr.receipt_date',
                'mr.invoice_number',
                'mr.challan_number',
                'mrl.id',
                'mrl.item_id',
                'mrl.uom_id',
                'mrl.qty_pcs',
                'mrl.received_weight_kg',
                'mrl.purchase_order_item_id',
                'poi.rate',
                'poi.tax_percent',
                'it.code',
                'it.name',
                'u.code'
            )
            ->selectRaw('
                mr.id as material_receipt_id,
                mr.receipt_number,
                mr.receipt_date,
                mr.invoice_number,
                mr.challan_number,
                mrl.id as material_receipt_line_id,
                mrl.item_id,
                mrl.uom_id,
                mrl.qty_pcs,
                COALESCE(mrl.received_weight_kg, 0) as received_weight_kg,
                mrl.purchase_order_item_id,
                poi.rate as po_rate,
                poi.tax_percent as po_tax_percent,
                it.code as item_code,
                it.name as item_name,
                u.code as uom_code,
                COALESCE(SUM(pbl.qty), 0) as billed_qty
            ')
            ->get();

        $lines = $rows->map(function ($row) {
            $grnQty = (float) $row->received_weight_kg;
            if ($grnQty <= 0 && $row->qty_pcs !== null) {
                $grnQty = (float) $row->qty_pcs;
            }

            $billedQty    = (float) $row->billed_qty;
            $remainingQty = $grnQty - $billedQty;

            if ($grnQty <= 0 || $remainingQty <= 0) {
                return null;
            }

            return [
                'material_receipt_id'      => $row->material_receipt_id,
                'material_receipt_line_id' => $row->material_receipt_line_id,
                'grn_no'                   => $row->receipt_number,
                'grn_date'                 => $row->receipt_date,
                'invoice_number'           => $row->invoice_number,
                'challan_number'           => $row->challan_number,
                'item_id'                  => $row->item_id,
                'item_code'                => $row->item_code ?? null,
                'item_name'                => $row->item_name ?? null,
                'uom_id'                   => $row->uom_id,
                'uom_code'                 => $row->uom_code ?? null,
                'qty_pcs'                  => (float) $row->qty_pcs,
                'received_weight_kg'       => (float) $row->received_weight_kg,
                'grn_qty'                  => $grnQty,
                'billed_qty'               => $billedQty,
                'remaining_qty'            => $remainingQty,
                'rate'                     => $row->po_rate !== null ? (float) $row->po_rate : 0.0,
                'tax_rate'                 => $row->po_tax_percent !== null ? (float) $row->po_tax_percent : 0.0,
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'lines'   => $lines,
        ]);
    }

    /**
     * Apply TDS section defaults and optional auto-calculation.
     *
     * Rules:
     * - If tds_section is selected and tds_rate is empty/0, it is auto-filled from TDS Sections master.
     * - If tds_auto_calculate is ON and tds_rate > 0, tds_amount is auto-calculated on Total Basic.
     */
    protected function applyTdsFromMaster(PurchaseBill $bill, array $data, int $companyId): void
    {
        $sectionCode = strtoupper(trim((string) ($data['tds_section'] ?? '')));

        // Default behavior: if section not selected, treat as no TDS
        if ($sectionCode === '') {
            $rate = isset($data['tds_rate']) ? (float) $data['tds_rate'] : 0.0;
            $amount = isset($data['tds_amount']) ? (float) $data['tds_amount'] : 0.0;

            $bill->tds_section = null;
            $bill->tds_rate = round(max(0, $rate), 4);
            $bill->tds_amount = round(max(0, $amount), 2);

            if ($bill->tds_rate <= 0) {
                $bill->tds_rate = 0.0;
                $bill->tds_amount = 0.0;
            }

            return;
        }

        $bill->tds_section = $sectionCode;

        $tdsSection = TdsSection::query()
            ->where('company_id', $companyId)
            ->where('code', $sectionCode)
            ->first();

        $rate = isset($data['tds_rate']) ? (float) $data['tds_rate'] : 0.0;
        if ($rate <= 0 && $tdsSection) {
            $rate = (float) $tdsSection->default_rate;
        }

        // Checkbox: if not present, treat as OFF
        $auto = array_key_exists('tds_auto_calculate', $data) ? (bool) $data['tds_auto_calculate'] : false;

        $amount = isset($data['tds_amount']) ? (float) $data['tds_amount'] : 0.0;
        if ($auto && $rate > 0) {
            $base = (float) ($bill->total_basic ?? 0);
            $amount = round(($base * $rate) / 100, 2);
        }

        $bill->tds_rate = round(max(0, $rate), 4);
        $bill->tds_amount = round(max(0, $amount), 2);

        if ($bill->tds_rate <= 0) {
            $bill->tds_amount = 0.0;
        }
    }

    protected function validateGrnBalances(array $lines, ?PurchaseBill $existingBill = null): void
    {
        $byGrnLine = [];
        $lineMeta  = [];

        foreach ($lines as $lineInput) {
            $grnLineId = $lineInput['material_receipt_line_id'] ?? null;
            if (empty($grnLineId)) {
                continue;
            }

            $qty = (float) ($lineInput['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $grnLineId = (int) $grnLineId;

            // Guardrail: if a line is linked to a GRN line, item/uom must remain the same.
            $itemId = isset($lineInput['item_id']) ? (int) $lineInput['item_id'] : 0;
            $uomId  = isset($lineInput['uom_id']) ? (int) $lineInput['uom_id'] : 0;

            if (! isset($lineMeta[$grnLineId])) {
                $lineMeta[$grnLineId] = ['item_id' => $itemId, 'uom_id' => $uomId];
            } else {
                if ((int) $lineMeta[$grnLineId]['item_id'] !== $itemId || (int) $lineMeta[$grnLineId]['uom_id'] !== $uomId) {
                    throw ValidationException::withMessages([
                        'lines' => ["GRN linked lines cannot be changed. Please do not change Item/UOM for GRN line #{$grnLineId}."],
                    ]);
                }
            }

            if (! isset($byGrnLine[$grnLineId])) {
                $byGrnLine[$grnLineId] = 0.0;
            }

            $byGrnLine[$grnLineId] += $qty;
        }

        if (! $byGrnLine) {
            return;
        }

        $query = DB::table('material_receipt_lines as mrl')
            ->leftJoin('purchase_bill_lines as pbl', 'pbl.material_receipt_line_id', '=', 'mrl.id');

        if ($existingBill) {
            $query->where(function ($q) use ($existingBill) {
                $q->whereNull('pbl.purchase_bill_id')
                    ->orWhere('pbl.purchase_bill_id', '!=', $existingBill->id);
            });
        }

        $rows = $query
            ->whereIn('mrl.id', array_keys($byGrnLine))
            ->groupBy('mrl.id', 'mrl.item_id', 'mrl.uom_id', 'mrl.qty_pcs', 'mrl.received_weight_kg')
            ->selectRaw('
                mrl.id,
                mrl.item_id,
                mrl.uom_id,
                mrl.qty_pcs,
                COALESCE(mrl.received_weight_kg, 0) as received_weight_kg,
                COALESCE(SUM(pbl.qty), 0) as billed_qty
            ')
            ->get()
            ->keyBy('id');

        foreach ($byGrnLine as $grnLineId => $newQty) {
            $row = $rows->get($grnLineId);
            if (! $row) {
                continue;
            }

            if (isset($lineMeta[$grnLineId])) {
                $expectedItemId = (int) ($lineMeta[$grnLineId]['item_id'] ?? 0);
                $expectedUomId  = (int) ($lineMeta[$grnLineId]['uom_id'] ?? 0);
                if ($expectedItemId && (int) $row->item_id !== $expectedItemId) {
                    throw ValidationException::withMessages([
                        'lines' => ["Item mismatch for GRN line #{$grnLineId}. Please re-fetch GRN lines."],
                    ]);
                }
                if ($expectedUomId && (int) $row->uom_id !== $expectedUomId) {
                    throw ValidationException::withMessages([
                        'lines' => ["UOM mismatch for GRN line #{$grnLineId}. Please re-fetch GRN lines."],
                    ]);
                }
            }

            $grnQty = (float) $row->received_weight_kg;
            if ($grnQty <= 0 && $row->qty_pcs !== null) {
                $grnQty = (float) $row->qty_pcs;
            }

            $totalAfter = (float) $row->billed_qty + $newQty;

            if ($grnQty > 0 && $totalAfter - $grnQty > 0.0001) {
                throw ValidationException::withMessages([
                    'lines' => ["Total billed quantity ({$totalAfter}) exceeds GRN quantity ({$grnQty}) for GRN line #{$grnLineId}."],
                ]);
            }
        }
    }

    protected function storeBillAttachments(Request $request, PurchaseBill $bill): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $files = $request->file('attachments');
        if (! is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            // Option 1: store bill docs under a bill-specific folder
            $path = $file->store('purchase_bill_attachments/' . $bill->id, 'public');

            $bill->attachments()->create([
                'category'      => 'purchase-bill',
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => $request->user()?->id,
            ]);
        }
    }

    protected function deleteBillAttachments(PurchaseBill $bill, array $attachmentIds): void
    {
        $attachmentIds = array_values(array_filter(array_map('intval', $attachmentIds)));
        if (empty($attachmentIds)) {
            return;
        }

        $disk = 'public';

        $attachments = $bill->attachments()
            ->whereIn('id', $attachmentIds)
            ->get();

        foreach ($attachments as $attachment) {
            if ($attachment->path && Storage::disk($disk)->exists($attachment->path)) {
                Storage::disk($disk)->delete($attachment->path);
            }

            $attachment->delete();
        }
    }

    public function destroy(PurchaseBill $bill)
    {
        if ($bill->status === 'posted') {
            return redirect()
                ->route('purchase.bills.index')
                ->with('error', 'Posted bills cannot be deleted.');
        }

        // Delete uploaded files + attachment records first
        $this->deleteBillAttachments($bill, $bill->attachments()->pluck('id')->all());

        $bill->delete();

        return redirect()
            ->route('purchase.bills.index')
            ->with('success', 'Purchase bill deleted.');
    }

   		public function reverse(Request $request, PurchaseBill $bill, PurchaseBillReversalService $reversalService)
	{
    if (($bill->status ?? null) !== 'posted') {
        return redirect()
            ->route('purchase.bills.show', $bill)
            ->with('error', 'Only posted bills can be reversed.');
    }

    $data = $request->validate([
        'reversal_date' => ['required', 'date'],
        'reason'        => ['nullable', 'string', 'max:500'],
    ]);

    try {
        $revVoucher = $reversalService->reverseBill(
            $bill,
            (string) $data['reversal_date'],
            $data['reason'] ?? null
        );
    } catch (\Throwable $e) {
        return redirect()
            ->route('purchase.bills.show', $bill)
            ->with('error', 'Failed to reverse bill: ' . $e->getMessage());
    }

    return redirect()
        ->route('purchase.bills.show', $bill)
        ->with('success', 'Purchase bill reversed successfully. Reversal Voucher: ' . ($revVoucher->voucher_no ?? ('#' . $revVoucher->id)));
	}
  
  	public function unallocate(Request $request, PurchaseBill $bill)
	{
    // Only allow unallocation for posted bills (not cancelled/reversed)
    if (($bill->status ?? null) !== 'posted') {
        return redirect()
            ->route('purchase.bills.show', $bill)
            ->with('error', 'Only posted bills can be un-allocated.');
    }

    $data = $request->validate([
        'allocation_id'   => ['required', 'integer', 'exists:account_bill_allocations,id'],
        'unallocate_amount' => ['nullable', 'numeric', 'gt:0'],
        'reason'          => ['nullable', 'string', 'max:500'],
    ]);

    $alloc = AccountBillAllocation::query()
        ->where('id', (int) $data['allocation_id'])
        ->firstOrFail();

    // Safety: ensure this allocation belongs to THIS bill
    // bill_type is stored in allocations; we compare with PurchaseBill::class OR fallback to 'purchase_bills'
    $billTypeOk = in_array((string) $alloc->bill_type, [PurchaseBill::class, 'purchase_bills'], true);
    if (! $billTypeOk || (int) $alloc->bill_id !== (int) $bill->id) {
        return redirect()
            ->route('purchase.bills.show', $bill)
            ->with('error', 'Invalid allocation selected for this bill.');
    }

    // Safety: don’t allow removing on-account allocations here
    if ((string) $alloc->mode !== 'against') {
        return redirect()
            ->route('purchase.bills.show', $bill)
            ->with('error', 'Only Against-Bill allocations can be un-allocated from here.');
    }

    // Calculate remaining allocated amount for this exact payment line against this bill
    $sumAllocated = (float) AccountBillAllocation::query()
        ->where('bill_type', $alloc->bill_type)
        ->where('bill_id', $bill->id)
        ->where('voucher_line_id', $alloc->voucher_line_id)
        ->where('mode', 'against')
        ->sum('amount');

    // NOTE: sum includes negative entries too
    $remaining = $sumAllocated;

    if ($remaining <= 0.009) {
        return redirect()
            ->route('purchase.bills.show', $bill)
            ->with('error', 'Nothing is allocated to remove for this payment line.');
    }

    $reqAmount = isset($data['unallocate_amount']) ? (float) $data['unallocate_amount'] : $remaining;
    if ($reqAmount > $remaining) {
        $reqAmount = $remaining; // cap to remaining
    }

    $reason = trim((string) ($data['reason'] ?? ''));
    if ($reason === '') {
        $reason = 'Un-allocated by user';
    }

    // Validate payment voucher line exists (for strong linkage)
    $payLine = VoucherLine::query()->where('id', (int) $alloc->voucher_line_id)->first();
    if (! $payLine) {
        return redirect()
            ->route('purchase.bills.show', $bill)
            ->with('error', 'Payment voucher line not found for this allocation.');
    }

    DB::transaction(function () use ($alloc, $bill, $reqAmount, $reason) {
        // Create a NEGATIVE allocation row (audit-safe; does not delete history)
        AccountBillAllocation::create([
            'company_id'      => $alloc->company_id,
            'voucher_id'      => $alloc->voucher_id,
            'voucher_line_id' => $alloc->voucher_line_id,
            'account_id'      => $alloc->account_id,
            'bill_type'       => $alloc->bill_type,
            'bill_id'         => $alloc->bill_id,
            'mode'            => 'against',
            'amount'          => -1 * round($reqAmount, 2),
            'allocation_date' => now()->toDateString(),
        ]);

        // Optional: store reason in metadata if your table has it (if not, ignore)
        // (No schema change needed for now)
    });

    return redirect()
        ->route('purchase.bills.show', $bill)
        ->with('success', 'Allocation removed successfully.');
	}

  
  
  
  	public function post(PurchaseBill $bill, PurchaseBillPostingService $postingService)
    {
        if ($bill->status === 'posted') {
            return redirect()
                ->route('purchase.bills.edit', $bill)
                ->with('info', 'Bill is already posted.');
        }

        try {
            $voucher = $postingService->postBill($bill);
        } catch (\Throwable $e) {
            return redirect()
                ->route('purchase.bills.edit', $bill)
                ->with('error', 'Failed to post bill: ' . $e->getMessage());
        }

        return redirect()
            ->route('purchase.bills.show', $bill)
            ->with('success', 'Purchase bill posted to accounting. Voucher ID: ' . $voucher->id);
    }
}



