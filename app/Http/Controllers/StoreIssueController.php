<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Project;
use App\Models\StoreIssue;
use App\Models\StoreIssueLine;
use App\Models\StoreReturnLine;
use App\Models\StoreRequisition;
use App\Models\StoreRequisitionLine;
use App\Models\StoreStockItem;
use App\Models\ActivityLog;
use App\Services\Accounting\StoreIssuePostingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StoreIssueController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:store.issue.view')
            ->only(['index', 'show', 'ajaxLines']);

        $this->middleware('permission:store.issue.create')
            ->only(['create', 'store']);

        $this->middleware('permission:store.issue.post_to_accounts')
            ->only(['postToAccounts']);
    }


    public function ajaxLines(StoreIssue $storeIssue): JsonResponse
    {
        // Used by UI helpers (e.g., Gate Pass link-to-issue) to fetch issue lines.
        $storeIssue->load(['project', 'contractor', 'lines.item', 'lines.uom']);

        $lines = $storeIssue->lines->map(function (StoreIssueLine $line) {
            return [
                'id'                 => $line->id,
                'store_stock_item_id'=> $line->store_stock_item_id,
                'item_id'            => $line->item_id,
                'item_code'          => $line->item?->code,
                'item_name'          => $line->item?->name,
                'uom_id'             => $line->uom_id,
                'uom_name'           => $line->uom?->name,
                // For Store Issue (non-raw), issued_weight_kg acts as the generic issued quantity.
                'qty'                => (float) ($line->issued_weight_kg ?? 0),
            ];
        })->values();

        return response()->json([
            'issue' => [
                'id'                  => $storeIssue->id,
                'project_id'           => $storeIssue->project_id,
                'contractor_party_id'  => $storeIssue->contractor_party_id,
            ],
            'lines' => $lines,
        ]);
    }

    public function index(): View
    {
        $issues = StoreIssue::with(['project', 'contractor', 'requisition', 'voucher'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('store_issues.index', compact('issues'));
    }

    public function create(Request $request): View
    {
        $projects    = Project::orderBy('code')->get();
        $contractors = Party::where('is_contractor', true)->orderBy('name')->get();

        $selectedRequisitionId = $request->query('store_requisition_id')
            ? (int) $request->query('store_requisition_id')
            : null;

        // Only requisitions with at least one line pending
        $requisitions = StoreRequisition::with(['project', 'contractor'])
            ->whereHas('lines', function ($q) {
                $q->whereColumn('issued_qty', '<', 'required_qty');
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $selectedRequisition = null;
        $pendingItemIds      = [];

        if ($selectedRequisitionId) {
            $selectedRequisition = StoreRequisition::with([
                'project',
                'contractor',
                'lines.item.uom',
            ])->find($selectedRequisitionId);

            if ($selectedRequisition) {
                // Collect pending item ids (required_qty > issued_qty)
                foreach ($selectedRequisition->lines as $line) {
                    $required = (float) ($line->required_qty ?? 0);
                    $issued   = (float) ($line->issued_qty ?? 0);
                    if ($required > $issued + 0.0001) {
                        $pendingItemIds[] = (int) $line->item_id;
                    }
                }
                $pendingItemIds = array_values(array_unique(array_filter($pendingItemIds)));
            }
        }

        // Only non-raw material should be issued via Store Issue
        $stockItemsQuery = StoreStockItem::with(['item.uom', 'project'])
            ->where('status', 'available')
            ->whereNotIn('material_category', ['steel_plate', 'steel_section'])
            ->where(function ($q) {
                $q->where('qty_pcs_available', '>', 0)
                  ->orWhere('weight_kg_available', '>', 0);
            })
            ->orderByDesc('id');

        // If requisition is selected, restrict stock pool to pending requisition items
        if (! empty($pendingItemIds)) {
            $stockItemsQuery->whereIn('item_id', $pendingItemIds);
        }
        if ($selectedRequisition && $selectedRequisition->project_id) {
            $projectId = (int) $selectedRequisition->project_id;

            // Allow issuing OWN material from GENERAL stock (project_id NULL) into a project.
            // Client material must remain project-scoped.
            $stockItemsQuery->where(function ($q) use ($projectId) {
                $q->where(function ($q2) use ($projectId) {
                    $q2->where('is_client_material', true)
                       ->where('project_id', $projectId);
                })->orWhere(function ($q2) use ($projectId) {
                    $q2->where('is_client_material', false)
                       ->where(function ($q3) use ($projectId) {
                           $q3->whereNull('project_id')
                              ->orWhere('project_id', $projectId);
                       });
                });
            });
        }

        $limit = $selectedRequisition ? 1000 : 200;
        $stockItems = $stockItemsQuery->limit($limit)->get();

        return view('store_issues.create', [
            'projects'              => $projects,
            'contractors'           => $contractors,
            'requisitions'          => $requisitions,
            'selectedRequisitionId' => $selectedRequisitionId,
            'selectedRequisition'   => $selectedRequisition,
            'stockItems'            => $stockItems,
        ]);
    }


    public function postToAccounts(
        StoreIssue $storeIssue,
        StoreIssuePostingService $postingService
    ): RedirectResponse {
        try {
            $voucher = $postingService->post($storeIssue);

            if ($voucher) {
                return redirect()
                    ->route('store-issues.show', $storeIssue)
                    ->with('success', 'Store issue posted to accounts as voucher ' . $voucher->voucher_no . '.');
            }

            return redirect()
                ->route('store-issues.show', $storeIssue)
                ->with('success', 'No accounting entry required for this store issue (client-supplied material only).');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('store-issues.show', $storeIssue)
                ->with('error', 'Failed to post store issue to accounts: ' . $e->getMessage());
        }
    }
  
  	public function store(Request $request): RedirectResponse
    {
        $rules = [
            'issue_date'             => ['required', 'date'],
            'project_id'             => ['nullable', 'integer', 'exists:projects,id'],
            'store_requisition_id'   => ['nullable', 'integer', 'exists:store_requisitions,id'],
            'contractor_party_id'    => ['nullable', 'integer', 'exists:parties,id'],
            'contractor_person_name' => ['nullable', 'string', 'max:100'],
            'remarks'                => ['nullable', 'string'],

            'lines'                           => ['required', 'array', 'min:1'],
            'lines.*.store_stock_item_id'     => ['required', 'integer', 'exists:store_stock_items,id'],
            'lines.*.issue_qty'               => ['required', 'numeric', 'min:0.0001'],
            'lines.*.remarks'                 => ['nullable', 'string', 'max:255'],
        ];

        if ($request->filled('store_requisition_id')) {
            $rules['lines.*.store_requisition_line_id'] = [
                'required',
                'integer',
                'exists:store_requisition_lines,id',
            ];
        } else {
            $rules['lines.*.store_requisition_line_id'] = [
                'nullable',
                'integer',
                'exists:store_requisition_lines,id',
            ];
        }

        $data = $request->validate($rules);

        DB::beginTransaction();

        try {
            /** @var StoreRequisition|null $requisition */
            $requisition          = null;
            $requisitionLinesById = [];

            if (! empty($data['store_requisition_id'])) {
                $requisition = StoreRequisition::with('lines')
                    ->lockForUpdate()
                    ->find($data['store_requisition_id']);

                if (! $requisition) {
                    throw new \RuntimeException('Selected requisition not found.');
                }

                $requisitionLinesById = $requisition->lines->keyBy('id');
            }

            // --------- Issue header ----------
            $issue                       = new StoreIssue();
            $issue->issue_date           = $data['issue_date'];
            $issue->store_requisition_id = $data['store_requisition_id'] ?? null;

            if ($requisition) {
                // For requisition-based issues, header is taken from requisition
                $issue->project_id             = $requisition->project_id;
                $issue->contractor_party_id    = $requisition->contractor_party_id;
                $issue->contractor_person_name = $requisition->contractor_person_name;
            } else {
                // General issue: project is optional
                $issue->project_id             = $data['project_id'] ?? null;
                $issue->contractor_party_id    = $data['contractor_party_id'] ?? null;
                $issue->contractor_person_name = $data['contractor_person_name'] ?? null;
            }

            $issue->issued_to_user_id = null;
            $issue->status            = 'posted';
            $issue->remarks           = $data['remarks'] ?? null;
            $issue->created_by        = $request->user()?->id;
            $issue->save();

            // Generate issue number via central service (same pattern ISS-YY-XXXX)
   			 $issue->issue_number = app(\App\Services\DocumentNumberService::class)
  		      ->storeIssue($issue);
  			  $issue->save();


            // Accumulate total issued quantity per requisition line
            $requisitionLineAllocations = [];

            // If ALL lines are client-supplied, no accounting posting is required.
            $hasOwnMaterial = false;

            // --------- Lines ----------
            foreach ($data['lines'] as $lineData) {
                /** @var StoreStockItem $stock */
                $stock = StoreStockItem::with('item')
                    ->lockForUpdate()
                    ->where('status', 'available')
                    ->findOrFail($lineData['store_stock_item_id']);

                if (! $stock->is_client_material) {
                    $hasOwnMaterial = true;
                }

                // Store Issue is only for non-raw material
                if (in_array($stock->material_category, ['steel_plate', 'steel_section'], true)) {
                    throw new \RuntimeException('Raw material cannot be issued via Store Issue. Use production / DPR flow.');
                }

                // Project scope + ownership guard
                // - Client material must belong to the same project as the issue
                // - Own material may be GENERAL (project_id NULL) or the same project
                if ($issue->project_id) {
                    $issueProjectId = (int) $issue->project_id;

                    if ($stock->is_client_material) {
                        if ((int) $stock->project_id !== $issueProjectId) {
                            throw new \RuntimeException('Client material stock must belong to the same project as this issue.');
                        }
                    } else {
                        if (! is_null($stock->project_id) && (int) $stock->project_id !== $issueProjectId) {
                            throw new \RuntimeException('Selected stock item belongs to a different project. Use GENERAL stock or same-project stock.');
                        }
                    }
                }
                $availableQty = (float) ($stock->weight_kg_available ?? 0);
                if ($availableQty <= 0) {
                    throw new \RuntimeException("Stock item ID {$stock->id} has no available quantity.");
                }

                $issueQty = (float) $lineData['issue_qty'];
                if ($issueQty <= 0) {
                    throw new \RuntimeException('Issue quantity must be greater than zero.');
                }

                if ($issueQty > $availableQty + 0.0001) {
                    throw new \RuntimeException(
                        "Issue quantity {$issueQty} exceeds available stock {$availableQty} for stock item ID {$stock->id}."
                    );
                }

                $reqLineId = $lineData['store_requisition_line_id'] ?? null;

                if ($requisition && $reqLineId) {
                    /** @var StoreRequisitionLine|null $reqLine */
                    $reqLine = $requisitionLinesById[$reqLineId] ?? null;

                    if (! $reqLine || $reqLine->store_requisition_id !== $requisition->id) {
                        throw new \RuntimeException('Invalid requisition line selected for issue.');
                    }

                    // Optional: check item match
                    if ($reqLine->item_id !== $stock->item_id) {
                        throw new \RuntimeException('Selected stock item does not match requisition line item.');
                    }

                    // Brand enforcement:
                    // If requisition line has a brand specified, stock brand must match (case-insensitive).
                    $reqBrand   = trim((string) ($reqLine->preferred_make ?? ''));
                    $stockBrand = trim((string) ($stock->brand ?? ''));
                    if ($reqBrand !== '' && strcasecmp($reqBrand, $stockBrand) !== 0) {
                        throw new \RuntimeException(
                            'Selected stock brand "' . ($stockBrand ?: '-') . '" does not match requisition brand "' . $reqBrand . '" for requisition line #' . $reqLine->id . '.'
                        );
                    }

                    $required = (float) ($reqLine->required_qty ?? 0);
                    $issued   = (float) ($reqLine->issued_qty ?? 0);
                    $pending  = $required - $issued;

                    if ($issueQty > $pending + 0.0001) {
                        throw new \RuntimeException(
                            "Cannot issue {$issueQty} against requisition line #{$reqLine->id}; only {$pending} pending."
                        );
                    }

                    if (! isset($requisitionLineAllocations[$reqLineId])) {
                        $requisitionLineAllocations[$reqLineId] = 0.0;
                    }
                    $requisitionLineAllocations[$reqLineId] += $issueQty;
                } elseif ($requisition && ! $reqLineId) {
                    throw new \RuntimeException('Requisition-based issue line must be linked to a requisition line.');
                }

                // Create issue line
                $issueLine                      = new StoreIssueLine();
                $issueLine->store_issue_id      = $issue->id;
                $issueLine->store_stock_item_id = $stock->id;
                $issueLine->item_id             = $stock->item_id;
                $issueLine->uom_id              = $stock->item?->uom_id;
                // For non-raw items the true quantity is stored in issued_weight_kg (generic qty)
                $issueLine->issued_qty_pcs      = 1;
                $issueLine->issued_weight_kg    = $issueQty;
                $issueLine->store_requisition_line_id = $reqLineId;
                $issueLine->remarks             = $lineData['remarks'] ?? null;
                $issueLine->save();

                // Update stock quantity: single logical quantity field
                $stock->weight_kg_available = max(0, $availableQty - $issueQty);

                if ($stock->weight_kg_available <= 0.0001) {
                    $stock->weight_kg_available = 0;
                    $stock->qty_pcs_available   = 0;
                    $stock->status              = 'issued';
                }

                $stock->save();
            }

            // Auto-mark "not required" for accounts if the issue contains only client-supplied material.
            if (! $hasOwnMaterial) {
                $issue->accounting_status    = 'not_required';
                $issue->accounting_posted_by = $request->user()?->id;
                $issue->accounting_posted_at = now();
                $issue->save();

                ActivityLog::logCustom(
                    'accounts_posting_not_required',
                    'Store issue ' . ($issue->issue_number ?: ('#' . $issue->id)) . ' created with client-supplied material only. No accounting entry required.',
                    $issue,
                    [
                        'accounting_status' => 'not_required',
                        'business_date'     => optional($issue->issue_date)->toDateString(),
                    ]
                );
            }

            // --------- Update requisition lines & status ----------
            if ($requisition && ! empty($requisitionLineAllocations)) {
                foreach ($requisitionLineAllocations as $lineId => $issueQty) {
                    /** @var StoreRequisitionLine $reqLine */
                    $reqLine = $requisitionLinesById[$lineId];

                    $required = (float) ($reqLine->required_qty ?? 0);
                    $already  = (float) ($reqLine->issued_qty ?? 0);
                    $pending  = $required - $already;

                    if ($issueQty > $pending + 0.0001) {
                        throw new \RuntimeException(
                            "Cannot issue {$issueQty} against requisition line #{$reqLine->id}; only {$pending} pending."
                        );
                    }
                }

                // Apply updates
                foreach ($requisitionLineAllocations as $lineId => $issueQty) {
                    /** @var StoreRequisitionLine $reqLine */
                    $reqLine = $requisitionLinesById[$lineId];
                    $reqLine->issued_qty = (float) ($reqLine->issued_qty ?? 0) + $issueQty;
                    $reqLine->save();
                }

                // Refresh and check if requisition fully issued
                $requisition->load('lines');

                $allFulfilled = true;
                $anyIssued    = false;
                $hasDemand    = false;

                foreach ($requisition->lines as $line) {
                    $required = (float) ($line->required_qty ?? 0);
                    $issued   = (float) ($line->issued_qty ?? 0);

                    if ($issued > 0.0001) {
                        $anyIssued = true;
                    }

                    if ($required <= 0) {
                        continue;
                    }

                    $hasDemand = true;

                    if ($issued + 0.0001 < $required) {
                        $allFulfilled = false;
                    }
                }

                // Status transition:
                // - requested/approved -> issued (partial)
                // - issued -> closed (fully issued)
                if ($hasDemand) {
                    $newStatus = $requisition->status;

                    if ($allFulfilled) {
                        $newStatus = 'closed';
                    } elseif ($anyIssued) {
                        $newStatus = 'issued';
                    }

                    if ($newStatus !== $requisition->status) {
                        $requisition->status = $newStatus;
                        $requisition->save();
                    }
                }
            }

            DB::commit();

            return redirect()
                ->route('store-issues.show', $issue)
                ->with('success', 'Store issue created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['general' => 'Failed to save issue: ' . $e->getMessage()]);
        }
    }

    public function show(StoreIssue $storeIssue): View
    {
        $storeIssue->load(['project', 'contractor', 'lines.item', 'lines.uom', 'lines.stockItem.project']);

        $returnedByLine = collect();

        $lineIds = $storeIssue->lines->pluck('id')->filter()->values();
        if ($lineIds->isNotEmpty()) {
            $returnedByLine = StoreReturnLine::query()
                ->selectRaw('store_issue_line_id, SUM(returned_weight_kg) as returned_qty')
                ->whereIn('store_issue_line_id', $lineIds->all())
                ->groupBy('store_issue_line_id')
                ->pluck('returned_qty', 'store_issue_line_id');
        }

        return view('store_issues.show', [
            'issue'          => $storeIssue,
            'returnedByLine' => $returnedByLine,
        ]);
    }
}
