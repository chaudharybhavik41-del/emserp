<?php

namespace App\Http\Controllers;

use App\Models\Accounting\TdsSection;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\ClientRaBill;
use App\Models\ClientRaBillLine;
use App\Models\Party;
use App\Models\Project;
use App\Models\Uom;
use App\Services\Accounting\SalesPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DEV-4: Client RA Bill / Sales Invoice Controller
 * 
 * Handles CRUD operations and workflow for Client RA Bills
 * Integrates with SalesPostingService for accounting
 */
class ClientRaBillController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:client_ra.view')->only(['index', 'show']);
        $this->middleware('permission:client_ra.create')->only(['create', 'store']);
        $this->middleware('permission:client_ra.update')->only(['edit', 'update']);
        $this->middleware('permission:client_ra.delete')->only('destroy');
        $this->middleware('permission:client_ra.approve')->only(['approve', 'reject']);
        $this->middleware('permission:client_ra.post')->only('post');
    }

    /**
     * Display listing of Client RA Bills
     */
    public function index(Request $request)
    {
        $query = ClientRaBill::with(['client', 'project', 'creator'])
            ->orderByDesc('bill_date')
            ->orderByDesc('id');

        // Filters
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('revenue_type')) {
            $query->where('revenue_type', $request->revenue_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('bill_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('bill_date', '<=', $request->date_to);
        }

        $raBills = $query->paginate(20);

        $clients = Party::where('is_client', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();

        return view('client_ra.index', compact('raBills', 'clients', 'projects'));
    }

    /**
     * Show create form
     */
    public function create(Request $request)
    {
        $clients = Party::where('is_client', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();
        $uoms = Uom::where('is_active', true)->orderBy('name')->get();
        
        // Revenue accounts for line-level assignment
        $revenueAccounts = Account::whereHas('group', function ($q) {
            $q->where('nature', 'income');
        })->where('is_active', true)->orderBy('name')->get();

        $companyId = (int) config('accounting.default_company_id', 1);
        $tdsSections = TdsSection::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        // Pre-fill if client/project selected
        $selectedClient = null;
        $selectedProject = null;
        $previousRa = null;
        $prefillLines = null;

        if ($request->filled('client_id') && $request->filled('project_id')) {
            $selectedClient = Party::find($request->client_id);
            $selectedProject = Project::find($request->project_id);

            // Get previous RA for this combination
            $previousRa = ClientRaBill::where('client_id', $request->client_id)
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
                        'revenue_account_id' => $l->revenue_account_id,
                        'description' => $l->description ?? '',
                        'uom_id' => $l->uom_id,
                        'contracted_qty' => $l->contracted_qty ?? 0,
                        'previous_qty' => $prevQty,
                        'current_qty' => 0,
                        'rate' => $l->rate ?? 0,
                        'sac_hsn_code' => $l->sac_hsn_code ?? '',
                        'remarks' => $l->remarks ?? '',
                    ];
                })->toArray();

                if (empty($prefillLines)) {
                    $prefillLines = null;
                }
            }
        }

        $nextRaNumber = ClientRaBill::generateNextRaNumber();

        return view('client_ra.create', compact(
            'clients',
            'projects',
            'uoms',
            'revenueAccounts',
            'selectedClient',
            'selectedProject',
            'previousRa',
            'prefillLines',
            'nextRaNumber',
            'tdsSections'
        ));
    }

    /**
     * Store new Client RA Bill
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id'        => 'required|exists:parties,id',
            'project_id'       => 'required|exists:projects,id',
            'bill_date'        => 'required|date',
            'due_date'         => 'nullable|date|after_or_equal:bill_date',
            'period_from'      => 'nullable|date',
            'period_to'        => 'nullable|date|after_or_equal:period_from',
            'contract_number'  => 'nullable|string|max:100',
            'po_number'        => 'nullable|string|max:100',
            'revenue_type'     => 'required|in:fabrication,erection,supply,service,other',
            
            // Deductions
            'retention_percent' => 'nullable|numeric|min:0|max:100',
            'retention_amount'  => 'nullable|numeric|min:0',
            'other_deductions'  => 'nullable|numeric|min:0',
            'deduction_remarks' => 'nullable|string',
            
            // GST
            'cgst_rate' => 'nullable|numeric|min:0|max:100',
            'sgst_rate' => 'nullable|numeric|min:0|max:100',
            'igst_rate' => 'nullable|numeric|min:0|max:100',
            
            // TDS (deducted by client)
            'tds_section' => 'nullable|string|max:20',
            'tds_rate'    => 'nullable|numeric|min:0|max:100',
            
            'remarks' => 'nullable|string',
            
            // Lines
            'lines'                    => 'required|array|min:1',
            'lines.*.description'      => 'required|string|max:500',
            'lines.*.uom_id'           => 'nullable|exists:uoms,id',
            'lines.*.revenue_account_id' => 'nullable|exists:accounts,id',
            'lines.*.contracted_qty'   => 'nullable|numeric|min:0',
            'lines.*.previous_qty'     => 'nullable|numeric|min:0',
            'lines.*.current_qty'      => 'required|numeric|min:0',
            'lines.*.rate'             => 'required|numeric|min:0',
            'lines.*.boq_item_code'    => 'nullable|string|max:50',
            'lines.*.sac_hsn_code'     => 'nullable|string|max:20',
            'lines.*.remarks'          => 'nullable|string',
        ]);

        // Verify client
        $client = Party::findOrFail($validated['client_id']);
        if (!$client->is_client) {
            throw ValidationException::withMessages([
                'client_id' => 'Selected party must be a client.',
            ]);
        }

        $companyId = config('accounting.default_company_id', 1);

        // Apply TDS master defaults (section → rate)
        $this->applyTdsFromMaster($validated, (int) $companyId);

        DB::transaction(function () use ($validated, $companyId) {
            // Create RA Bill
            $raBill = new ClientRaBill();
            $raBill->company_id = $companyId;
            $raBill->client_id = $validated['client_id'];
            $raBill->project_id = $validated['project_id'];
            $raBill->ra_number = ClientRaBill::generateNextRaNumber($companyId);
            $raBill->ra_sequence = ClientRaBill::getNextRaSequence(
                $validated['client_id'],
                $validated['project_id']
            );
            $raBill->bill_date = $validated['bill_date'];
            $raBill->due_date = $validated['due_date'] ?? null;
            $raBill->period_from = $validated['period_from'] ?? null;
            $raBill->period_to = $validated['period_to'] ?? null;
            $raBill->contract_number = $validated['contract_number'] ?? null;
            $raBill->po_number = $validated['po_number'] ?? null;
            $raBill->revenue_type = $validated['revenue_type'];
            
            // Deductions
            $raBill->retention_percent = $validated['retention_percent'] ?? 0;
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
                $line = new ClientRaBillLine();
                $line->client_ra_bill_id = $raBill->id;
                $line->line_no = $lineNo++;
                $line->boq_item_code = $lineData['boq_item_code'] ?? null;
                $line->revenue_account_id = $lineData['revenue_account_id'] ?? null;
                $line->description = $lineData['description'];
                $line->uom_id = $lineData['uom_id'] ?? null;
                $line->contracted_qty = $lineData['contracted_qty'] ?? 0;
                $line->previous_qty = $lineData['previous_qty'] ?? 0;
                $line->current_qty = $lineData['current_qty'];
                $line->rate = $lineData['rate'];
                $line->sac_hsn_code = $lineData['sac_hsn_code'] ?? null;
                $line->remarks = $lineData['remarks'] ?? null;
                $line->calculateAmounts();
                $line->save();
            }

            // Recalculate bill totals
            $this->recalculateBillTotals($raBill);
        });

        return redirect()
            ->route('accounting.client-ra.index')
            ->with('success', 'Client RA Bill created successfully.');
    }

    /**
     * Show RA Bill details
     */
    public function show(ClientRaBill $clientRa)
    {
        $clientRa->load(['client', 'project', 'lines.uom', 'lines.revenueAccount', 'voucher', 'creator', 'approvedBy']);
        
        return view('client_ra.show', compact('clientRa'));
    }

    /**
     * Show edit form
     */
    public function edit(ClientRaBill $clientRa)
    {
        if ($clientRa->isPosted()) {
            return redirect()
                ->route('accounting.client-ra.show', $clientRa)
                ->with('error', 'Posted RA Bills cannot be edited.');
        }

        $clientRa->load('lines');
        $clients = Party::where('is_client', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();
        $uoms = Uom::where('is_active', true)->orderBy('name')->get();
        $revenueAccounts = Account::whereHas('group', function ($q) {
            $q->where('nature', 'income');
        })->where('is_active', true)->orderBy('name')->get();

        $companyId = (int) ($clientRa->company_id ?: config('accounting.default_company_id', 1));
        $tdsSections = TdsSection::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('client_ra.edit', compact('clientRa', 'clients', 'projects', 'uoms', 'revenueAccounts', 'tdsSections'));
    }

    /**
     * Update RA Bill
     */
    public function update(Request $request, ClientRaBill $clientRa)
    {
        if ($clientRa->isPosted()) {
            throw ValidationException::withMessages([
                'status' => 'Posted RA Bills cannot be edited.',
            ]);
        }

        $validated = $request->validate([
            'bill_date'        => 'required|date',
            'due_date'         => 'nullable|date|after_or_equal:bill_date',
            'period_from'      => 'nullable|date',
            'period_to'        => 'nullable|date|after_or_equal:period_from',
            'contract_number'  => 'nullable|string|max:100',
            'po_number'        => 'nullable|string|max:100',
            'revenue_type'     => 'required|in:fabrication,erection,supply,service,other',
            
            'retention_percent' => 'nullable|numeric|min:0|max:100',
            'retention_amount'  => 'nullable|numeric|min:0',
            'other_deductions'  => 'nullable|numeric|min:0',
            'deduction_remarks' => 'nullable|string',
            
            'cgst_rate' => 'nullable|numeric|min:0|max:100',
            'sgst_rate' => 'nullable|numeric|min:0|max:100',
            'igst_rate' => 'nullable|numeric|min:0|max:100',
            
            'tds_section' => 'nullable|string|max:20',
            'tds_rate'    => 'nullable|numeric|min:0|max:100',
            
            'remarks' => 'nullable|string',
            
            'lines'                    => 'required|array|min:1',
            'lines.*.id'               => 'nullable|exists:client_ra_bill_lines,id',
            'lines.*.description'      => 'required|string|max:500',
            'lines.*.uom_id'           => 'nullable|exists:uoms,id',
            'lines.*.revenue_account_id' => 'nullable|exists:accounts,id',
            'lines.*.contracted_qty'   => 'nullable|numeric|min:0',
            'lines.*.previous_qty'     => 'nullable|numeric|min:0',
            'lines.*.current_qty'      => 'required|numeric|min:0',
            'lines.*.rate'             => 'required|numeric|min:0',
            'lines.*.boq_item_code'    => 'nullable|string|max:50',
            'lines.*.sac_hsn_code'     => 'nullable|string|max:20',
            'lines.*.remarks'          => 'nullable|string',
        ]);

        // Apply TDS master defaults (section → rate)
        $companyId = (int) ($clientRa->company_id ?: config('accounting.default_company_id', 1));
        $this->applyTdsFromMaster($validated, $companyId);

        DB::transaction(function () use ($validated, $clientRa) {
            // Update header
            $clientRa->fill([
                'bill_date'         => $validated['bill_date'],
                'due_date'          => $validated['due_date'] ?? null,
                'period_from'       => $validated['period_from'] ?? null,
                'period_to'         => $validated['period_to'] ?? null,
                'contract_number'   => $validated['contract_number'] ?? null,
                'po_number'         => $validated['po_number'] ?? null,
                'revenue_type'      => $validated['revenue_type'],
                'retention_percent' => $validated['retention_percent'] ?? 0,
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
            $clientRa->save();

            // Handle lines
            $existingLineIds = collect($validated['lines'])
                ->pluck('id')
                ->filter()
                ->toArray();

            // Delete removed lines
            $clientRa->lines()
                ->whereNotIn('id', $existingLineIds)
                ->delete();

            // Update/create lines
            $lineNo = 1;
            foreach ($validated['lines'] as $lineData) {
                $lineAttributes = [
                    'line_no'            => $lineNo++,
                    'boq_item_code'      => $lineData['boq_item_code'] ?? null,
                    'revenue_account_id' => $lineData['revenue_account_id'] ?? null,
                    'description'        => $lineData['description'],
                    'uom_id'             => $lineData['uom_id'] ?? null,
                    'contracted_qty'     => $lineData['contracted_qty'] ?? 0,
                    'previous_qty'       => $lineData['previous_qty'] ?? 0,
                    'current_qty'        => $lineData['current_qty'],
                    'rate'               => $lineData['rate'],
                    'sac_hsn_code'       => $lineData['sac_hsn_code'] ?? null,
                    'remarks'            => $lineData['remarks'] ?? null,
                ];

                if (!empty($lineData['id'])) {
                    $line = ClientRaBillLine::find($lineData['id']);
                    if ($line) {
                        $line->fill($lineAttributes);
                        $line->calculateAmounts();
                        $line->save();
                    }
                } else {
                    $line = new ClientRaBillLine($lineAttributes);
                    $line->client_ra_bill_id = $clientRa->id;
                    $line->calculateAmounts();
                    $line->save();
                }
            }

            // Recalculate totals
            $this->recalculateBillTotals($clientRa);
        });

        return redirect()
            ->route('accounting.client-ra.show', $clientRa)
            ->with('success', 'Client RA Bill updated successfully.');
    }

    /**
     * Submit for approval
     */
    public function submit(ClientRaBill $clientRa)
    {
        if ($clientRa->status !== 'draft') {
            return back()->with('error', 'Only draft RA Bills can be submitted.');
        }

        if ($clientRa->current_amount <= 0) {
            return back()->with('error', 'RA Bill must have a positive current amount.');
        }

        $clientRa->status = 'submitted';
        $clientRa->updated_by = Auth::id();
        $clientRa->save();

        return back()->with('success', 'RA Bill submitted for approval.');
    }

    /**
     * Approve RA Bill
     */
    public function approve(ClientRaBill $clientRa)
    {
        if (!$clientRa->canBeApproved()) {
            return back()->with('error', 'RA Bill cannot be approved in current state.');
        }

        $clientRa->status = 'approved';
        $clientRa->approved_at = now();
        $clientRa->approved_by = Auth::id();
        $clientRa->updated_by = Auth::id();
        $clientRa->save();

        return back()->with('success', 'RA Bill approved successfully.');
    }

    /**
     * Reject RA Bill
     */
    public function reject(Request $request, ClientRaBill $clientRa)
    {
        if ($clientRa->status !== 'submitted') {
            return back()->with('error', 'Only submitted RA Bills can be rejected.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $clientRa->status = 'rejected';
        $clientRa->remarks = ($clientRa->remarks ?? '') . 
            "\n[Rejected: " . $request->rejection_reason . "]";
        $clientRa->updated_by = Auth::id();
        $clientRa->save();

        return back()->with('success', 'RA Bill rejected.');
    }

    /**
     * Post RA Bill to accounts
     */
    public function post(ClientRaBill $clientRa, SalesPostingService $postingService)
    {
        if (!$clientRa->canBePosted()) {
            return back()->with('error', 'RA Bill cannot be posted. It must be approved and not already posted.');
        }

        try {
            $voucher = $postingService->post($clientRa);

            return redirect()
                ->route('accounting.client-ra.show', $clientRa)
                ->with('success', 'RA Bill posted to accounts. Voucher: ' . $voucher->voucher_no . '. Invoice: ' . $clientRa->invoice_number);
        } catch (\Exception $e) {
            return back()->with('error', 'Posting failed: ' . $e->getMessage());
        }
    }

    /**
     * Reverse posted RA Bill
     */
    public function reverse(Request $request, ClientRaBill $clientRa, SalesPostingService $postingService)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $reversalVoucher = $postingService->reverse($clientRa, $request->reason);

            return redirect()
                ->route('accounting.client-ra.show', $clientRa)
                ->with('success', 'RA Bill posting reversed. Reversal voucher: ' . $reversalVoucher->voucher_no);
        } catch (\Exception $e) {
            return back()->with('error', 'Reversal failed: ' . $e->getMessage());
        }
    }

    /**
     * Print invoice
     */
    public function print(ClientRaBill $clientRa)
    {
        $clientRa->load(['client', 'project', 'lines.uom', 'voucher']);

        return view('client_ra.print', compact('clientRa'));
    }

    /**
     * Delete RA Bill
     */
    public function destroy(ClientRaBill $clientRa)
    {
        if ($clientRa->isPosted()) {
            return back()->with('error', 'Posted RA Bills cannot be deleted. Please reverse first.');
        }

        $clientRa->lines()->delete();
        $clientRa->delete();

        return redirect()
            ->route('accounting.client-ra.index')
            ->with('success', 'RA Bill deleted successfully.');
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
    protected function recalculateBillTotals(ClientRaBill $raBill): void
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
        $totalDeductions = $raBill->retention_amount + $raBill->other_deductions;
        $raBill->net_amount = $currentAmount - $totalDeductions;

        // GST on net amount (Output GST)
        $raBill->cgst_amount = round($raBill->net_amount * ($raBill->cgst_rate / 100), 2);
        $raBill->sgst_amount = round($raBill->net_amount * ($raBill->sgst_rate / 100), 2);
        $raBill->igst_amount = round($raBill->net_amount * ($raBill->igst_rate / 100), 2);
        $raBill->total_gst = $raBill->cgst_amount + $raBill->sgst_amount + $raBill->igst_amount;

        // TDS on net amount (will be deducted by client)
        $raBill->tds_amount = round($raBill->net_amount * ($raBill->tds_rate / 100), 2);

        // Total invoice = Net + GST
        $raBill->total_amount = $raBill->net_amount + $raBill->total_gst;

        // Receivable = Total - TDS
        $raBill->receivable_amount = $raBill->total_amount - $raBill->tds_amount;

        $raBill->save();
    }
}
