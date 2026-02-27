<?php

namespace App\Http\Controllers;

use App\Models\GatePass;
use App\Models\GatePassLine;
use App\Models\Item;
use App\Models\Machine;
use App\Models\Party;
use App\Models\Project;
use App\Models\StoreIssue;
use App\Models\StoreIssueLine;
use App\Models\StoreReturn;
use App\Models\StoreReturnLine;
use App\Models\StoreStockItem;
use App\Models\Uom;
use App\Models\ActivityLog;
use App\Services\DocumentNumberService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Company;
use App\Services\SettingsService;

class GatePassController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:store.gatepass.view')
            ->only(['index', 'show', 'pdf']);
        $this->middleware('permission:store.gatepass.create')->only([
            'create',
            'store',
            'returnForm',
            'registerReturn',
            'closeWithoutFullReturn',
        ]);
    }

    public function index(Request $request): View
    {
        $query = GatePass::with(['project', 'contractor', 'toParty', 'createdBy'])
            ->orderByDesc('gatepass_date')
            ->orderByDesc('id');

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }

        $gatePasses = $query->paginate(25);
        $projects   = Project::orderBy('code')->get();

        return view('gate_passes.index', compact('gatePasses', 'projects'));
    }

    public function create(Request $request): View
    {
        $projects = Project::orderBy('code')->get();
        $parties  = Party::orderBy('name')->get();
        $items    = Item::orderBy('name')->get();
        $uoms     = Uom::orderBy('name')->get();
        $machines = Machine::orderBy('code')->get();

        // Optional: quick link to a Store Issue to auto-fill / link Gate Pass lines to stock.
        $issues = StoreIssue::with('project')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('gate_passes.create', compact(
            'projects',
            'parties',
            'items',
            'uoms',
            'machines',
            'issues'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $baseRules = [
            'gatepass_date'       => ['required', 'date'],
            'gatepass_time'       => ['nullable', 'date_format:H:i'],
            'type'                => ['required', 'in:project_material,machinery_maintenance'],

            // Optional linking helper (not stored on gate_passes table; only used to link lines)
            'store_issue_id'      => ['nullable', 'integer', 'exists:store_issues,id'],

            'project_id'          => ['nullable', 'integer', 'exists:projects,id'],
            'contractor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'to_party_id'         => ['nullable', 'integer', 'exists:parties,id'],
            'vehicle_number'      => ['nullable', 'string', 'max:50'],
            'driver_name'         => ['nullable', 'string', 'max:100'],
            'transport_mode'      => ['nullable', 'string', 'max:50'],
            'is_returnable'       => ['nullable', 'boolean'],
            'address'             => ['nullable', 'string', 'max:500'],
            'reason'              => ['nullable', 'string', 'max:255'],
            'remarks'             => ['nullable', 'string'],
        ];

        $type = $request->input('type');

        if ($type === 'project_material') {
            $rules = array_merge($baseRules, [
                'lines'                         => ['required', 'array', 'min:1'],
                'lines.*.item_id'               => ['required', 'integer', 'exists:items,id'],
                'lines.*.uom_id'                => ['required', 'integer', 'exists:uoms,id'],
                'lines.*.qty'                   => ['required', 'numeric', 'min:0.001'],
                'lines.*.is_returnable'         => ['nullable', 'boolean'],
                'lines.*.expected_return_date'  => ['nullable', 'date'],
                'lines.*.remarks'               => ['nullable', 'string', 'max:255'],

                // Phase-1: optional linkage to Store Issue / stock for traceable returns
                'lines.*.store_issue_line_id'   => ['nullable', 'integer', 'exists:store_issue_lines,id'],
                'lines.*.store_stock_item_id'   => ['nullable', 'integer', 'exists:store_stock_items,id'],
            ]);
        } else {
            // machinery_maintenance
            $rules = array_merge($baseRules, [
                'lines'                         => ['required', 'array', 'min:1'],
                'lines.*.machine_id'            => ['required', 'integer', 'exists:machines,id'],
                'lines.*.qty'                   => ['required', 'numeric', 'min:0.001'],
                'lines.*.expected_return_date'  => ['required', 'date'],
                'lines.*.remarks'               => ['nullable', 'string', 'max:255'],
            ]);
        }

        $data = $request->validate($rules);

        DB::beginTransaction();

        try {
            $linkedIssue = null;
            if (! empty($data['store_issue_id'])) {
                $linkedIssue = StoreIssue::with(['project', 'contractor'])
                    ->findOrFail($data['store_issue_id']);

                // Enforce / auto-fill header project + contractor from linked issue
                if (! empty($data['project_id']) && (int) $data['project_id'] !== (int) $linkedIssue->project_id) {
                    throw new \RuntimeException('Selected project does not match the linked Store Issue.');
                }
                if (! empty($data['contractor_party_id'])
                    && ! empty($linkedIssue->contractor_party_id)
                    && (int) $data['contractor_party_id'] !== (int) $linkedIssue->contractor_party_id
                ) {
                    throw new \RuntimeException('Selected contractor does not match the linked Store Issue.');
                }

                $data['project_id'] = $data['project_id'] ?? $linkedIssue->project_id;
                $data['contractor_party_id'] = $data['contractor_party_id'] ?? $linkedIssue->contractor_party_id;
            }

            // Determine financial year based on gatepass_date (Indian FY: Apr–Mar)
            $gpDate       = Carbon::parse($data['gatepass_date']);
            $year         = (int) $gpDate->format('Y');
            $month        = (int) $gpDate->format('m');
            $fyStartYear  = $month >= 4 ? $year : $year - 1;
            $fyEndYear    = $fyStartYear + 1;
            $fyLabel      = $fyStartYear . '/' . substr((string) $fyEndYear, -2);
            $prefix       = 'GP-' . $fyLabel . '-';

            $lastNumber = GatePass::where('gatepass_number', 'like', $prefix . '%')
                ->max('gatepass_number');

            if ($lastNumber) {
                $lastSeq = (int) substr($lastNumber, strlen($prefix));
                $nextSeq = $lastSeq + 1;
            } else {
                $nextSeq = 1;
            }

            $gatepassNumber = $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

            $gp                       = new GatePass();
            $gp->gatepass_number      = $gatepassNumber;
            $gp->gatepass_date        = $data['gatepass_date'];
            $gp->gatepass_time        = $data['gatepass_time'] ?? null;
            $gp->type                 = $data['type'];
            $gp->project_id           = $data['project_id'] ?? null;
            $gp->contractor_party_id  = $data['contractor_party_id'] ?? null;
            $gp->to_party_id          = $data['to_party_id'] ?? null;
            $gp->vehicle_number       = $data['vehicle_number'] ?? null;
            $gp->driver_name          = $data['driver_name'] ?? null;
            $gp->transport_mode       = $data['transport_mode'] ?? null;
            $gp->is_returnable        = (bool) ($data['is_returnable'] ?? false);
            $gp->address              = $data['address'] ?? null;
            $gp->reason               = $data['reason'] ?? null;
            $gp->remarks              = $data['remarks'] ?? null;
            $gp->status               = 'out';
            $gp->created_by           = $request->user()?->id;
            $gp->save();

            $lineNo = 1;

            foreach ($data['lines'] as $lineData) {
                $line                   = new GatePassLine();
                $line->gate_pass_id     = $gp->id;
                $line->line_no          = $lineNo++;
                $line->qty              = (float) $lineData['qty'];
                $line->remarks          = $lineData['remarks'] ?? null;

                if ($gp->type === 'project_material') {
                    $issueLineId = $lineData['store_issue_line_id'] ?? null;

                    if ($issueLineId) {
                        /** @var StoreIssueLine $issueLine */
                        $issueLine = StoreIssueLine::with(['issue', 'item', 'uom'])
                            ->findOrFail($issueLineId);

                        if ($linkedIssue && (int) $issueLine->store_issue_id !== (int) $linkedIssue->id) {
                            throw new \RuntimeException('One or more lines do not belong to the selected Store Issue.');
                        }

                        // Gate pass qty cannot exceed issued qty for that line (generic qty)
                        $maxQty = (float) ($issueLine->issued_weight_kg ?? 0);
                        if ($maxQty > 0 && $line->qty > $maxQty + 0.0001) {
                            throw new \RuntimeException(
                                "Gate pass qty for issue line #{$issueLine->id} cannot exceed issued qty ({$maxQty})."
                            );
                        }

                        $line->store_issue_line_id    = $issueLine->id;
                        $line->store_stock_item_id    = $issueLine->store_stock_item_id;

                        // Derive item/uom from issue line (ensures integrity even if request is tampered)
                        $line->item_id                = $issueLine->item_id;
                        $line->uom_id                 = $issueLine->uom_id;
                    } else {
                        // Manual entry (no issue linkage)
                        $line->item_id            = $lineData['item_id'];
                        $line->uom_id             = $lineData['uom_id'];

                        // Optional stock linkage (future-proof; UI may not use it yet)
                        if (! empty($lineData['store_stock_item_id'])) {
                            $line->store_stock_item_id = (int) $lineData['store_stock_item_id'];
                        }
                    }

                    $line->is_returnable        = (bool) ($lineData['is_returnable'] ?? false);
                    $line->expected_return_date = $lineData['expected_return_date'] ?? null;
                } else {
                    // machinery_maintenance
                    $line->machine_id           = $lineData['machine_id'];
                    $line->is_returnable        = true;
                    $line->expected_return_date = $lineData['expected_return_date'] ?? null;
                }

                $line->save();
            }

            DB::commit();

            return redirect()
                ->route('gate-passes.show', $gp)
                ->with('success', 'Gate pass created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['general' => 'Failed to save gate pass: ' . $e->getMessage()]);
        }
    }

    public function show(GatePass $gatePass): View
    {
        $gatePass->load([
            'project',
            'contractor',
            'toParty',
            'createdBy',
            'approvedBy',
            'lines.item',
            'lines.uom',
            'lines.machine',
            'lines.storeIssueLine.issue.voucher',
            'lines.storeIssueLine.issue.project',
            'lines.storeIssueLine.issue.contractor',
            'lines.stockItem',
            'storeReturns.project',
            'storeReturns.contractor',
            'storeReturns.issue',
            'storeReturns.voucher',
        ]);

        return view('gate_passes.show', [
            'gatePass' => $gatePass,
        ]);
    }

    public function pdf(GatePass $gate_pass, SettingsService $settings)
    {
        $gate_pass->load([
            'project',
            'contractor',
            'toParty',
            'lines.item',
            'lines.machine',
            'lines.uom',
            'lines.storeIssueLine.issue.voucher',
            'lines.storeIssueLine.issue.project',
            'lines.storeIssueLine.issue.contractor',
            'lines.stockItem',
            'createdBy',
        ]);

        $defaultCompanyId = $settings->get('general', 'default_company_id', null);

        $companyQuery = Company::query();

        if ($defaultCompanyId) {
            $company = $companyQuery->find($defaultCompanyId);
        }

        if (! isset($company) || ! $company) {
            $company = $companyQuery->where('is_default', true)->first()
                ?? $companyQuery->first();
        }

        $pdf = Pdf::loadView('gate_passes.pdf', [
            'gatePass' => $gate_pass,
            'company'  => $company,
        ])
            ->setPaper('a4');

        $fileName = $gate_pass->gatepass_number . '.pdf';

        return $pdf->stream($fileName);
    }

    public function returnForm(GatePass $gatePass): View|RedirectResponse
    {
        $gatePass->load(['lines.item', 'lines.uom', 'lines.machine', 'lines.storeIssueLine', 'lines.stockItem']);

        if (! in_array($gatePass->status, ['out', 'partially_returned'], true)) {
            return redirect()
                ->route('gate-passes.show', $gatePass)
                ->withErrors(['general' => 'Only gate passes with status OUT or PARTIALLY RETURNED can register returns.']);
        }

        $returnableLines = $gatePass->lines->filter(function (GatePassLine $line) {
            return $line->is_returnable;
        });

        if ($returnableLines->isEmpty()) {
            return redirect()
                ->route('gate-passes.show', $gatePass)
                ->withErrors(['general' => 'There are no returnable lines on this gate pass.']);
        }

        return view('gate_passes.return', [
            'gatePass'        => $gatePass,
            'returnableLines' => $returnableLines,
        ]);
    }

    public function registerReturn(Request $request, GatePass $gatePass): RedirectResponse
    {
        $gatePass->load(['lines.storeIssueLine', 'lines.stockItem']);

        if (! in_array($gatePass->status, ['out', 'partially_returned'], true)) {
            return redirect()
                ->route('gate-passes.show', $gatePass)
                ->withErrors(['general' => 'Only gate passes with status OUT or PARTIALLY RETURNED can register returns.']);
        }

        $returnableLines = $gatePass->lines->where('is_returnable', true);

        if ($returnableLines->isEmpty()) {
            return redirect()
                ->route('gate-passes.show', $gatePass)
                ->withErrors(['general' => 'There are no returnable lines on this gate pass.']);
        }

        $data = $request->validate([
            'return_lines'                   => ['required', 'array'],
            'return_lines.*.this_return_qty' => ['nullable', 'numeric', 'min:0'],
            'return_lines.*.returned_on'     => ['nullable', 'date'],
        ]);

        $createStoreReturn = $request->boolean('create_store_return');

        DB::beginTransaction();

        try {
            $anyReturnedNow = false;

            /** @var array<int, array{line:GatePassLine, qty:float, returned_on:string|null}> $returnedNow */
            $returnedNow = [];

            foreach ($returnableLines as $line) {
                $key       = (string) $line->id;
                $lineInput = $data['return_lines'][$key] ?? null;

                if (! $lineInput) {
                    continue;
                }

                $thisReturn = isset($lineInput['this_return_qty'])
                    ? (float) $lineInput['this_return_qty']
                    : 0.0;

                if ($thisReturn <= 0) {
                    continue;
                }

                $pending = (float) $line->qty - (float) ($line->returned_qty ?? 0.0);

                if ($thisReturn > $pending + 0.0001) {
                    DB::rollBack();

                    return back()
                        ->withInput()
                        ->withErrors([
                            'general' => "Return qty for line #{$line->line_no} cannot exceed pending qty.",
                        ]);
                }

                $line->returned_qty = (float) ($line->returned_qty ?? 0) + $thisReturn;

                if (! empty($lineInput['returned_on'])) {
                    $line->returned_on = $lineInput['returned_on'];
                } elseif (! $line->returned_on) {
                    $line->returned_on = now()->toDateString();
                }

                $line->save();

                $returnedNow[] = [
                    'line'        => $line,
                    'qty'         => $thisReturn,
                    'returned_on' => $line->returned_on,
                ];

                $anyReturnedNow = true;
            }

            if ($anyReturnedNow) {
                $returnableLines = $gatePass->refresh()->lines->where('is_returnable', true);

                $allFullyReturned = true;
                $anyReturned      = false;

                foreach ($returnableLines as $line) {
                    $qty      = (float) $line->qty;
                    $returned = (float) ($line->returned_qty ?? 0);

                    if ($returned > 0.0001) {
                        $anyReturned = true;
                    }

                    if ($qty > $returned + 0.0001) {
                        $allFullyReturned = false;
                    }
                }

                if ($allFullyReturned) {
                    $gatePass->status = 'closed';
                } elseif ($anyReturned) {
                    $gatePass->status = 'partially_returned';
                }

                $gatePass->save();
            }

            // Phase-1: If requested, auto-create Store Return for stock-linked lines.
            $createdReturns = [];

            if ($createStoreReturn && $anyReturnedNow && $gatePass->type === 'project_material') {
                // Only lines linked to stock can be posted back to Store Return.
                $eligible = array_values(array_filter($returnedNow, function ($row) {
                    /** @var GatePassLine $gpLine */
                    $gpLine = $row['line'];
                    return ! empty($gpLine->store_stock_item_id);
                }));

                if (! empty($eligible)) {
                    // Group by Store Issue (if available) to keep returns tidy
                    $groups = [];
                    foreach ($eligible as $row) {
                        /** @var GatePassLine $gpLine */
                        $gpLine = $row['line'];
                        $issueId = $gpLine->storeIssueLine?->store_issue_id ?? null;
                        $groupKey = $issueId ? (string) $issueId : '0';
                        $groups[$groupKey][] = $row;
                    }

                    foreach ($groups as $groupKey => $rows) {
                        $issue = null;
                        $issueId = ($groupKey !== '0') ? (int) $groupKey : null;
                        if ($issueId) {
                            $issue = StoreIssue::find($issueId);
                        }

                        // Choose a single date for the return header (use latest returned_on in this batch)
                        $returnDate = collect($rows)
                            ->pluck('returned_on')
                            ->filter()
                            ->max() ?: now()->toDateString();

                        $return = new StoreReturn();
                        $return->return_date            = $returnDate;
                        $return->store_issue_id         = $issue?->id;
                        $return->gate_pass_id          = $gatePass->id;
                        $return->project_id             = $gatePass->project_id ?? ($issue?->project_id);
                        $return->contractor_party_id    = $gatePass->contractor_party_id ?? ($issue?->contractor_party_id);
                        $return->contractor_person_name = $issue?->contractor_person_name;
                        $return->status                 = 'posted';
                        $return->reason                 = 'Gate Pass Return: ' . $gatePass->gatepass_number;
                        $return->remarks                = 'Auto-created from gate pass return registration.';
                        $return->created_by             = $request->user()?->id;
                        $return->save();

                        $return->return_number = app(DocumentNumberService::class)->storeReturn($return);
                        $return->save();

                        $hasOwnMaterial = false;

                        foreach ($rows as $row) {
                            /** @var GatePassLine $gpLine */
                            $gpLine = $row['line'];
                            $returnQty = (float) ($row['qty'] ?? 0);

                            if ($returnQty <= 0.0001) {
                                continue;
                            }

                            /** @var StoreStockItem $stock */
                            $stock = StoreStockItem::with('item')
                                ->lockForUpdate()
                                ->findOrFail((int) $gpLine->store_stock_item_id);

                            // Guard: do not allow QC-hold stock in returns
                            if (($stock->status ?? null) === 'blocked_qc') {
                                throw new \RuntimeException('QC-hold stock cannot be returned. Complete QC first.');
                            }

                            // Store Return is only for non-raw material
                            if (in_array($stock->material_category, ['steel_plate', 'steel_section'], true)) {
                                throw new \RuntimeException(
                                    'Raw material cannot be returned via Store Return. Use remnant / production flow.'
                                );
                            }

                            // If linked to Store Issue Line, enforce per-line remaining
                            $issueLineId = $gpLine->store_issue_line_id;
                            $issueLine = null;

                            if ($issueLineId) {
                                $issueLine = StoreIssueLine::lockForUpdate()->findOrFail((int) $issueLineId);

                                // Ensure issue-line <> stock mapping matches
                                if ((int) $issueLine->store_stock_item_id !== (int) $stock->id) {
                                    throw new \RuntimeException('Gate pass line linkage mismatch (issue line vs stock item).');
                                }

                                if ($issue && (int) $issueLine->store_issue_id !== (int) $issue->id) {
                                    throw new \RuntimeException('Gate pass issue-line does not belong to the expected Store Issue.');
                                }

                                $issuedQty = (float) ($issueLine->issued_weight_kg ?? 0);

                                $alreadyReturned = (float) StoreReturnLine::where('store_issue_line_id', $issueLine->id)
                                    ->sum('returned_weight_kg');

                                $remaining = max(0.0, $issuedQty - $alreadyReturned);

                                if ($remaining <= 0.0001) {
                                    throw new \RuntimeException(
                                        "Issue line #{$issueLine->id} has no pending quantity to return."
                                    );
                                }

                                if ($returnQty > $remaining + 0.0001) {
                                    throw new \RuntimeException(
                                        "Cannot return {$returnQty} against issue line #{$issueLine->id}; only {$remaining} is pending."
                                    );
                                }
                            }

                            if (! $stock->is_client_material) {
                                $hasOwnMaterial = true;
                            }

                            $totalQty     = (float) ($stock->weight_kg_total ?? 0);
                            $availableQty = (float) ($stock->weight_kg_available ?? 0);

                            if ($totalQty <= 0) {
                                throw new \RuntimeException(
                                    "Stock item ID {$stock->id} has no total quantity defined."
                                );
                            }

                            // How much is currently issued = total - available
                            $issuedStockQty = max(0.0, $totalQty - $availableQty);
                            if ($issuedStockQty <= 0.0001) {
                                throw new \RuntimeException(
                                    "Stock item ID {$stock->id} has no issued quantity to return."
                                );
                            }

                            // Do not allow returning more than what was issued (stock level)
                            if ($returnQty > $issuedStockQty + 0.0001) {
                                throw new \RuntimeException(
                                    "Cannot return {$returnQty} for stock item #{$stock->id}; only {$issuedStockQty} is currently issued."
                                );
                            }

                            $line = new StoreReturnLine();
                            $line->store_return_id     = $return->id;
                            $line->store_issue_line_id = $issueLine?->id;
                            $line->store_stock_item_id = $stock->id;
                            $line->item_id             = $stock->item_id;
                            $line->brand               = $stock->brand;
                            $line->uom_id              = $stock->item?->uom_id;
                            $line->returned_qty_pcs    = 1;
                            $line->returned_weight_kg  = $returnQty;
                            $line->remarks             = 'Gate Pass: ' . $gatePass->gatepass_number . ($gpLine->remarks ? (' | ' . $gpLine->remarks) : '');
                            $line->save();

                            // Update stock availability in weight terms
                            $newAvailable = min($totalQty, $availableQty + $returnQty);
                            $stock->weight_kg_available = $newAvailable;

                            if ($newAvailable <= 0.0001) {
                                $stock->weight_kg_available = 0.0;
                                $stock->status              = 'issued';
                            } else {
                                $stock->status = 'available';
                            }

                            // Keep pcs roughly in sync (not critical for non-raw)
                            if (! is_null($stock->qty_pcs_total) && $stock->qty_pcs_total > 0
                                && ! is_null($stock->weight_kg_total) && $stock->weight_kg_total > 0
                            ) {
                                $ratio = $stock->weight_kg_available / $stock->weight_kg_total;
                                $stock->qty_pcs_available = (int) round($stock->qty_pcs_total * $ratio);
                            }

                            $stock->save();
                        }

                        // Auto-mark "not required" for accounts if the return contains only client-supplied material.
                        if (! $hasOwnMaterial) {
                            $return->accounting_status    = 'not_required';
                            $return->accounting_posted_by = $request->user()?->id;
                            $return->accounting_posted_at = now();
                            $return->save();

                            ActivityLog::logCustom(
                                'accounts_posting_not_required',
                                'Store return ' . ($return->return_number ?: ('#' . $return->id)) . ' created with client-supplied material only. No accounting entry required.',
                                $return,
                                [
                                    'accounting_status' => 'not_required',
                                    'business_date'     => optional($return->return_date)->toDateString(),
                                ]
                            );
                        }

                        $createdReturns[] = $return;
                    }
                }
            }

            DB::commit();

            if ($createStoreReturn) {
                if (! empty($createdReturns)) {
                    if (count($createdReturns) === 1) {
                        return redirect()
                            ->route('store-returns.show', $createdReturns[0])
                            ->with('success', 'Gate pass return saved and Store Return created.');
                    }

                    $nums = collect($createdReturns)->pluck('return_number')->filter()->implode(', ');

                    return redirect()
                        ->route('store-returns.index')
                        ->with('success', 'Gate pass return saved. Store Returns created: ' . ($nums ?: count($createdReturns) . ' return(s).'));
                }

                // Fallback: just redirect to Store Return create (manual) if nothing was eligible
                $redirectIssueId = null;
                $firstLineWithIssue = $gatePass->lines->firstWhere('store_issue_line_id', '!=', null);
                if ($firstLineWithIssue && $firstLineWithIssue->storeIssueLine) {
                    $redirectIssueId = $firstLineWithIssue->storeIssueLine->store_issue_id;
                }

                return redirect()
                    ->route('store-returns.create', array_filter([
                        'store_issue_id' => $redirectIssueId,
                    ]))
                    ->with('success', 'Gate pass return saved. Please create Store Return manually for non-linked lines (if any).');
            }

            return redirect()
                ->route('gate-passes.show', $gatePass)
                ->with('success', 'Gate pass return registered successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['general' => 'Failed to register gate pass return: ' . $e->getMessage()]);
        }
    }

    public function closeWithoutFullReturn(Request $request, GatePass $gatePass): RedirectResponse
    {
        if (! in_array($gatePass->status, ['out', 'partially_returned'], true)) {
            return redirect()
                ->route('gate-passes.show', $gatePass)
                ->withErrors(['general' => 'Only gate passes with status OUT or PARTIALLY RETURNED can be closed.']);
        }

        $gatePass->status = 'closed';
        $gatePass->save();

        return redirect()
            ->route('gate-passes.show', $gatePass)
            ->with('success', 'Gate pass closed. Pending quantities will be treated as consumed.');
    }
}
