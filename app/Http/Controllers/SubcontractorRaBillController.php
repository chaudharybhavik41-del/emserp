<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Accounting\Account;
use App\Services\Accounting\PartyAccountService;
use Carbon\Carbon;
use App\Models\Accounting\TdsSection;
use App\Models\Party;
use App\Models\Project;
use App\Models\SubcontractorRaBill;
use App\Models\SubcontractorRaBillLine;
use App\Models\Uom;
use App\Services\Accounting\SubcontractorRaPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\MoneyHelper;

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

        if ($request->filled('subcontractor_id') && $request->filled('project_id')) {
            $selectedSubcontractor = Party::find($request->subcontractor_id);
            $selectedProject = Project::find($request->project_id);

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
            'tdsSections'
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
            'due_date'         => 'nullable|date|after_or_equal:bill_date',
            'period_from'      => 'nullable|date',
            'period_to'        => 'nullable|date|after_or_equal:period_from',
            'work_order_number'=> 'nullable|string|max:100',
            
            // Deductions
            'retention_percent' => 'nullable|numeric|min:0|max:100',
            'retention_amount'  => 'nullable|numeric|min:0',
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

        // Auto GST guardrail:
        // If subcontractor does not have GSTIN, force GST rates to 0.
        if (empty(trim((string) $subcontractor->gstin))) {
            $validated['cgst_rate'] = 0;
            $validated['sgst_rate'] = 0;
            $validated['igst_rate'] = 0;
        }

        $companyId = config('accounting.default_company_id', 1);

        // Apply TDS master defaults (section → rate)
        $this->applyTdsFromMaster($validated, (int) $companyId);

        DB::transaction(function () use ($validated, $companyId) {
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
            $raBill->due_date = $validated['due_date'] ?? null;
            $raBill->period_from = $validated['period_from'] ?? null;
            $raBill->period_to = $validated['period_to'] ?? null;
            $raBill->work_order_number = $validated['work_order_number'] ?? null;
            
            // Deductions
            $raBill->retention_percent = $validated['retention_percent'] ?? 0;
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
            $this->recalculateBillTotals($raBill);
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
        $subcontractorRa->load(['subcontractor', 'project', 'lines.uom', 'voucher', 'creator', 'approvedBy']);
        
        return view('subcontractor_ra.show', compact('subcontractorRa'));
    }

    /**
     * Show edit form
     */
    public function edit(SubcontractorRaBill $subcontractorRa)
    {
        if ($subcontractorRa->isPosted()) {
            return redirect()
                ->route('accounting.subcontractor-ra.show', $subcontractorRa)
                ->with('error', 'Posted RA Bills cannot be edited.');
        }

        $subcontractorRa->load('lines');
        $subcontractors = Party::where('is_contractor', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();
        $uoms = Uom::where('is_active', true)->orderBy('name')->get();

        $companyId = (int) ($subcontractorRa->company_id ?: config('accounting.default_company_id', 1));
        $tdsSections = TdsSection::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('subcontractor_ra.edit', compact('subcontractorRa', 'subcontractors', 'projects', 'uoms', 'tdsSections'));
    }

    /**
     * Update RA Bill
     */
    public function update(Request $request, SubcontractorRaBill $subcontractorRa)
    {
        if ($subcontractorRa->isPosted()) {
            throw ValidationException::withMessages([
                'status' => 'Posted RA Bills cannot be edited.',
            ]);
        }

        $validated = $request->validate([
            'bill_number'      => 'nullable|string|max:100',
            'bill_date'        => 'required|date',
            'due_date'         => 'nullable|date|after_or_equal:bill_date',
            'period_from'      => 'nullable|date',
            'period_to'        => 'nullable|date|after_or_equal:period_from',
            'work_order_number'=> 'nullable|string|max:100',
            
            'retention_percent' => 'nullable|numeric|min:0|max:100',
            'retention_amount'  => 'nullable|numeric|min:0',
            'advance_recovery'  => 'nullable|numeric|min:0',
            'other_deductions'  => 'nullable|numeric|min:0',
            'deduction_remarks' => 'nullable|string',
            
            'cgst_rate'   => 'nullable|numeric|min:0|max:100',
            'sgst_rate'   => 'nullable|numeric|min:0|max:100',
            'igst_rate'   => 'nullable|numeric|min:0|max:100',
            
            'tds_section' => 'nullable|string|max:20',
            'tds_rate'    => 'nullable|numeric|min:0|max:100',
            
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

        // Auto GST guardrail:
        // If subcontractor does not have GSTIN, force GST rates to 0.
        $party = Party::find($subcontractorRa->subcontractor_id);
        if ($party && empty(trim((string) $party->gstin))) {
            $validated['cgst_rate'] = 0;
            $validated['sgst_rate'] = 0;
            $validated['igst_rate'] = 0;
        }

        DB::transaction(function () use ($validated, $subcontractorRa) {
            // Update header
            $subcontractorRa->fill([
                'bill_number'       => $validated['bill_number'] ?? null,
                'bill_date'         => $validated['bill_date'],
                'due_date'          => $validated['due_date'] ?? null,
                'period_from'       => $validated['period_from'] ?? null,
                'period_to'         => $validated['period_to'] ?? null,
                'work_order_number' => $validated['work_order_number'] ?? null,
                'retention_percent' => $validated['retention_percent'] ?? 0,
                'advance_recovery'  => $validated['advance_recovery'] ?? 0,
                'other_deductions'  => $validated['other_deductions'] ?? 0,
                'deduction_remarks' => $validated['deduction_remarks'] ?? null,
                'cgst_rate'         => $validated['cgst_rate'] ?? 0,
                'sgst_rate'         => $validated['sgst_rate'] ?? 0,
                'igst_rate'         => $validated['igst_rate'] ?? 0,
                'tds_section'       => $validated['tds_section'] ?? null,
                'tds_rate'          => $validated['tds_rate'] ?? 0,
                'remarks'           => $validated['remarks'] ?? null,
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
                    $line = SubcontractorRaBillLine::find($lineData['id']);
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
            $this->recalculateBillTotals($subcontractorRa);
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
        if ($subcontractorRa->status !== 'draft') {
            return back()->with('error', 'Only draft RA Bills can be submitted.');
        }

        if ($subcontractorRa->current_amount <= 0) {
            return back()->with('error', 'RA Bill must have a positive current amount.');
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
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $reversalVoucher = $postingService->reverse($subcontractorRa, $request->reason);

            return redirect()
                ->route('accounting.subcontractor-ra.show', $subcontractorRa)
                ->with('success', 'RA Bill posting reversed. Reversal voucher: ' . $reversalVoucher->voucher_no);
        } catch (\Exception $e) {
            return back()->with('error', 'Reversal failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete RA Bill
     */
    public function destroy(SubcontractorRaBill $subcontractorRa)
    {
        if ($subcontractorRa->isPosted()) {
            return back()->with('error', 'Posted RA Bills cannot be deleted. Please reverse first.');
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

    /**
     * Recalculate bill totals from lines
     */
    protected function recalculateBillTotals(SubcontractorRaBill $raBill): void
    {
        $raBill->refresh();
        
        // Sum line amounts
        $currentAmount = $raBill->lines()->sum('current_amount');
        $previousAmount = $raBill->lines()->sum('previous_amount');

        $raBill->previous_amount = $previousAmount;
        $raBill->current_amount = $currentAmount;
        $raBill->gross_amount = $previousAmount + $currentAmount;

        // Calculate retention
        if ($raBill->retention_percent > 0) {
            $raBill->retention_amount = round($currentAmount * ($raBill->retention_percent / 100), 2);
        }

        // Net = Current - Deductions
        $totalDeductions = $raBill->retention_amount + $raBill->advance_recovery + $raBill->other_deductions;
        $raBill->net_amount = $currentAmount - $totalDeductions;

        // GST on net amount
        $raBill->cgst_amount = round($raBill->net_amount * ($raBill->cgst_rate / 100), 2);
        $raBill->sgst_amount = round($raBill->net_amount * ($raBill->sgst_rate / 100), 2);
        $raBill->igst_amount = round($raBill->net_amount * ($raBill->igst_rate / 100), 2);
        $raBill->total_gst = $raBill->cgst_amount + $raBill->sgst_amount + $raBill->igst_amount;

        // TDS on net amount
        // IMPORTANT: As per requirement, TDS should be rounded UP (ceiling) to 2 decimals (paise).
        // We do it safely in integer paise to avoid floating-point edge cases.
        $raBill->tds_amount = 0;

        $tdsRate = (float) ($raBill->tds_rate ?? 0);
        if ($tdsRate > 0 && $raBill->net_amount > 0) {
            $netPaise = MoneyHelper::toPaise($raBill->net_amount);

            // Rate in "hundredths of percent" (e.g., 1.00% => 100; 1.50% => 150)
            $rateHp = (int) round($tdsRate * 100, 0, PHP_ROUND_HALF_UP);

            // tdsPaise = ceil(netPaise * rateHp / 10000)
            $numerator = $netPaise * $rateHp;
            $tdsPaise  = intdiv($numerator + 10000 - 1, 10000);

            $raBill->tds_amount = (float) MoneyHelper::fromPaise($tdsPaise);
        }

        // Final payable = Net + GST - TDS
        $raBill->total_amount = $raBill->net_amount + $raBill->total_gst - $raBill->tds_amount;

        $raBill->save();
    }
}