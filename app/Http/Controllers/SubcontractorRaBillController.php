<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Accounting\Account;
use App\Services\Accounting\PartyAccountService;
use Carbon\Carbon;
use App\Models\ActivityLog;
use App\Models\Accounting\TdsSection;
use App\Models\Party;
use App\Models\Project;
use App\Models\SubcontractorRaBill;
use App\Models\SubcontractorRaBillLine;
use App\Models\SubcontractorWorkOrder;
use App\Models\Uom;
use App\Services\Accounting\AccountingDateControlService;
use App\Services\Accounting\SubcontractorRaPostingService;
use App\Services\Subcontractor\SubcontractorRaTotalsCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DEV-3: Subcontractor RA Bill Controller
 * 
 * Handles CRUD operations and workflow for Subcontractor RA Bills
 * Integrates with SubcontractorRaPostingService for accounting
 */
class SubcontractorRaBillController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:subcontractor_ra.view')->only(['index', 'show']);
        $this->middleware('permission:subcontractor_ra.create')->only(['create', 'store']);
        $this->middleware('permission:subcontractor_ra.update')->only(['edit', 'update']);
        $this->middleware('permission:subcontractor_ra.delete')->only('destroy');
        $this->middleware('permission:subcontractor_ra.approve')->only(['approve', 'reject']);
        $this->middleware('permission:subcontractor_ra.post')->only('post');
        $this->middleware('permission:subcontractor_ra.change_posting_date')->only('changePostingDate');
        $this->middleware('permission:subcontractor_ra.view|subcontractor_ra.create|subcontractor_ra.update')->only(['partySummary']);
    }

    /**
     * Display listing of RA Bills
     */
    public function index(Request $request)
    {
        $query = SubcontractorRaBill::with(['subcontractor', 'project', 'creator'])
            ->orderByDesc('bill_date')
            ->orderByDesc('id');

        // Filters
        if ($request->filled('subcontractor_id')) {
            $query->where('subcontractor_id', $request->subcontractor_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('bill_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('bill_date', '<=', $request->date_to);
        }

        $raBills = $query->paginate(20);

        $subcontractors = Party::where('is_contractor', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();

        return view('subcontractor_ra.index', compact('raBills', 'subcontractors', 'projects'));
    }

    /**
     * Show create form
     */
    public function create(Request $request)
    {
        $subcontractors = Party::where('is_contractor', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();
        $uoms = Uom::where('is_active', true)->orderBy('name')->get();

        $companyId = (int) config('accounting.default_company_id', 1);
        $tdsSections = TdsSection::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        // Pre-fill if subcontractor/project selected
        $selectedSubcontractor = null;
        $selectedProject = null;
        $previousRa = null;
        $prefillLines = null;
        $availableWorkOrders = collect();

        if ($request->filled('subcontractor_id') && $request->filled('project_id')) {
            $selectedSubcontractor = Party::find($request->subcontractor_id);
            $selectedProject = Project::find($request->project_id);
            $availableWorkOrders = $this->availableWorkOrdersForSelection(
                (int) $request->subcontractor_id,
                (int) $request->project_id
            );

            // Get previous RA for this combination
            $previousRa = SubcontractorRaBill::where('subcontractor_id', $request->subcontractor_id)
                ->where('project_id', $request->project_id)
                ->whereIn('status', ['posted', 'approved'])
                ->orderByDesc('ra_sequence')
                ->first();

            // If requested, copy previous RA lines as template for faster entry
            if ($request->boolean('copy_prev_lines') && $previousRa) {
                $previousRa->load('lines');
                $prefillLines = $previousRa->lines->map(function ($l) {
                    $prevQty = $l->cumulative_qty ?? ((float) $l->previous_qty + (float) $l->current_qty);
                    return [
                        'id' => null,
                        'boq_item_code' => $l->boq_item_code ?? '',
                        'description' => $l->description ?? '',
                        'uom_id' => $l->uom_id,
                        'contracted_qty' => $l->contracted_qty ?? 0,
                        'previous_qty' => $prevQty,
                        'current_qty' => 0,
                        'rate' => $l->rate ?? 0,
                        'remarks' => $l->remarks ?? '',
                    ];
                })->toArray();

                if (empty($prefillLines)) {
                    $prefillLines = null;
                }
            }
        }

        $nextRaNumber = SubcontractorRaBill::generateNextRaNumber();

        return view('subcontractor_ra.create', compact(
            'subcontractors',
            'projects',
            'uoms',
            'selectedSubcontractor',
            'selectedProject',
            'previousRa',
            'prefillLines',
            'nextRaNumber',
            'tdsSections',
            'availableWorkOrders'
        ));
    }

    /**
     * Store new RA Bill
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subcontractor_id' => 'required|exists:parties,id',
            'project_id'       => 'required|exists:projects,id',
            'bill_number'      => 'nullable|string|max:100',
            'bill_date'        => 'required|date',
            'posting_date'     => 'nullable|date',
            'due_date'         => 'nullable|date',
            'period_from'      => 'nullable|date',
            'period_to'        => 'nullable|date|after_or_equal:period_from',
            'work_order_id'    => 'nullable|integer|exists:subcontractor_work_orders,id',
            'work_order_number'=> 'nullable|string|max:100',
            
            // Deductions
            'retention_percent' => 'nullable|numeric|min:0|max:100',
            'retention_amount'  => 'nullable|numeric|min:0',
            'security_deposit_percent' => 'nullable|numeric|min:0|max:100',
            'advance_recovery'  => 'nullable|numeric|min:0',
            'other_deductions'  => 'nullable|numeric|min:0',
            'deduction_remarks' => 'nullable|string',
            
            // GST
            'cgst_rate'   => 'nullable|numeric|min:0|max:100',
            'sgst_rate'   => 'nullable|numeric|min:0|max:100',
            'igst_rate'   => 'nullable|numeric|min:0|max:100',
            
            // TDS
            'tds_section' => 'nullable|string|max:20',
            'tds_rate'    => 'nullable|numeric|min:0|max:100',
            'invoice_total' => 'nullable|numeric|min:0',

            'other_terms' => 'nullable|string',
            'remarks' => 'nullable|string',
            
            // Lines
            'lines'              => 'required|array|min:1',
            'lines.*.description'=> 'required|string|max:500',
            'lines.*.uom_id'     => 'nullable|exists:uoms,id',
            'lines.*.contracted_qty' => 'nullable|numeric|min:0',
            'lines.*.previous_qty'   => 'nullable|numeric|min:0',
            'lines.*.current_qty'    => 'required|numeric|min:0',
            'lines.*.rate'           => 'required|numeric|min:0',
            'lines.*.boq_item_code'  => 'nullable|string|max:50',
            'lines.*.remarks'        => 'nullable|string',
        ]);

        // Verify subcontractor
        $subcontractor = Party::findOrFail($validated['subcontractor_id']);
        if (!$subcontractor->is_contractor) {
            throw ValidationException::withMessages([
                'subcontractor_id' => 'Selected party must be a contractor/subcontractor.',
            ]);
        }

        $this->validateLineQuantityLimits($validated['lines'] ?? []);

        // Auto GST guardrail:
        // If subcontractor does not have GSTIN, force GST rates to 0.
        if (empty(trim((string) $subcontractor->gstin))) {
            $validated['cgst_rate'] = 0;
            $validated['sgst_rate'] = 0;
            $validated['igst_rate'] = 0;
        }

        $companyId = config('accounting.default_company_id', 1);
        $workOrder = $this->resolveWorkOrderForBill(
            $validated,
            (int) $companyId,
            (int) $validated['subcontractor_id'],
            (int) $validated['project_id']
        );

        // Apply TDS master defaults (section → rate)
        $this->applyTdsFromMaster($validated, (int) $companyId);

        DB::transaction(function () use ($validated, $companyId, $workOrder) {
            // Create RA Bill
            $raBill = new SubcontractorRaBill();
            $raBill->company_id = $companyId;
            $raBill->subcontractor_id = $validated['subcontractor_id'];
            $raBill->project_id = $validated['project_id'];
            $raBill->ra_number = SubcontractorRaBill::generateNextRaNumber($companyId);
            $raBill->ra_sequence = SubcontractorRaBill::getNextRaSequence(
                $validated['subcontractor_id'],
                $validated['project_id']
            );
            $raBill->bill_number = $validated['bill_number'] ?? null;
            $raBill->bill_date = $validated['bill_date'];
            $raBill->posting_date = $validated['posting_date'] ?? $validated['bill_date'];
            $raBill->period_from = $validated['period_from'] ?? null;
            $raBill->period_to = $validated['period_to'] ?? null;
            $raBill->payment_terms_days = $workOrder?->payment_terms_days;
            $raBill->due_date = $this->resolveDueDate(
                $validated['bill_date'],
                $raBill->posting_date,
                $workOrder?->payment_terms_days,
                $validated['due_date'] ?? null
            );
            $raBill->work_order_id = $workOrder?->id;
            $raBill->work_order_number = $workOrder?->work_order_number ?? ($validated['work_order_number'] ?? null);
            
            // Deductions
            $raBill->retention_percent = $workOrder?->retention_percent ?? ($validated['retention_percent'] ?? 0);
            $raBill->security_deposit_percent = $workOrder?->security_deposit_percent ?? ($validated['security_deposit_percent'] ?? 0);
            $raBill->advance_recovery = $validated['advance_recovery'] ?? 0;
            $raBill->other_deductions = $validated['other_deductions'] ?? 0;
            $raBill->deduction_remarks = $validated['deduction_remarks'] ?? null;
            
            // GST rates
            $raBill->cgst_rate = $validated['cgst_rate'] ?? 0;
            $raBill->sgst_rate = $validated['sgst_rate'] ?? 0;
            $raBill->igst_rate = $validated['igst_rate'] ?? 0;
            
            // TDS
            $raBill->tds_section = $validated['tds_section'] ?? null;
            $raBill->tds_rate = $validated['tds_rate'] ?? 0;
            
            $raBill->remarks = $validated['remarks'] ?? null;
            $raBill->other_terms = $workOrder?->other_terms ?? ($validated['other_terms'] ?? null);
            $raBill->status = 'draft';
            $raBill->created_by = Auth::id();
            $raBill->updated_by = Auth::id();
            $raBill->save();

            // Create lines
            $lineNo = 1;
            foreach ($validated['lines'] as $lineData) {
                $line = new SubcontractorRaBillLine();
                $line->subcontractor_ra_bill_id = $raBill->id;
                $line->line_no = $lineNo++;
                $line->boq_item_code = $lineData['boq_item_code'] ?? null;
                $line->description = $lineData['description'];
                $line->uom_id = $lineData['uom_id'] ?? null;
                $line->contracted_qty = $lineData['contracted_qty'] ?? 0;
                $line->previous_qty = $lineData['previous_qty'] ?? 0;
                $line->current_qty = $lineData['current_qty'];
                $line->rate = $lineData['rate'];
                $line->remarks = $lineData['remarks'] ?? null;
                $line->calculateAmounts();
                $line->save();
            }

            // Recalculate bill totals
            $this->recalculateBillTotals(
                $raBill,
                array_key_exists('invoice_total', $validated) && $validated['invoice_total'] !== null && $validated['invoice_total'] !== ''
                    ? (float) $validated['invoice_total']
                    : null
            );
        });

        return redirect()
            ->route('accounting.subcontractor-ra.index')
            ->with('success', 'Subcontractor RA Bill created successfully.');
    }

    /**
     * Show RA Bill details
     */
    public function show(SubcontractorRaBill $subcontractorRa)
    {
        $subcontractorRa->load(['subcontractor', 'project', 'workOrder', 'lines.uom', 'voucher', 'creator', 'approvedBy']);
        
        return view('subcontractor_ra.show', compact('subcontractorRa'));
    }

    /**
     * Show edit form
     */
    public function edit(SubcontractorRaBill $subcontractorRa)
    {
        if (! in_array((string) $subcontractorRa->status, ['draft', 'rejected'], true)) {
            return redirect()
                ->route('accounting.subcontractor-ra.show', ['subcontractorRa' => $subcontractorRa])
                ->with('error', 'Only draft or rejected RA Bills can be edited.');
        }

        $subcontractorRa->load('lines');
        $subcontractors = Party::where('is_contractor', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();
        $uoms = Uom::where('is_active', true)->orderBy('name')->get();
        $availableWorkOrders = $this->availableWorkOrdersForSelection(
            (int) $subcontractorRa->subcontractor_id,
            (int) $subcontractorRa->project_id
        );

        $companyId = (int) ($subcontractorRa->company_id ?: config('accounting.default_company_id', 1));
        $tdsSections = TdsSection::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('subcontractor_ra.edit', compact('subcontractorRa', 'subcontractors', 'projects', 'uoms', 'tdsSections', 'availableWorkOrders'));
    }

    /**
     * Update RA Bill
     */
    public function update(Request $request, SubcontractorRaBill $subcontractorRa)
    {
        if (! in_array((string) $subcontractorRa->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or rejected RA Bills can be edited.',
            ]);
        }

        $validated = $request->validate([
            'bill_number'      => 'nullable|string|max:100',
            'bill_date'        => 'required|date',
            'posting_date'     => 'nullable|date',
            'due_date'         => 'nullable|date',
            'period_from'      => 'nullable|date',
            'period_to'        => 'nullable|date|after_or_equal:period_from',
            'work_order_id'    => 'nullable|integer|exists:subcontractor_work_orders,id',
            'work_order_number'=> 'nullable|string|max:100',
            
            'retention_percent' => 'nullable|numeric|min:0|max:100',
            'retention_amount'  => 'nullable|numeric|min:0',
            'security_deposit_percent' => 'nullable|numeric|min:0|max:100',
            'advance_recovery'  => 'nullable|numeric|min:0',
            'other_deductions'  => 'nullable|numeric|min:0',
            'deduction_remarks' => 'nullable|string',
            
            'cgst_rate'   => 'nullable|numeric|min:0|max:100',
            'sgst_rate'   => 'nullable|numeric|min:0|max:100',
            'igst_rate'   => 'nullable|numeric|min:0|max:100',
            
            'tds_section' => 'nullable|string|max:20',
            'tds_rate'    => 'nullable|numeric|min:0|max:100',
            'invoice_total' => 'nullable|numeric|min:0',

            'other_terms' => 'nullable|string',
            'remarks' => 'nullable|string',
            
            'lines'              => 'required|array|min:1',
            'lines.*.id'         => 'nullable|exists:subcontractor_ra_bill_lines,id',
            'lines.*.description'=> 'required|string|max:500',
            'lines.*.uom_id'     => 'nullable|exists:uoms,id',
            'lines.*.contracted_qty' => 'nullable|numeric|min:0',
            'lines.*.previous_qty'   => 'nullable|numeric|min:0',
            'lines.*.current_qty'    => 'required|numeric|min:0',
            'lines.*.rate'           => 'required|numeric|min:0',
            'lines.*.boq_item_code'  => 'nullable|string|max:50',
            'lines.*.remarks'        => 'nullable|string',
        ]);

        // Apply TDS master defaults (section → rate)
        $companyId = (int) ($subcontractorRa->company_id ?: config('accounting.default_company_id', 1));
        $this->applyTdsFromMaster($validated, $companyId);
        $workOrder = $this->resolveWorkOrderForBill(
            $validated,
            $companyId,
            (int) $subcontractorRa->subcontractor_id,
            (int) $subcontractorRa->project_id
        );

        foreach (($validated['lines'] ?? []) as $index => $lineData) {
            if (empty($lineData['id'])) {
                continue;
            }

            $belongsToBill = $subcontractorRa->lines()
                ->whereKey((int) $lineData['id'])
                ->exists();

            if (! $belongsToBill) {
                throw ValidationException::withMessages([
                    'lines.' . $index . '.id' => 'The selected line does not belong to this RA Bill.',
                ]);
            }
        }

        // Auto GST guardrail:
        // If subcontractor does not have GSTIN, force GST rates to 0.
        $party = Party::find($subcontractorRa->subcontractor_id);
        if ($party && empty(trim((string) $party->gstin))) {
            $validated['cgst_rate'] = 0;
            $validated['sgst_rate'] = 0;
            $validated['igst_rate'] = 0;
        }

        $this->validateLineQuantityLimits($validated['lines'] ?? []);

        DB::transaction(function () use ($validated, $subcontractorRa, $workOrder) {
            $postingDate = $validated['posting_date'] ?? $validated['bill_date'];
            // Update header
            $subcontractorRa->fill([
                'bill_number'       => $validated['bill_number'] ?? null,
                'bill_date'         => $validated['bill_date'],
                'posting_date'      => $postingDate,
                'due_date'          => $this->resolveDueDate(
                    $validated['bill_date'],
                    $postingDate,
                    $workOrder?->payment_terms_days,
                    $validated['due_date'] ?? null
                ),
                'period_from'       => $validated['period_from'] ?? null,
                'period_to'         => $validated['period_to'] ?? null,
                'work_order_id'     => $workOrder?->id,
                'work_order_number' => $workOrder?->work_order_number ?? ($validated['work_order_number'] ?? null),
                'payment_terms_days' => $workOrder?->payment_terms_days,
                'retention_percent' => $workOrder?->retention_percent ?? ($validated['retention_percent'] ?? 0),
                'security_deposit_percent' => $workOrder?->security_deposit_percent ?? ($validated['security_deposit_percent'] ?? 0),
                'advance_recovery'  => $validated['advance_recovery'] ?? 0,
                'other_deductions'  => $validated['other_deductions'] ?? 0,
                'deduction_remarks' => $validated['deduction_remarks'] ?? null,
                'cgst_rate'         => $validated['cgst_rate'] ?? 0,
                'sgst_rate'         => $validated['sgst_rate'] ?? 0,
                'igst_rate'         => $validated['igst_rate'] ?? 0,
                'tds_section'       => $validated['tds_section'] ?? null,
                'tds_rate'          => $validated['tds_rate'] ?? 0,
                'remarks'           => $validated['remarks'] ?? null,
                'other_terms'       => $workOrder?->other_terms ?? ($validated['other_terms'] ?? null),
                'updated_by'        => Auth::id(),
            ]);
            $subcontractorRa->save();

            // Handle lines (delete removed, update existing, create new)
            $existingLineIds = collect($validated['lines'])
                ->pluck('id')
                ->filter()
                ->toArray();

            // Delete removed lines
            $subcontractorRa->lines()
                ->whereNotIn('id', $existingLineIds)
                ->delete();

            // Update/create lines
            $lineNo = 1;
            foreach ($validated['lines'] as $lineData) {
                $lineAttributes = [
                    'line_no'        => $lineNo++,
                    'boq_item_code'  => $lineData['boq_item_code'] ?? null,
                    'description'    => $lineData['description'],
                    'uom_id'         => $lineData['uom_id'] ?? null,
                    'contracted_qty' => $lineData['contracted_qty'] ?? 0,
                    'previous_qty'   => $lineData['previous_qty'] ?? 0,
                    'current_qty'    => $lineData['current_qty'],
                    'rate'           => $lineData['rate'],
                    'remarks'        => $lineData['remarks'] ?? null,
                ];

                if (!empty($lineData['id'])) {
                    $line = $subcontractorRa->lines()->find($lineData['id']);
                    if ($line) {
                        $line->fill($lineAttributes);
                        $line->calculateAmounts();
                        $line->save();
                    }
                } else {
                    $line = new SubcontractorRaBillLine($lineAttributes);
                    $line->subcontractor_ra_bill_id = $subcontractorRa->id;
                    $line->calculateAmounts();
                    $line->save();
                }
            }

            // Recalculate totals
            $this->recalculateBillTotals(
                $subcontractorRa,
                array_key_exists('invoice_total', $validated) && $validated['invoice_total'] !== null && $validated['invoice_total'] !== ''
                    ? (float) $validated['invoice_total']
                    : null
            );
        });

        return redirect()
            ->route('accounting.subcontractor-ra.show', $subcontractorRa)
            ->with('success', 'Subcontractor RA Bill updated successfully.');
    }

    /**
     * Submit for approval
     */
    public function submit(SubcontractorRaBill $subcontractorRa)
    {
        if (! in_array((string) $subcontractorRa->status, ['draft', 'rejected'], true)) {
            return back()->with('error', 'Only draft or rejected RA Bills can be submitted.');
        }

        if ($subcontractorRa->current_amount <= 0) {
            return back()->with('error', 'RA Bill must have a positive current amount.');
        }

        if ((float) $subcontractorRa->net_amount < -0.0001) {
            return back()->with('error', 'RA Bill deductions cannot exceed current amount.');
        }

        $subcontractorRa->status = 'submitted';
        $subcontractorRa->updated_by = Auth::id();
        $subcontractorRa->save();

        return back()->with('success', 'RA Bill submitted for approval.');
    }

    /**
     * Approve RA Bill
     */
    public function approve(SubcontractorRaBill $subcontractorRa)
    {
        if (!$subcontractorRa->canBeApproved()) {
            return back()->with('error', 'RA Bill cannot be approved in current state.');
        }

        $subcontractorRa->status = 'approved';
        $subcontractorRa->approved_at = now();
        $subcontractorRa->approved_by = Auth::id();
        $subcontractorRa->updated_by = Auth::id();
        $subcontractorRa->save();

        return back()->with('success', 'RA Bill approved successfully.');
    }

    /**
     * Reject RA Bill
     */
    public function reject(Request $request, SubcontractorRaBill $subcontractorRa)
    {
        if ($subcontractorRa->status !== 'submitted') {
            return back()->with('error', 'Only submitted RA Bills can be rejected.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $subcontractorRa->status = 'rejected';
        $subcontractorRa->remarks = ($subcontractorRa->remarks ?? '') . 
            "\n[Rejected: " . $request->rejection_reason . "]";
        $subcontractorRa->updated_by = Auth::id();
        $subcontractorRa->save();

        return back()->with('success', 'RA Bill rejected.');
    }

    /**
     * Post RA Bill to accounts
     */
    public function post(SubcontractorRaBill $subcontractorRa, SubcontractorRaPostingService $postingService)
    {
        if (!$subcontractorRa->canBePosted()) {
            return back()->with('error', 'RA Bill cannot be posted. It must be approved and not already posted.');
        }

        try {
            $voucher = $postingService->post($subcontractorRa);

            return redirect()
                ->route('accounting.subcontractor-ra.show', $subcontractorRa)
                ->with('success', 'RA Bill posted to accounts. Voucher: ' . $voucher->voucher_no);
        } catch (\Exception $e) {
            return back()->with('error', 'Posting failed: ' . $e->getMessage());
        }
    }

    /**
     * Reverse posted RA Bill
     */
    public function reverse(Request $request, SubcontractorRaBill $subcontractorRa, SubcontractorRaPostingService $postingService)
    {
        $data = $request->validate([
            'reversal_date' => 'required|date',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $reversalVoucher = $postingService->reverse($subcontractorRa, $data['reversal_date'], $data['reason']);

            return redirect()
                ->route('accounting.subcontractor-ra.show', $subcontractorRa)
                ->with('success', 'RA Bill posting reversed. Reversal voucher: ' . $reversalVoucher->voucher_no);
        } catch (\Exception $e) {
            return back()->with('error', 'Reversal failed: ' . $e->getMessage());
        }
    }

    public function changePostingDate(Request $request, SubcontractorRaBill $subcontractorRa)
    {
        if (($subcontractorRa->status ?? null) !== 'posted') {
            return redirect()
                ->route('accounting.subcontractor-ra.show', ['subcontractorRa' => $subcontractorRa])
                ->with('error', 'Only posted subcontractor RA Bills can have posting date corrected.');
        }

        if (! $subcontractorRa->voucher_id) {
            return redirect()
                ->route('accounting.subcontractor-ra.show', ['subcontractorRa' => $subcontractorRa])
                ->with('error', 'Cannot change posting date because linked accounting voucher is missing.');
        }

        $postingDateRules = ['required', 'date', 'before_or_equal:today'];
        if ($subcontractorRa->bill_date) {
            $postingDateRules[] = 'after_or_equal:' . $subcontractorRa->bill_date->toDateString();
        }

        $data = $request->validate([
            'posting_date' => $postingDateRules,
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $newPostingDate = (string) $data['posting_date'];
        $reason = trim((string) $data['reason']);
        $currentPostingDate = optional($subcontractorRa->posting_date ?: $subcontractorRa->bill_date)->toDateString();

        if ($currentPostingDate === $newPostingDate) {
            return redirect()
                ->route('accounting.subcontractor-ra.show', ['subcontractorRa' => $subcontractorRa])
                ->with('info', 'Posting date is already set to the selected date.');
        }

        app(AccountingDateControlService::class)->assertDateOpenForValidation(
            $newPostingDate,
            (int) ($subcontractorRa->company_id ?: config('accounting.default_company_id', 1)),
            'posting_date',
            'Posting date'
        );

        try {
            DB::transaction(function () use ($subcontractorRa, $newPostingDate, $reason) {
                $lockedBill = SubcontractorRaBill::query()
                    ->lockForUpdate()
                    ->findOrFail($subcontractorRa->id);

                if (($lockedBill->status ?? null) !== 'posted') {
                    throw ValidationException::withMessages([
                        'posting_date' => 'Only posted subcontractor RA Bills can have posting date corrected.',
                    ]);
                }

                if (! $lockedBill->voucher_id) {
                    throw ValidationException::withMessages([
                        'posting_date' => 'Linked accounting voucher is missing for this RA Bill.',
                    ]);
                }

                $voucher = $lockedBill->voucher()
                    ->lockForUpdate()
                    ->first();

                if (! $voucher) {
                    throw ValidationException::withMessages([
                        'posting_date' => 'Linked accounting voucher could not be found.',
                    ]);
                }

                $oldBillValues = [
                    'posting_date' => optional($lockedBill->posting_date)->toDateString(),
                ];
                $oldVoucherValues = [
                    'voucher_date' => optional($voucher->voucher_date)->toDateString(),
                ];

                SubcontractorRaBill::query()
                    ->whereKey($lockedBill->id)
                    ->update([
                        'posting_date' => $newPostingDate,
                        'updated_at' => now(),
                    ]);
                $lockedBill->posting_date = $newPostingDate;

                $voucher->voucher_date = $newPostingDate;
                $voucher->save();

                ActivityLog::logCustom(
                    'posting_date_corrected',
                    'Subcontractor RA Bill ' . ($lockedBill->ra_number ?: ('#' . $lockedBill->id))
                    . ' posting date changed from '
                    . ($oldBillValues['posting_date'] ?: 'blank')
                    . ' to '
                    . $newPostingDate,
                    $lockedBill,
                    [
                        'reason' => $reason,
                        'old_posting_date' => $oldBillValues['posting_date'],
                        'new_posting_date' => $newPostingDate,
                        'voucher_id' => $voucher->id,
                        'old_voucher_date' => $oldVoucherValues['voucher_date'],
                        'new_voucher_date' => $newPostingDate,
                    ]
                );

                ActivityLog::logUpdated(
                    $voucher,
                    $oldVoucherValues,
                    'Voucher date corrected for subcontractor RA Bill ' . ($lockedBill->ra_number ?: ('#' . $lockedBill->id))
                );
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return redirect()
                ->route('accounting.subcontractor-ra.show', ['subcontractorRa' => $subcontractorRa])
                ->with('error', 'Failed to change posting date: ' . $e->getMessage());
        }

        return redirect()
            ->route('accounting.subcontractor-ra.show', ['subcontractorRa' => $subcontractorRa])
            ->with('success', 'Posting date updated successfully.');
    }

    /**
     * Delete RA Bill
     */
    public function destroy(SubcontractorRaBill $subcontractorRa)
    {
        if (! in_array((string) $subcontractorRa->status, ['draft', 'rejected'], true)) {
            return back()->with('error', 'Only draft or rejected RA Bills can be deleted.');
        }

        $subcontractorRa->lines()->delete();
        $subcontractorRa->delete();

        return redirect()
            ->route('accounting.subcontractor-ra.index')
            ->with('success', 'RA Bill deleted successfully.');
    }





    /**
     * AJAX helper: return subcontractor/party ledger summary (advance/payable) as of a date.
     *
     * Used in RA Bill form to show "available advance" from ledger so user can decide recovery.
     *
     * Query params:
     * - party_id (required)
     * - project_id (optional)
     * - as_of (optional, defaults to today)
     */
    public function partySummary(Request $request)
    {
        $data = $request->validate([
            'party_id'   => ['required', 'integer', 'exists:parties,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'as_of'      => ['nullable', 'date'],
        ]);

        $party = Party::findOrFail((int) $data['party_id']);
        $projectId = $data['project_id'] ?? null;
        $projectId = $projectId ? (int) $projectId : null;

        $companyId = (int) (config('accounting.default_company_id', 1));

        $asOf = $data['as_of'] ?? null;
        $asOfDate = $asOf ? Carbon::parse((string) $asOf)->toDateString() : now()->toDateString();

        /** @var PartyAccountService $partyAccountService */
        $partyAccountService = app(PartyAccountService::class);
        $account = $partyAccountService->syncAccountForParty($party, $companyId);

        if (! $account instanceof Account) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve party ledger account.',
            ], 422);
        }

        // Overall balance includes opening balance.
        $opening = 0.0;
        if ((float) ($account->opening_balance ?? 0) !== 0.0) {
            $opening = (float) $account->opening_balance;

            // Opening applies only if effective on/before asOfDate
            if ($account->opening_balance_date && $account->opening_balance_date->gt(Carbon::parse($asOfDate))) {
                $opening = 0.0;
            } else {
                if (($account->opening_balance_type ?? 'dr') === 'cr') {
                    $opening *= -1;
                }
            }
        }

        // Overall movements (posted) up to asOfDate.
        $overallQuery = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->where('vl.account_id', $account->id)
            ->whereDate('v.voucher_date', '<=', $asOfDate);

        // Respect opening_balance_date cut-off (company-level logic)
        if ($account->opening_balance_date) {
            $overallQuery->whereDate('v.voucher_date', '>=', $account->opening_balance_date->toDateString());
        }

        $overallAgg = (clone $overallQuery)
            ->selectRaw('COALESCE(SUM(vl.debit),0) as debit_total, COALESCE(SUM(vl.credit),0) as credit_total')
            ->first();

        $overallDebit  = (float) ($overallAgg->debit_total ?? 0);
        $overallCredit = (float) ($overallAgg->credit_total ?? 0);
        $overallNet    = $opening + ($overallDebit - $overallCredit);

        $projectRow = null;
        if ($projectId) {
            $projectQuery = DB::table('voucher_lines as vl')
                ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
                ->where('v.company_id', $companyId)
                ->where('v.status', 'posted')
                ->where('vl.account_id', $account->id)
                ->where('v.project_id', $projectId)
                ->whereDate('v.voucher_date', '<=', $asOfDate);

            $projectAgg = (clone $projectQuery)
                ->selectRaw('COALESCE(SUM(vl.debit),0) as debit_total, COALESCE(SUM(vl.credit),0) as credit_total')
                ->first();

            $projectDebit  = (float) ($projectAgg->debit_total ?? 0);
            $projectCredit = (float) ($projectAgg->credit_total ?? 0);
            $projectNet    = $projectDebit - $projectCredit;

            $projectRow = [
                'project_id' => $projectId,
                'debit'      => round($projectDebit, 2),
                'credit'     => round($projectCredit, 2),
                'net'        => round($projectNet, 2),
                'advance'    => round(max(0, $projectNet), 2),
                'payable'    => round(max(0, -$projectNet), 2),
            ];
        }

        return response()->json([
            'success'   => true,
            'party_id'  => (int) $party->id,
            'party_name'=> $party->name,
            'gstin'     => $party->gstin,
            'has_gstin' => !empty(trim((string) $party->gstin)),
            'as_of'     => $asOfDate,
            'account_id'=> (int) $account->id,
            'overall'   => [
                'opening' => round($opening, 2),
                'debit'   => round($overallDebit, 2),
                'credit'  => round($overallCredit, 2),
                'net'     => round($overallNet, 2),
                'advance' => round(max(0, $overallNet), 2),
                'payable' => round(max(0, -$overallNet), 2),
            ],
            'project'   => $projectRow,
        ]);
    }

    /**
     * Apply TDS master defaults:
     * - If tds_section exists in master and tds_rate is empty/0 → use default_rate.
     */
    protected function applyTdsFromMaster(array &$validated, int $companyId): void
    {
        $code = trim((string) ($validated['tds_section'] ?? ''));

        if ($code === '') {
            $validated['tds_section'] = null;
            return;
        }

        // If master table/model is not available for some reason, keep as-is.
        try {
            $sec = TdsSection::where('company_id', $companyId)
                ->where('is_active', true)
                ->where('code', $code)
                ->first();
        } catch (\Throwable $e) {
            return;
        }

        if (!$sec) {
            return;
        }

        $validated['tds_section'] = $sec->code;

        $currentRate = (float) ($validated['tds_rate'] ?? 0);
        if ($sec->default_rate > 0 && $currentRate <= 0) {
            $validated['tds_rate'] = (float) $sec->default_rate;
        }
    }

    protected function availableWorkOrdersForSelection(?int $subcontractorId = null, ?int $projectId = null)
    {
        $query = SubcontractorWorkOrder::query()
            ->with(['subcontractor', 'project'])
            ->where('company_id', (int) config('accounting.default_company_id', 1))
            ->whereIn('status', ['draft', 'active', 'closed'])
            ->orderByDesc('work_order_date')
            ->orderBy('work_order_number');

        if ($subcontractorId) {
            $query->where('subcontractor_id', $subcontractorId);
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query->get();
    }

    protected function resolveWorkOrderForBill(array $validated, int $companyId, int $subcontractorId, int $projectId): ?SubcontractorWorkOrder
    {
        $workOrderId = (int) ($validated['work_order_id'] ?? 0);
        if ($workOrderId <= 0) {
            return null;
        }

        $workOrder = SubcontractorWorkOrder::query()
            ->where('company_id', $companyId)
            ->where('subcontractor_id', $subcontractorId)
            ->where('project_id', $projectId)
            ->where('id', $workOrderId)
            ->where('status', '<>', 'cancelled')
            ->first();

        if (! $workOrder) {
            throw ValidationException::withMessages([
                'work_order_id' => 'Select a valid work order for the chosen subcontractor and project.',
            ]);
        }

        return $workOrder;
    }

    protected function resolveDueDate(string $billDate, string $postingDate, ?int $paymentTermsDays = null, ?string $requestedDueDate = null): string
    {
        $posting = Carbon::parse($postingDate)->startOfDay();
        $due = null;

        if ($paymentTermsDays !== null) {
            $due = Carbon::parse($billDate)->startOfDay()->addDays(max(0, $paymentTermsDays));
        } elseif (! empty($requestedDueDate)) {
            $due = Carbon::parse($requestedDueDate)->startOfDay();
        }

        if (! $due) {
            return $posting->toDateString();
        }

        if ($due->lt($posting)) {
            $due = $posting;
        }

        return $due->toDateString();
    }

    /**
     * Recalculate bill totals from lines
     */
    protected function recalculateBillTotals(SubcontractorRaBill $raBill, ?float $invoiceTotalInput = null): void
    {
        $raBill->refresh();
        $raBill->load('lines');

        $totals = app(SubcontractorRaTotalsCalculator::class)->calculateForBill($raBill, $raBill->lines, $invoiceTotalInput);
        $raBill->fill($totals['header']);

        if ($raBill->net_amount < -0.0001) {
            throw ValidationException::withMessages([
                'advance_recovery' => 'Total deductions cannot exceed current amount.',
            ]);
        }

        if (abs((float) ($raBill->round_off ?? 0)) > 5) {
            throw ValidationException::withMessages([
                'invoice_total' => 'Invoice Total differs too much from calculated total. Please check work lines, GST, and deductions.',
            ]);
        }

        $raBill->save();
    }

    protected function validateLineQuantityLimits(array $lines): void
    {
        foreach ($lines as $index => $lineData) {
            $contractedQty = (float) ($lineData['contracted_qty'] ?? 0);
            $previousQty = (float) ($lineData['previous_qty'] ?? 0);
            $currentQty = (float) ($lineData['current_qty'] ?? 0);

            if ($contractedQty <= 0) {
                continue;
            }

            if (($previousQty + $currentQty) - $contractedQty > 0.0001) {
                throw ValidationException::withMessages([
                    'lines.' . $index . '.current_qty' => 'Current quantity exceeds the remaining contracted quantity for this line.',
                ]);
            }
        }
    }
}
