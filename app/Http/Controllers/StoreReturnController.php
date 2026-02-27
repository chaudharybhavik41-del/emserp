<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Project;
use App\Models\StoreIssue;
use App\Models\StoreIssueLine;
use App\Models\StoreReturn;
use App\Models\StoreReturnLine;
use App\Models\StoreStockItem;
use App\Models\ActivityLog;
use App\Services\Accounting\StoreReturnPostingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:store.return.view')
            ->only(['index', 'show']);

        $this->middleware('permission:store.return.create')
            ->only(['create', 'store']);

        // Reuse the same posting permission as Store Issues (keeps roles simple)
        $this->middleware('permission:store.issue.post_to_accounts')
            ->only(['postToAccounts']);
    }

    /**
     * List store returns.
     */
    public function index(): View
    {
        $returns = StoreReturn::with(['project', 'contractor', 'issue'])
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->paginate(25);

        return view('store_returns.index', compact('returns'));
    }

    /**
     * Create form for a new store return (non-raw materials only).
     *
     * Phase-1:
     * - If a Store Issue is selected, the UI will show Issue Lines and allow returning against each line (traceable).
     * - Otherwise, it falls back to selecting issued stock items (less traceable).
     */
    public function create(Request $request): View
    {
        $projects    = Project::orderBy('code')->get();
        $contractors = Party::where('is_contractor', true)->orderBy('name')->get();

        // Latest issues for dropdown (any issue can be the source of returns)
        $issues = StoreIssue::with('project')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $selectedIssueId = $request->query('store_issue_id');
        $issue = null;

        if ($selectedIssueId) {
            $issue = StoreIssue::with([
                'project',
                'contractor',
                'lines.item.uom',
                'lines.uom',
                'lines.stockItem.project',
            ])->find($selectedIssueId);
        }

        // Precompute issue-line balances (issued vs already returned) for traceable returns.
        $issueLineSummaries = collect();

        if ($issue && $issue->lines->isNotEmpty()) {
            $lineIds = $issue->lines->pluck('id')->filter()->all();

            $returnedMap = StoreReturnLine::query()
                ->selectRaw('store_issue_line_id, SUM(returned_weight_kg) as returned_qty')
                ->whereIn('store_issue_line_id', $lineIds)
                ->groupBy('store_issue_line_id')
                ->pluck('returned_qty', 'store_issue_line_id')
                ->toArray();

            $issueLineSummaries = $issue->lines->map(function (StoreIssueLine $line) use ($returnedMap) {
                $issued   = (float) ($line->issued_weight_kg ?? 0);
                $returned = (float) ($returnedMap[$line->id] ?? 0);
                $balance  = max(0.0, $issued - $returned);

                return [
                    'id'                 => $line->id,
                    'store_stock_item_id'=> $line->store_stock_item_id,
                    'item_code'          => $line->item?->code,
                    'item_name'          => $line->item?->name,
                    'uom_name'           => $line->uom?->name ?? $line->item?->uom?->name,
                    'issued_qty'         => $issued,
                    'returned_qty'       => $returned,
                    'balance_qty'        => $balance,
                    'stock_project'      => $line->stockItem?->project?->code,
                ];
            })->values();
        }

        // Stock items that have some quantity issued (weight_kg_total > weight_kg_available),
        // and are NON-RAW (no plates/sections).
        //
        // IMPORTANT: exclude QC-hold stock (blocked_qc), because it is never "issued"
        // but would otherwise appear as issued (available 0 < total).
        $stockItemsQuery = StoreStockItem::with(['item.uom', 'project'])
            ->whereNotIn('material_category', ['steel_plate', 'steel_section'])
            ->whereNotIn('status', ['blocked_qc'])
            ->whereNotNull('weight_kg_total')
            ->whereNotNull('weight_kg_available')
            ->whereColumn('weight_kg_available', '<', 'weight_kg_total')
            ->orderByDesc('id');

        // If an issue is selected, restrict to stock items that were used on that issue
        if ($issue) {
            $stockIds = $issue->lines
                ->pluck('store_stock_item_id')
                ->filter()
                ->unique()
                ->all();

            if (! empty($stockIds)) {
                $stockItemsQuery->whereIn('id', $stockIds);
            } else {
                // No stock items linked to this issue -> show none
                $stockItemsQuery->whereRaw('1 = 0');
            }
        }

        $stockItems = $stockItemsQuery
            ->limit(200)
            ->get();

        return view('store_returns.create', compact(
            'projects',
            'contractors',
            'issues',
            'selectedIssueId',
            'issue',
            'gatePass',
            'issueLineSummaries',
            'stockItems'
        ));
    }

    /**
     * Store a new non-raw-material store return.
     *
     * Phase-1:
     * - If store_issue_id is present, each returned line must reference store_issue_line_id
     *   and cannot exceed the pending qty on that issue line.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'return_date'            => ['required', 'date'],
            'project_id'             => ['nullable', 'integer', 'exists:projects,id'],
            'store_issue_id'         => ['nullable', 'integer', 'exists:store_issues,id'],
            'contractor_party_id'    => ['nullable', 'integer', 'exists:parties,id'],
            'contractor_person_name' => ['nullable', 'string', 'max:100'],
            'reason'                 => ['nullable', 'string', 'max:255'],
            'remarks'                => ['nullable', 'string'],

            'lines'                           => ['required', 'array', 'min:1'],
            'lines.*.store_stock_item_id'     => ['required', 'integer', 'exists:store_stock_items,id'],
            'lines.*.store_issue_line_id'     => ['nullable', 'integer', 'exists:store_issue_lines,id'],
            'lines.*.returned_weight_kg'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.remarks'                 => ['nullable', 'string', 'max:255'],
        ]);

        // Filter out zero/empty return lines (useful when returning against issue lines table)
        $lines = collect($data['lines'] ?? [])
            ->map(function ($l) {
                $l['returned_weight_kg'] = isset($l['returned_weight_kg']) ? (float) $l['returned_weight_kg'] : 0.0;
                return $l;
            })
            ->filter(fn ($l) => (float) ($l['returned_weight_kg'] ?? 0) > 0.0001)
            ->values();

        if ($lines->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['general' => 'Please enter at least one return quantity.']);
        }

        DB::beginTransaction();

        try {
            // If store_issue_id is not explicitly provided, infer it if all lines refer to the same issue.
            $headerIssueId = ! empty($data['store_issue_id']) ? (int) $data['store_issue_id'] : null;

            if (! $headerIssueId) {
                $issueLineIds = $lines->pluck('store_issue_line_id')->filter()->unique()->values();
                if ($issueLineIds->isNotEmpty()) {
                    $issueIds = StoreIssueLine::whereIn('id', $issueLineIds->all())
                        ->pluck('store_issue_id')
                        ->filter()
                        ->unique()
                        ->values();

                    if ($issueIds->count() === 1) {
                        $headerIssueId = (int) $issueIds->first();
                    }
                }
            }

            $issue = $headerIssueId ? StoreIssue::find($headerIssueId) : null;

            // Track if any own-material exists (otherwise no accounting posting is required)
            $hasOwnMaterial = false;

            // --------- Return header ----------
            $return = new StoreReturn();
            $return->return_date            = $data['return_date'];
            $return->store_issue_id         = $headerIssueId;
            $return->project_id             = $data['project_id']
                                              ?? ($issue ? $issue->project_id : null);
            $return->contractor_party_id    = $data['contractor_party_id']
                                              ?? ($issue ? $issue->contractor_party_id : null);
            $return->contractor_person_name = $data['contractor_person_name']
                                              ?? ($issue ? $issue->contractor_person_name : null);
            $return->status                 = 'posted';
            $return->reason                 = $data['reason'] ?? null;
            $return->remarks                = $data['remarks'] ?? null;
            $return->created_by             = $request->user()?->id;
            $return->save();

            // Generate return number via central service (same pattern RTN-YY-XXXX)
            $return->return_number = app(\App\Services\DocumentNumberService::class)
                ->storeReturn($return);
            $return->save();

            // --------- Lines ----------
            foreach ($lines as $lineData) {
                /** @var StoreStockItem $stock */
                $stock = StoreStockItem::with('item')
                    ->lockForUpdate()
                    ->findOrFail($lineData['store_stock_item_id']);

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
                $issuedQty = max(0.0, $totalQty - $availableQty);
                if ($issuedQty <= 0.0001) {
                    throw new \RuntimeException(
                        "Stock item ID {$stock->id} has no issued quantity to return."
                    );
                }

                $returnQty = (float) ($lineData['returned_weight_kg'] ?? 0);
                if ($returnQty <= 0) {
                    throw new \RuntimeException('Return quantity must be greater than zero.');
                }

                // Do not allow returning more than what was issued (stock level)
                if ($returnQty > $issuedQty + 0.0001) {
                    throw new \RuntimeException(
                        "Cannot return {$returnQty} for stock item #{$stock->id}; only {$issuedQty} is currently issued."
                    );
                }

                // --------- Issue-line traceability ----------
                $issueLine = null;
                $issueLineId = $lineData['store_issue_line_id'] ?? null;

                if ($headerIssueId) {
                    // If header issue is present, enforce that each return line maps to an issue line (traceable)
                    if (! $issueLineId) {
                        throw new \RuntimeException('Please select an Issue Line for each return line.');
                    }
                }

                if ($issueLineId) {
                    /** @var StoreIssueLine $issueLine */
                    $issueLine = StoreIssueLine::lockForUpdate()->findOrFail((int) $issueLineId);

                    if ($headerIssueId && (int) $issueLine->store_issue_id !== (int) $headerIssueId) {
                        throw new \RuntimeException('One or more return lines do not belong to the selected Store Issue.');
                    }

                    if ((int) $issueLine->store_stock_item_id !== (int) $stock->id) {
                        throw new \RuntimeException('Issue-line linkage mismatch (issue line vs stock item).');
                    }

                    $issuedLineQty = (float) ($issueLine->issued_weight_kg ?? 0);

                    $alreadyReturnedLine = (float) StoreReturnLine::where('store_issue_line_id', $issueLine->id)
                        ->sum('returned_weight_kg');

                    $remainingLine = max(0.0, $issuedLineQty - $alreadyReturnedLine);

                    if ($remainingLine <= 0.0001) {
                        throw new \RuntimeException(
                            "Issue line #{$issueLine->id} has no pending quantity to return."
                        );
                    }

                    if ($returnQty > $remainingLine + 0.0001) {
                        throw new \RuntimeException(
                            "Cannot return {$returnQty} against issue line #{$issueLine->id}; only {$remainingLine} is pending."
                        );
                    }
                }

                // --------- Create return line ----------
                $line = new StoreReturnLine();
                $line->store_return_id     = $return->id;
                $line->store_issue_line_id = $issueLine?->id;
                $line->store_stock_item_id = $stock->id;
                $line->item_id             = $stock->item_id;
                $line->brand               = $stock->brand;
                $line->uom_id              = $stock->item?->uom_id;
                // For non-raw items, the true quantity is returned_weight_kg (generic qty)
                $line->returned_qty_pcs    = 1;
                $line->returned_weight_kg  = $returnQty;
                $line->remarks             = $lineData['remarks'] ?? null;
                $line->save();

                // Update stock availability in weight terms
                $newAvailable = min($totalQty, $availableQty + $returnQty);
                $stock->weight_kg_available = $newAvailable;

                if ($newAvailable <= 0.0001) {
                    // Still fully out from store
                    $stock->weight_kg_available = 0.0;
                    $stock->status              = 'issued';
                } else {
                    // Some quantity is now back in store
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

            DB::commit();

            return redirect()
                ->route('store-returns.show', $return)
                ->with('success', 'Store return created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['general' => 'Failed to save return: ' . $e->getMessage()]);
        }
    }

    /**
     * Post the store return to Accounts (creates a voucher).
     */
    public function postToAccounts(
        StoreReturn $storeReturn,
        StoreReturnPostingService $postingService
    ): RedirectResponse {
        try {
            $voucher = $postingService->post($storeReturn);

            if ($voucher) {
                return redirect()
                    ->route('store-returns.show', $storeReturn)
                    ->with('success', 'Store return posted to accounts as voucher ' . $voucher->voucher_no . '.');
            }

            return redirect()
                ->route('store-returns.show', $storeReturn)
                ->with('success', 'No accounting entry required for this store return (client-supplied material only / opening).');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('store-returns.show', $storeReturn)
                ->with('error', 'Failed to post store return to accounts: ' . $e->getMessage());
        }
    }

    /**
     * Show a store return.
     */
    public function show(StoreReturn $storeReturn): View
    {
        $storeReturn->load([
            'project',
            'contractor',
            'issue',
            'gatePass',
            'lines.stockItem.item',
            'lines.stockItem.project',
            'lines.item',
            'lines.issueLine.issue',
        ]);

        return view('store_returns.show', [
            'return' => $storeReturn,
        ]);
    }
}
