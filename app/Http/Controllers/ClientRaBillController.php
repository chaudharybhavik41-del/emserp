<?php

namespace App\Http\Controllers;

use App\Models\Accounting\TdsSection;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\ClientRaBill;
use App\Models\ClientRaBillLine;
use App\Models\Party;
use App\Models\Project;
use App\Models\ProjectClientBillingRate;
use App\Models\ProductionV2\ProductionDispatch;
use App\Models\ProductionV2\ProductionDispatchLine;
use App\Models\Uom;
use App\Services\Accounting\SalesPostingService;
use App\Services\ProjectClientBillingRateResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
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
        $this->middleware('permission:client_ra.view')->only(['dispatchBalance']);
        $this->middleware('permission:client_ra.create')->only(['create', 'store']);
        $this->middleware('permission:client_ra.update')->only(['edit', 'update']);
        $this->middleware('permission:client_ra.delete')->only('destroy');
        $this->middleware('permission:client_ra.approve')->only(['approve', 'reject']);
        $this->middleware('permission:client_ra.post')->only('post');
    }

    /**
     * Display listing of client billing documents
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

        if ($request->filled('bill_kind')) {
            $query->where('bill_kind', $request->bill_kind);
        }

        if ($request->filled('source_basis')) {
            $query->where('source_basis', $request->source_basis);
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

        return view('client_ra.index', [
            'raBills' => $raBills,
            'clients' => $clients,
            'projects' => $projects,
            'billKindOptions' => ClientRaBill::billKindOptions(),
            'sourceBasisOptions' => ClientRaBill::sourceBasisOptions(),
        ]);
    }

    public function dispatchBalance(Request $request)
    {
        $billedSubquery = ClientRaBillLine::query()
            ->join('client_ra_bills as bills', 'bills.id', '=', 'client_ra_bill_lines.client_ra_bill_id')
            ->whereIn('bills.status', ['draft', 'submitted', 'approved', 'posted'])
            ->whereNotNull('client_ra_bill_lines.production_v2_dispatch_line_id')
            ->groupBy('client_ra_bill_lines.production_v2_dispatch_line_id')
            ->selectRaw('client_ra_bill_lines.production_v2_dispatch_line_id as dispatch_line_id, SUM(client_ra_bill_lines.current_qty) as billed_qty');

        $query = DB::table('production_v2_dispatch_lines as line')
            ->join('production_v2_dispatches as dispatch', 'dispatch.id', '=', 'line.dispatch_id')
            ->join('projects as project', 'project.id', '=', 'dispatch.project_id')
            ->leftJoin('parties as client', 'client.id', '=', 'dispatch.client_party_id')
            ->leftJoinSub($billedSubquery, 'billed', function ($join) {
                $join->on('billed.dispatch_line_id', '=', 'line.id');
            })
            ->where('dispatch.status', 'finalized')
            ->when($request->filled('client_id'), fn ($builder) => $builder->where('dispatch.client_party_id', (int) $request->client_id))
            ->when($request->filled('project_id'), fn ($builder) => $builder->where('dispatch.project_id', (int) $request->project_id))
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('dispatch.dispatch_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('dispatch.dispatch_date', '<=', $request->date_to))
            ->when($request->get('billing_status') === 'unbilled', fn ($builder) => $builder->whereRaw('COALESCE(billed.billed_qty, 0) <= 0.0001'))
            ->when($request->get('billing_status') === 'partial', fn ($builder) => $builder->whereRaw('COALESCE(billed.billed_qty, 0) > 0.0001 AND COALESCE(billed.billed_qty, 0) < line.qty - 0.0001'))
            ->when($request->get('billing_status') === 'fully_billed', fn ($builder) => $builder->whereRaw('COALESCE(billed.billed_qty, 0) >= line.qty - 0.0001'))
            ->selectRaw('
                line.id as dispatch_line_id,
                dispatch.id as dispatch_id,
                dispatch.dispatch_number,
                dispatch.dispatch_date,
                dispatch.client_party_id,
                dispatch.project_id,
                client.name as client_name,
                project.name as project_name,
                line.assembly_code_snapshot,
                line.assembly_name_snapshot,
                line.client_dispatch_description_snapshot,
                line.qty as dispatch_qty,
                line.weight_kg,
                COALESCE(billed.billed_qty, 0) as billed_qty
            ')
            ->orderByDesc('dispatch.dispatch_date')
            ->orderByDesc('dispatch.id')
            ->orderBy('line.id');

        $rows = $query->paginate(25)->withQueryString();

        $rows->getCollection()->transform(function ($row) {
            $dispatchQty = (float) $row->dispatch_qty;
            $billedQty = (float) $row->billed_qty;
            $remainingQty = max(0.0, $dispatchQty - $billedQty);

            $row->remaining_qty = $remainingQty;
            $row->billing_status = $remainingQty <= 0.0001
                ? 'fully_billed'
                : ($billedQty > 0.0001 ? 'partial' : 'unbilled');

            return $row;
        });

        $clients = Party::where('is_client', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();

        return view('client_ra.dispatch_balance', [
            'rows' => $rows,
            'clients' => $clients,
            'projects' => $projects,
        ]);
    }

    /**
     * Show create form
     */
    public function create(Request $request)
    {
        $clients = Party::where('is_client', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();
        $uoms = Uom::where('is_active', true)->orderBy('name')->get();
        $revenueAccountData = $this->clientBillingRevenueAccountData();
        $revenueAccounts = $revenueAccountData['accounts'];

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
        $dispatchImport = null;

        if ($request->filled('production_v2_dispatch_id')) {
            $dispatchImport = $this->buildProductionDispatchImport((int) $request->production_v2_dispatch_id);

            if ($dispatchImport) {
                $selectedClient = $dispatchImport['dispatch']->client;
                $selectedProject = $dispatchImport['dispatch']->project;
                $prefillLines = $dispatchImport['lines'];
            }
        }

        if (! $dispatchImport && $request->filled('client_id') && $request->filled('project_id')) {
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

        return view('client_ra.create', [
            'clients' => $clients,
            'projects' => $projects,
            'uoms' => $uoms,
            'revenueAccounts' => $revenueAccounts,
            'selectedClient' => $selectedClient,
            'selectedProject' => $selectedProject,
            'previousRa' => $previousRa,
            'prefillLines' => $prefillLines,
            'dispatchImport' => $dispatchImport,
            'nextRaNumber' => $nextRaNumber,
            'tdsSections' => $tdsSections,
            'billKindOptions' => ClientRaBill::billKindOptions(),
            'sourceBasisOptions' => ClientRaBill::sourceBasisOptions(),
            'materialScopeOptions' => ClientRaBill::materialScopeOptions(),
            'revenueAccountMeta' => $revenueAccountData['meta'],
        ]);
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
            'bill_kind'        => 'nullable|in:' . implode(',', array_keys(ClientRaBill::billKindOptions())),
            'source_basis'     => 'nullable|in:' . implode(',', array_keys(ClientRaBill::sourceBasisOptions())),
            'material_scope'   => 'nullable|in:' . implode(',', array_keys(ClientRaBill::materialScopeOptions())),
            
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
            'invoice_total' => 'nullable|numeric|min:0',
            
            'remarks' => 'nullable|string',
            
            // Lines
            'lines'                    => 'required|array|min:1',
            'lines.*.description'      => 'required|string|max:500',
            'lines.*.uom_id'           => 'nullable|exists:uoms,id',
            'lines.*.revenue_account_id' => 'nullable|exists:accounts,id',
            'lines.*.production_v2_dispatch_id' => 'nullable|exists:production_v2_dispatches,id',
            'lines.*.production_v2_dispatch_line_id' => 'nullable|exists:production_v2_dispatch_lines,id',
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
        $this->normalizeBillingClassification($validated);
        $this->normalizeClientBillingLineDefaults($validated);
        $dispatchSourceRows = $this->validateProductionDispatchSources($validated, null);

        DB::transaction(function () use ($validated, $companyId, $dispatchSourceRows) {
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
            $raBill->bill_kind = $validated['bill_kind'];
            $raBill->source_basis = $validated['source_basis'];
            $raBill->material_scope = $validated['material_scope'];
            
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
            foreach ($validated['lines'] as $index => $lineData) {
                $dispatchSource = $dispatchSourceRows[$index] ?? null;
                $line = new ClientRaBillLine();
                $line->client_ra_bill_id = $raBill->id;
                $line->line_no = $lineNo++;
                $line->boq_item_code = $lineData['boq_item_code'] ?? null;
                $line->revenue_account_id = $lineData['revenue_account_id'] ?? null;
                $line->production_v2_dispatch_id = $dispatchSource['dispatch']->id ?? ($lineData['production_v2_dispatch_id'] ?? null);
                $line->production_v2_dispatch_line_id = $dispatchSource['dispatch_line']->id ?? ($lineData['production_v2_dispatch_line_id'] ?? null);
                $line->description = $lineData['description'];
                $line->uom_id = $lineData['uom_id'] ?? null;
                $line->contracted_qty = $dispatchSource['dispatch_line']->qty ?? ($lineData['contracted_qty'] ?? 0);
                $line->previous_qty = $dispatchSource['already_billed_qty'] ?? ($lineData['previous_qty'] ?? 0);
                $line->current_qty = $lineData['current_qty'];
                $line->rate = $lineData['rate'];
                $line->sac_hsn_code = $lineData['sac_hsn_code'] ?? null;
                $line->remarks = $lineData['remarks'] ?? null;
                $line->calculateAmounts();
                $line->save();
            }

            // Recalculate bill totals
            $this->recalculateBillTotals(
                $raBill,
                array_key_exists('invoice_total', $validated) && $validated['invoice_total'] !== null
                    ? (float) $validated['invoice_total']
                    : null
            );
        });

        return redirect()
            ->route('accounting.client-ra.index')
            ->with('success', 'Client billing draft created successfully.');
    }

    /**
     * Show RA Bill details
     */
    public function show(ClientRaBill $clientRa)
    {
        $clientRa->load([
            'client',
            'project',
            'lines.uom',
            'lines.revenueAccount',
            'lines.productionV2Dispatch',
            'lines.productionV2DispatchLine',
            'voucher',
            'creator',
            'approvedBy',
        ]);
        
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

        $clientRa->load(['lines.productionV2Dispatch', 'lines.productionV2DispatchLine']);
        $clients = Party::where('is_client', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();
        $uoms = Uom::where('is_active', true)->orderBy('name')->get();
        $revenueAccountData = $this->clientBillingRevenueAccountData();
        $revenueAccounts = $revenueAccountData['accounts'];

        $companyId = (int) ($clientRa->company_id ?: config('accounting.default_company_id', 1));
        $tdsSections = TdsSection::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('client_ra.edit', [
            'clientRa' => $clientRa,
            'clients' => $clients,
            'projects' => $projects,
            'uoms' => $uoms,
            'revenueAccounts' => $revenueAccounts,
            'tdsSections' => $tdsSections,
            'billKindOptions' => ClientRaBill::billKindOptions(),
            'sourceBasisOptions' => ClientRaBill::sourceBasisOptions(),
            'materialScopeOptions' => ClientRaBill::materialScopeOptions(),
            'revenueAccountMeta' => $revenueAccountData['meta'],
        ]);
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
            'bill_kind'        => 'nullable|in:' . implode(',', array_keys(ClientRaBill::billKindOptions())),
            'source_basis'     => 'nullable|in:' . implode(',', array_keys(ClientRaBill::sourceBasisOptions())),
            'material_scope'   => 'nullable|in:' . implode(',', array_keys(ClientRaBill::materialScopeOptions())),
            
            'retention_percent' => 'nullable|numeric|min:0|max:100',
            'retention_amount'  => 'nullable|numeric|min:0',
            'other_deductions'  => 'nullable|numeric|min:0',
            'deduction_remarks' => 'nullable|string',
            
            'cgst_rate' => 'nullable|numeric|min:0|max:100',
            'sgst_rate' => 'nullable|numeric|min:0|max:100',
            'igst_rate' => 'nullable|numeric|min:0|max:100',
            
            'tds_section' => 'nullable|string|max:20',
            'tds_rate'    => 'nullable|numeric|min:0|max:100',
            'invoice_total' => 'nullable|numeric|min:0',
            
            'remarks' => 'nullable|string',
            
            'lines'                    => 'required|array|min:1',
            'lines.*.id'               => 'nullable|exists:client_ra_bill_lines,id',
            'lines.*.description'      => 'required|string|max:500',
            'lines.*.uom_id'           => 'nullable|exists:uoms,id',
            'lines.*.revenue_account_id' => 'nullable|exists:accounts,id',
            'lines.*.production_v2_dispatch_id' => 'nullable|exists:production_v2_dispatches,id',
            'lines.*.production_v2_dispatch_line_id' => 'nullable|exists:production_v2_dispatch_lines,id',
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
        $this->normalizeBillingClassification($validated);
        $this->normalizeClientBillingLineDefaults($validated);
        $dispatchSourceRows = $this->validateProductionDispatchSources($validated, $clientRa);

        DB::transaction(function () use ($validated, $clientRa, $dispatchSourceRows) {
            // Update header
            $clientRa->fill([
                'bill_date'         => $validated['bill_date'],
                'due_date'          => $validated['due_date'] ?? null,
                'period_from'       => $validated['period_from'] ?? null,
                'period_to'         => $validated['period_to'] ?? null,
                'contract_number'   => $validated['contract_number'] ?? null,
                'po_number'         => $validated['po_number'] ?? null,
                'revenue_type'      => $validated['revenue_type'],
                'bill_kind'         => $validated['bill_kind'],
                'source_basis'      => $validated['source_basis'],
                'material_scope'    => $validated['material_scope'],
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
            foreach ($validated['lines'] as $index => $lineData) {
                $dispatchSource = $dispatchSourceRows[$index] ?? null;
                $lineAttributes = [
                    'line_no'            => $lineNo++,
                    'boq_item_code'      => $lineData['boq_item_code'] ?? null,
                    'revenue_account_id' => $lineData['revenue_account_id'] ?? null,
                    'production_v2_dispatch_id' => $dispatchSource['dispatch']->id ?? ($lineData['production_v2_dispatch_id'] ?? null),
                    'production_v2_dispatch_line_id' => $dispatchSource['dispatch_line']->id ?? ($lineData['production_v2_dispatch_line_id'] ?? null),
                    'description'        => $lineData['description'],
                    'uom_id'             => $lineData['uom_id'] ?? null,
                    'contracted_qty'     => $dispatchSource['dispatch_line']->qty ?? ($lineData['contracted_qty'] ?? 0),
                    'previous_qty'       => $dispatchSource['already_billed_qty'] ?? ($lineData['previous_qty'] ?? 0),
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
            $this->recalculateBillTotals(
                $clientRa,
                array_key_exists('invoice_total', $validated) && $validated['invoice_total'] !== null
                    ? (float) $validated['invoice_total']
                    : null
            );
        });

        return redirect()
            ->route('accounting.client-ra.show', $clientRa)
            ->with('success', 'Client billing draft updated successfully.');
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
        $data = $request->validate([
            'reversal_date' => 'required|date',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $reversalVoucher = $postingService->reverse($clientRa, $data['reversal_date'], $data['reason']);

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

    protected function normalizeBillingClassification(array &$validated): void
    {
        $lines = collect($validated['lines'] ?? []);
        $hasDispatchSource = $lines->contains(function ($line) {
            return !empty($line['production_v2_dispatch_line_id'] ?? null);
        });
        $project = !empty($validated['project_id']) ? Project::find((int) $validated['project_id']) : null;

        $billKind = $validated['bill_kind'] ?? ($project?->client_billing_default_bill_kind ?: null);
        if (!$billKind) {
            $billKind = ClientRaBill::defaultBillKindFor($validated['revenue_type'] ?? null, $hasDispatchSource);
        }

        $sourceBasis = $validated['source_basis'] ?? ($project?->client_billing_source_basis ?: null);
        if (!$sourceBasis) {
            $sourceBasis = ClientRaBill::defaultSourceBasisFor($billKind, $hasDispatchSource);
        }

        $materialScope = $validated['material_scope'] ?? ($project?->client_billing_material_scope ?: null);
        if (!$materialScope) {
            $materialScope = ClientRaBill::defaultMaterialScopeFor($billKind);
        }

        if (empty($validated['tds_section']) && !empty($project?->client_billing_tds_section)) {
            $validated['tds_section'] = $project->client_billing_tds_section;
        }

        if (((float) ($validated['tds_rate'] ?? 0)) <= 0 && !empty($project?->client_billing_tds_rate)) {
            $validated['tds_rate'] = (float) $project->client_billing_tds_rate;
        }

        $validated['bill_kind'] = $billKind;
        $validated['source_basis'] = $sourceBasis;
        $validated['material_scope'] = $materialScope;
    }

    protected function normalizeClientBillingLineDefaults(array &$validated): void
    {
        if (empty($validated['lines']) || empty($validated['project_id'])) {
            return;
        }

        $project = Project::find((int) $validated['project_id']);
        $billKind = (string) ($validated['bill_kind'] ?? ClientRaBill::BILL_KIND_OTHER);
        $revenueType = (string) ($validated['revenue_type'] ?? 'other');
        $billDate = $validated['bill_date'] ?? null;
        $rateResolver = app(ProjectClientBillingRateResolver::class);

        foreach ($validated['lines'] as $index => $line) {
            if ($project) {
                $projectRate = $rateResolver->resolveForClientBillLine($project, $line, $billKind, $billDate);

                if ($projectRate) {
                    if (empty($line['uom_id']) && $projectRate->uom_id) {
                        $validated['lines'][$index]['uom_id'] = $projectRate->uom_id;
                    }

                    if ((float) ($line['rate'] ?? 0) <= 0 && $projectRate->rate > 0) {
                        $validated['lines'][$index]['rate'] = $projectRate->rate;
                    }

                    if (empty($line['sac_hsn_code']) && !empty($projectRate->sac_hsn_code)) {
                        $validated['lines'][$index]['sac_hsn_code'] = $projectRate->sac_hsn_code;
                    }

                    if (empty($line['revenue_account_id']) && $projectRate->revenue_account_id) {
                        $validated['lines'][$index]['revenue_account_id'] = $projectRate->revenue_account_id;
                    }
                }
            }

            if (empty($validated['lines'][$index]['revenue_account_id'])) {
                $defaultRevenueAccount = $this->defaultRevenueAccountForBillKind($billKind, $revenueType);
                if ($defaultRevenueAccount) {
                    $validated['lines'][$index]['revenue_account_id'] = $defaultRevenueAccount->id;
                }
            }
        }
    }

    protected function clientBillingRevenueAccountData(): array
    {
        $accounts = Account::query()
            ->whereHas('group', function ($q) {
                $q->where('nature', 'income');
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $codeBuckets = [
            'fabrication' => (string) Config::get('accounting.sales.fabrication_revenue_code', 'REV-FABRICATION'),
            'erection' => (string) Config::get('accounting.sales.erection_revenue_code', 'REV-ERECTION'),
            'supply' => (string) Config::get('accounting.sales.supply_revenue_code', 'REV-SUPPLY'),
            'service' => (string) Config::get('accounting.sales.service_revenue_code', 'REV-SERVICE'),
            'other' => (string) Config::get('accounting.sales.other_revenue_code', 'REV-OTHER'),
            'scrap' => (string) Config::get('accounting.sales.scrap_revenue_code', 'REV-SCRAP'),
            'default' => (string) Config::get('accounting.sales.default_revenue_code', 'REV-FABRICATION'),
        ];

        $roleByAccountId = [];
        $idsByRole = [];

        foreach ($accounts as $account) {
            $role = array_search($account->code, $codeBuckets, true);
            $role = $role === false ? 'generic' : $role;
            $roleByAccountId[$account->id] = $role;
            $idsByRole[$role][] = $account->id;
        }

        return [
            'accounts' => $accounts,
            'meta' => [
                'role_by_account_id' => $roleByAccountId,
                'ids_by_role' => $idsByRole,
                'default_account_ids' => [
                    ClientRaBill::BILL_KIND_MATERIAL_SALES => $this->defaultRevenueAccountForBillKind(ClientRaBill::BILL_KIND_MATERIAL_SALES, 'supply')?->id,
                    ClientRaBill::BILL_KIND_SCRAP_SALES => $this->defaultRevenueAccountForBillKind(ClientRaBill::BILL_KIND_SCRAP_SALES, 'other')?->id,
                    ClientRaBill::BILL_KIND_PROJECT_LABOUR_SERVICE => $this->defaultRevenueAccountForBillKind(ClientRaBill::BILL_KIND_PROJECT_LABOUR_SERVICE, 'service')?->id,
                    ClientRaBill::BILL_KIND_PROJECT_MFG_SERVICE => $this->defaultRevenueAccountForBillKind(ClientRaBill::BILL_KIND_PROJECT_MFG_SERVICE, 'fabrication')?->id,
                    ClientRaBill::BILL_KIND_OTHER => $this->defaultRevenueAccountForBillKind(ClientRaBill::BILL_KIND_OTHER, 'other')?->id,
                ],
            ],
        ];
    }

    protected function defaultRevenueAccountForBillKind(string $billKind, string $revenueType): ?Account
    {
        $preferredCode = match ($billKind) {
            ClientRaBill::BILL_KIND_MATERIAL_SALES => (string) Config::get('accounting.sales.supply_revenue_code', 'REV-SUPPLY'),
            ClientRaBill::BILL_KIND_SCRAP_SALES => (string) Config::get('accounting.sales.scrap_revenue_code', 'REV-SCRAP'),
            ClientRaBill::BILL_KIND_PROJECT_LABOUR_SERVICE => (string) Config::get('accounting.sales.service_revenue_code', 'REV-SERVICE'),
            ClientRaBill::BILL_KIND_PROJECT_MFG_SERVICE => match ($revenueType) {
                'erection' => (string) Config::get('accounting.sales.erection_revenue_code', 'REV-ERECTION'),
                'service' => (string) Config::get('accounting.sales.service_revenue_code', 'REV-SERVICE'),
                default => (string) Config::get('accounting.sales.fabrication_revenue_code', 'REV-FABRICATION'),
            },
            default => match ($revenueType) {
                'supply' => (string) Config::get('accounting.sales.supply_revenue_code', 'REV-SUPPLY'),
                'service' => (string) Config::get('accounting.sales.service_revenue_code', 'REV-SERVICE'),
                'erection' => (string) Config::get('accounting.sales.erection_revenue_code', 'REV-ERECTION'),
                default => (string) Config::get('accounting.sales.other_revenue_code', Config::get('accounting.sales.default_revenue_code', 'REV-FABRICATION')),
            },
        };

        return Account::query()
            ->where('code', $preferredCode)
            ->where('is_active', true)
            ->first();
    }

    protected function buildProductionDispatchImport(int $dispatchId): ?array
    {
        $rateResolver = app(ProjectClientBillingRateResolver::class);
        $dispatch = ProductionDispatch::query()
            ->with(['project', 'client', 'lines'])
            ->where('status', 'finalized')
            ->find($dispatchId);

        if (! $dispatch) {
            return null;
        }

        $billedQtyMap = $this->dispatchLineBilledQtyMap($dispatch->lines->pluck('id')->all());

        $lines = $dispatch->lines
            ->map(function (ProductionDispatchLine $line) use ($dispatch, $billedQtyMap, $rateResolver) {
                $alreadyBilledQty = (float) ($billedQtyMap[$line->id] ?? 0);
                $remainingQty = max(0.0, (float) $line->qty - $alreadyBilledQty);
                $projectRate = $rateResolver->resolveForDispatchLine($dispatch->project_id, $line, $dispatch->dispatch_date);

                if ($remainingQty <= 0.0001) {
                    return null;
                }

                return [
                    'id' => null,
                    'boq_item_code' => $line->assembly_code_snapshot ?? '',
                    'revenue_account_id' => null,
                    'production_v2_dispatch_id' => $dispatch->id,
                    'production_v2_dispatch_line_id' => $line->id,
                    'description' => $this->dispatchBillingDescription($dispatch, $line),
                    'uom_id' => $projectRate?->uom_id,
                    'contracted_qty' => (float) $line->qty,
                    'previous_qty' => $alreadyBilledQty,
                    'current_qty' => $remainingQty,
                    'rate' => (float) ($projectRate?->rate ?? 0),
                    'revenue_account_id' => $projectRate?->revenue_account_id,
                    'sac_hsn_code' => $projectRate?->sac_hsn_code ?? '',
                    'remarks' => $line->remarks ?? '',
                    'source_summary' => $dispatch->dispatch_number,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'dispatch' => $dispatch,
            'lines' => $lines,
            'remaining_count' => count($lines),
        ];
    }

    /**
     * @return array<int, array{dispatch:\App\Models\ProductionV2\ProductionDispatch,dispatch_line:\App\Models\ProductionV2\ProductionDispatchLine,already_billed_qty:float}>
     */
    protected function validateProductionDispatchSources(array $validated, ?ClientRaBill $currentBill): array
    {
        $rows = [];
        $lineIds = collect($validated['lines'] ?? [])
            ->pluck('production_v2_dispatch_line_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($lineIds->isEmpty()) {
            return $rows;
        }

        $dispatchLines = ProductionDispatchLine::query()
            ->with('dispatch')
            ->whereIn('id', $lineIds)
            ->get()
            ->keyBy('id');

        $billedQtyMap = $this->dispatchLineBilledQtyMap($lineIds->all(), $currentBill?->id);
        $projectId = (int) ($validated['project_id'] ?? $currentBill?->project_id ?? 0);
        $clientId = (int) ($validated['client_id'] ?? $currentBill?->client_id ?? 0);

        foreach ($validated['lines'] as $index => $line) {
            $dispatchId = (int) ($line['production_v2_dispatch_id'] ?? 0);
            $dispatchLineId = (int) ($line['production_v2_dispatch_line_id'] ?? 0);

            if ($dispatchId === 0 && $dispatchLineId === 0) {
                continue;
            }

            if ($dispatchId === 0 || $dispatchLineId === 0) {
                throw ValidationException::withMessages([
                    "lines.$index.description" => 'Dispatch-linked billing line is missing source dispatch reference.',
                ]);
            }

            /** @var \App\Models\ProductionV2\ProductionDispatchLine|null $dispatchLine */
            $dispatchLine = $dispatchLines->get($dispatchLineId);
            $dispatch = $dispatchLine?->dispatch;

            if (! $dispatchLine || ! $dispatch || (int) $dispatch->id !== $dispatchId) {
                throw ValidationException::withMessages([
                    "lines.$index.description" => 'Selected dispatch source could not be verified.',
                ]);
            }

            if ($dispatch->status !== 'finalized') {
                throw ValidationException::withMessages([
                    "lines.$index.description" => 'Only finalized production dispatch can be billed to client.',
                ]);
            }

            if ((int) $dispatch->project_id !== $projectId || (int) ($dispatch->client_party_id ?? 0) !== $clientId) {
                throw ValidationException::withMessages([
                    "lines.$index.description" => 'Dispatch source does not belong to the selected client/project.',
                ]);
            }

            $alreadyBilledQty = (float) ($billedQtyMap[$dispatchLineId] ?? 0);
            $currentQty = (float) ($line['current_qty'] ?? 0);

            if ($currentQty > (((float) $dispatchLine->qty - $alreadyBilledQty) + 0.0001)) {
                throw ValidationException::withMessages([
                    "lines.$index.current_qty" => 'Current qty exceeds remaining unbilled dispatch qty.',
                ]);
            }

            $rows[$index] = [
                'dispatch' => $dispatch,
                'dispatch_line' => $dispatchLine,
                'already_billed_qty' => $alreadyBilledQty,
            ];
        }

        return $rows;
    }

    protected function dispatchLineBilledQtyMap(array $dispatchLineIds, ?int $ignoreBillId = null)
    {
        if (empty($dispatchLineIds)) {
            return collect();
        }

        return ClientRaBillLine::query()
            ->join('client_ra_bills as bills', 'bills.id', '=', 'client_ra_bill_lines.client_ra_bill_id')
            ->whereIn('bills.status', ['draft', 'submitted', 'approved', 'posted'])
            ->when($ignoreBillId, fn ($query) => $query->where('bills.id', '!=', $ignoreBillId))
            ->whereIn('client_ra_bill_lines.production_v2_dispatch_line_id', $dispatchLineIds)
            ->groupBy('client_ra_bill_lines.production_v2_dispatch_line_id')
            ->selectRaw('client_ra_bill_lines.production_v2_dispatch_line_id as dispatch_line_id, SUM(client_ra_bill_lines.current_qty) as qty_sum')
            ->pluck('qty_sum', 'dispatch_line_id');
    }

    protected function dispatchBillingDescription(ProductionDispatch $dispatch, ProductionDispatchLine $line): string
    {
        $parts = trim((string) ($line->client_dispatch_description_snapshot ?? ''));
        $assembly = trim((string) ($line->assembly_code_snapshot ?: 'Assembly'));
        $assemblyName = trim((string) ($line->assembly_name_snapshot ?? ''));

        $label = trim($assembly . ($assemblyName !== '' ? ' - ' . $assemblyName : ''));
        $description = 'Dispatch ' . $dispatch->dispatch_number . ' / ' . $label;

        if ($parts !== '') {
            $description .= ' / Parts: ' . $parts;
        }

        return $description;
    }

    /**
     * Recalculate bill totals from lines
     */
    protected function recalculateBillTotals(ClientRaBill $raBill, ?float $invoiceTotalInput = null): void
    {
        $raBill->refresh();

        // Sum line amounts
        $currentAmount = $raBill->lines()->sum('current_amount');
        $previousAmount = $raBill->lines()->sum('previous_amount');

        $raBill->previous_amount = $previousAmount;
        $raBill->current_amount = $currentAmount;
        $raBill->gross_amount = $previousAmount + $currentAmount;

        // Calculate retention
        $raBill->retention_amount = $raBill->retention_percent > 0
            ? round($currentAmount * ($raBill->retention_percent / 100), 2)
            : 0.0;

        // Net = Current - Deductions
        $totalDeductions = $raBill->retention_amount + $raBill->other_deductions;
        $raBill->net_amount = $currentAmount - $totalDeductions;

        // GST on net amount (Output GST)
        $raBill->cgst_amount = round($raBill->net_amount * ($raBill->cgst_rate / 100), 2);
        $raBill->sgst_amount = round($raBill->net_amount * ($raBill->sgst_rate / 100), 2);
        $raBill->igst_amount = round($raBill->net_amount * ($raBill->igst_rate / 100), 2);
        $raBill->total_gst = $raBill->cgst_amount + $raBill->sgst_amount + $raBill->igst_amount;

        // TDS on net amount (will be deducted by client) should stay whole-rupee.
        $raBill->tds_amount = $this->calculateRoundedTdsAmount(
            (float) $raBill->net_amount,
            (float) $raBill->tds_rate
        );

        $calculatedTotal = round($raBill->net_amount + $raBill->total_gst, 2);
        $invoiceTotal = $invoiceTotalInput !== null
            ? round($invoiceTotalInput, 2)
            : round($calculatedTotal, 0);
        $raBill->round_off = round($invoiceTotal - $calculatedTotal, 2);

        if (abs((float) $raBill->round_off) > 5) {
            throw ValidationException::withMessages([
                'invoice_total' => 'Invoice Total differs too much from calculated total. Please check billing lines, GST and deductions.',
            ]);
        }

        // Total invoice = Net + GST + Round Off
        $raBill->total_amount = $invoiceTotal;

        // Receivable = Total - TDS
        $raBill->receivable_amount = $raBill->total_amount - $raBill->tds_amount;

        $raBill->save();
    }

    protected function calculateRoundedTdsAmount(float $netAmount, float $tdsRate): float
    {
        if ($tdsRate <= 0 || $netAmount <= 0) {
            return 0.0;
        }

        return (float) round(max(0, ($netAmount * $tdsRate) / 100), 0);
    }
}
