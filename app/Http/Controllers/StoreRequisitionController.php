<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Party;
use App\Models\Project;
use App\Models\Machine;
use App\Models\StoreIssueLine;
use App\Models\StoreRequisition;
use App\Models\StoreRequisitionLine;
use App\Models\StoreStockItem;
use App\Models\Uom;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreRequisitionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:store.requisition.view')
            ->only(['index', 'show', 'ajaxAvailableBrands']);

        $this->middleware('permission:store.requisition.create')
            ->only(['create', 'store']);

        $this->middleware('permission:store.requisition.update')
            ->only(['edit', 'update']);
    }

    /**
     * List store requisitions.
     */
    public function index(): View
    {
        $requisitions = StoreRequisition::with(['project', 'contractor', 'requestedBy', 'machine'])
            ->orderByDesc('requisition_date')
            ->orderByDesc('id')
            ->paginate(25);

        return view('store_requisitions.index', compact('requisitions'));
    }

    /**
     * Show the create form.
     */
    public function create(): View
    {
        $projects    = Project::orderBy('code')->get();
        $contractors = Party::where('is_contractor', true)->orderBy('name')->get();
        $machines    = Machine::orderBy('code')->orderBy('name')->get(['id', 'code', 'name', 'current_project_id']);
        $uoms        = Uom::orderBy('name')->get();

        return view('store_requisitions.create', compact(
            'projects',
            'contractors',
            'machines',
            'uoms'
        ));
    }

    /**
     * AJAX: Return distinct brands that currently have AVAILABLE stock for a given item.
     * This powers the "Brand" dropdown on Store Requisition lines.
     */
    public function ajaxAvailableBrands(Request $request): JsonResponse
    {
        $itemId    = (int) $request->input('item_id');
        $projectId = $request->integer('project_id') ?: null;

        if ($itemId <= 0) {
            return response()->json(['brands' => []]);
        }

        // Only non-raw items are handled in Store Requisition / Store Issue (raw -> production flow)
        $q = StoreStockItem::query()
            ->where('item_id', $itemId)
            ->where('status', 'available')
            ->whereNotIn('material_category', ['steel_plate', 'steel_section'])
            // Must be issuable quantity
            ->where(function ($qq) {
                $qq->where('qty_pcs_available', '>', 0)
                   ->orWhere('weight_kg_available', '>', 0);
            });

        // Match the same project/ownership rules used in StoreIssue:
        // - Client material must be project-scoped
        // - Own material may be GENERAL (project_id NULL) or same project
        if ($projectId) {
            $q->where(function ($scope) use ($projectId) {
                $scope->where(function ($c) use ($projectId) {
                    $c->where('is_client_material', true)
                      ->where('project_id', $projectId);
                })->orWhere(function ($c) use ($projectId) {
                    $c->where('is_client_material', false)
                      ->where(function ($p) use ($projectId) {
                          $p->whereNull('project_id')
                            ->orWhere('project_id', $projectId);
                      });
                });
            });
        }

        $raw = $q->select('brand')->distinct()->pluck('brand')->all();

        $brands = [];
        foreach ($raw as $b) {
            $b = is_string($b) ? trim($b) : '';
            if ($b === '') {
                continue;
            }
            $brands[] = $b;
        }

        $brands = array_values(array_unique($brands));
        natcasesort($brands);
        $brands = array_values($brands);

        return response()->json(['brands' => $brands]);
    }

    /**
     * Store a new requisition.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'requisition_date'        => ['required', 'date'],
            'issue_purpose'           => ['required', 'string', 'in:general,machine_spare'],
            'project_id'              => ['nullable', 'integer', 'exists:projects,id'],
            'machine_id'              => ['nullable', 'integer', 'exists:machines,id'],
            'contractor_party_id'     => ['nullable', 'integer', 'exists:parties,id'],
            'contractor_person_name'  => ['nullable', 'string', 'max:100'],
            'remarks'                 => ['nullable', 'string'],

            'lines'                           => ['required', 'array', 'min:1'],
            'lines.*.item_id'                 => ['required', 'integer', 'exists:items,id'],
            'lines.*.uom_id'                  => ['required', 'integer', 'exists:uoms,id'],
            'lines.*.required_qty'            => ['required', 'numeric', 'min:0.001'],
            'lines.*.description'             => ['nullable', 'string', 'max:255'],
            // Brand (stored in preferred_make column for backward compatibility)
            'lines.*.preferred_make'          => ['nullable', 'string', 'max:100'],
            'lines.*.segment_reference'       => ['nullable', 'string', 'max:100'],
            'lines.*.remarks'                 => ['nullable', 'string', 'max:255'],
        ]);

        $issuePurpose = (string) ($data['issue_purpose'] ?? 'general');
        if ($issuePurpose === 'machine_spare' && empty($data['machine_id'])) {
            return back()
                ->withInput()
                ->withErrors(['machine_id' => 'Machine is required for machine spare requisition.']);
        }

        if ($issuePurpose === 'general' && empty($data['project_id'])) {
            return back()
                ->withInput()
                ->withErrors(['project_id' => 'Project is required for general requisition.']);
        }

        if ($issuePurpose === 'machine_spare' && empty($data['project_id']) && ! empty($data['machine_id'])) {
            $data['project_id'] = Machine::query()->whereKey((int) $data['machine_id'])->value('current_project_id');
        }

        // Safety: Store Requisitions are for NON-RAW items only.
        // (Raw material is handled via GRN / Production / Remnant flows.)
        $itemIds = collect($data['lines'] ?? [])
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values();

        if ($itemIds->isNotEmpty()) {
            $itemsById = Item::with(['type', 'category'])
                ->whereIn('id', $itemIds->all())
                ->get()
                ->keyBy('id');

            foreach ($itemIds as $itemId) {
                $item = $itemsById->get($itemId);

                if ($item && $item->type && $item->type->code === 'RAW') {
                    return back()
                        ->withInput()
                        ->withErrors([
                            'general' => 'Raw material items cannot be requested via Store Requisition. Use the Raw Material / Production flow.',
                        ]);
                }
            }

            if ($issuePurpose === 'machine_spare') {
                $allowedTypeCodes = $this->machineSpareAllowedTypeCodes();
                $excludedCategoryCodes = $this->machineSpareExcludedCategoryCodes();

                foreach ($itemIds as $itemId) {
                    $item = $itemsById->get($itemId);
                    $typeCode = strtoupper(trim((string) ($item?->type?->code ?? '')));
                    $categoryCode = strtoupper(trim((string) ($item?->category?->code ?? '')));

                    if (! in_array($typeCode, $allowedTypeCodes, true)) {
                        return back()
                            ->withInput()
                            ->withErrors([
                                'general' => 'Selected item is not allowed for machine spare requisition.',
                            ]);
                    }

                    if ($categoryCode !== '' && in_array($categoryCode, $excludedCategoryCodes, true)) {
                        return back()
                            ->withInput()
                            ->withErrors([
                                'general' => 'Selected item category is not allowed in machine spare requisition.',
                            ]);
                    }
                }
            }
        }

        DB::beginTransaction();

        try {
            $req = new StoreRequisition();
            $req->requisition_date       = $data['requisition_date'];
            $req->issue_purpose          = $issuePurpose;
            $req->project_id             = $data['project_id'];
            $req->machine_id             = $issuePurpose === 'machine_spare'
                ? (int) ($data['machine_id'] ?? 0) ?: null
                : null;
            $req->contractor_party_id    = $data['contractor_party_id'] ?? null;
            $req->contractor_person_name = $data['contractor_person_name'] ?? null;
            $req->requested_by_user_id   = $request->user()?->id;
            $req->status                 = 'requested';
            $req->remarks                = $data['remarks'] ?? null;
            $req->save();

            // Generate requisition number via central service (same pattern SR-YY-XXXX)
            $req->requisition_number = app(\App\Services\DocumentNumberService::class)
                ->storeRequisition($req);
            $req->save();

            foreach ($data['lines'] as $lineData) {
                $brand = trim((string) ($lineData['preferred_make'] ?? ''));
                $req->lines()->create([
                    'item_id'           => $lineData['item_id'],
                    'uom_id'            => $lineData['uom_id'],
                    'description'       => $lineData['description'] ?? null,
                    'required_qty'      => $lineData['required_qty'],
                    'issued_qty'        => 0,
                    'preferred_make'    => $brand !== '' ? $brand : null,
                    'segment_reference' => $lineData['segment_reference'] ?? null,
                    'remarks'           => $lineData['remarks'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('store-requisitions.show', $req)
                ->with('success', 'Store requisition created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['general' => 'Failed to save requisition: ' . $e->getMessage()]);
        }
    }

    /**
     * Show edit form.
     *
     * You can edit only while:
     * - status = 'requested'
     * - and there are NO store issues against any of its lines.
     */
    public function edit(StoreRequisition $storeRequisition): RedirectResponse|View
    {
        $storeRequisition->load(['project', 'contractor', 'requestedBy', 'machine', 'lines.item.uom']);

        // Guard: allow editing only when requested and no issues exist
        if ($storeRequisition->status !== 'requested') {
            return redirect()
                ->route('store-requisitions.show', $storeRequisition)
                ->withErrors(['general' => 'This requisition cannot be edited because it is already ' . $storeRequisition->status . '.']);
        }

        $lineIds = $storeRequisition->lines->pluck('id')->all();

        if (! empty($lineIds)) {
            $hasIssues = StoreIssueLine::whereIn('store_requisition_line_id', $lineIds)->exists();
            if ($hasIssues) {
                return redirect()
                    ->route('store-requisitions.show', $storeRequisition)
                    ->withErrors(['general' => 'This requisition cannot be edited because some lines have already been issued.']);
            }
        }

        $projects    = Project::orderBy('code')->get();
        $contractors = Party::where('is_contractor', true)->orderBy('name')->get();
        $machines    = Machine::orderBy('code')->orderBy('name')->get(['id', 'code', 'name', 'current_project_id']);
        $uoms        = Uom::orderBy('name')->get();

        return view('store_requisitions.edit', [
            'requisition' => $storeRequisition,
            'projects'    => $projects,
            'contractors' => $contractors,
            'machines'    => $machines,
            'uoms'        => $uoms,
        ]);
    }

    /**
     * Update an existing requisition.
     *
     * Only allowed while status = requested and no issues posted.
     * For now we allow changing header and line quantities/details,
     * but not changing items or adding/removing lines.
     */
    public function update(Request $request, StoreRequisition $storeRequisition): RedirectResponse
    {
        // Reload lines for checks and updates
        $storeRequisition->load('lines');

        // Only editable while in "requested" status
        if ($storeRequisition->status !== 'requested') {
            return redirect()
                ->route('store-requisitions.show', $storeRequisition)
                ->withErrors([
                    'general' => 'This requisition cannot be edited because it is already ' . $storeRequisition->status . '.',
                ]);
        }

        // Do not allow editing if any issues have already been created for these lines
        $lineIds = $storeRequisition->lines->pluck('id')->all();
        if (! empty($lineIds)) {
            $hasIssues = StoreIssueLine::whereIn('store_requisition_line_id', $lineIds)->exists();
            if ($hasIssues) {
                return redirect()
                    ->route('store-requisitions.show', $storeRequisition)
                    ->withErrors([
                        'general' => 'This requisition cannot be edited because some lines have already been issued.',
                    ]);
            }
        }

        // Validate incoming data
        $data = $request->validate([
            'requisition_date'        => ['required', 'date'],
            'issue_purpose'           => ['required', 'string', 'in:general,machine_spare'],
            'project_id'              => ['nullable', 'integer', 'exists:projects,id'],
            'machine_id'              => ['nullable', 'integer', 'exists:machines,id'],
            'contractor_party_id'     => ['nullable', 'integer', 'exists:parties,id'],
            'contractor_person_name'  => ['nullable', 'string', 'max:100'],
            'remarks'                 => ['nullable', 'string'],

            'lines'                     => ['required', 'array', 'min:1'],
            'lines.*.id'                => ['required', 'integer', 'exists:store_requisition_lines,id'],
            'lines.*.required_qty'      => ['required', 'numeric', 'min:0.001'],
            'lines.*.description'       => ['nullable', 'string', 'max:255'],
            'lines.*.preferred_make'    => ['nullable', 'string', 'max:100'],
            'lines.*.segment_reference' => ['nullable', 'string', 'max:100'],
            'lines.*.remarks'           => ['nullable', 'string', 'max:255'],
        ]);

        $issuePurpose = (string) ($data['issue_purpose'] ?? 'general');
        if ($issuePurpose === 'machine_spare' && empty($data['machine_id'])) {
            return back()
                ->withInput()
                ->withErrors(['machine_id' => 'Machine is required for machine spare requisition.']);
        }

        if ($issuePurpose === 'general' && empty($data['project_id'])) {
            return back()
                ->withInput()
                ->withErrors(['project_id' => 'Project is required for general requisition.']);
        }

        if ($issuePurpose === 'machine_spare' && empty($data['project_id']) && ! empty($data['machine_id'])) {
            $data['project_id'] = Machine::query()->whereKey((int) $data['machine_id'])->value('current_project_id');
        }

        if ($issuePurpose === 'machine_spare') {
            $lineItemIds = $storeRequisition->lines
                ->pluck('item_id')
                ->filter()
                ->unique()
                ->values();

            if ($lineItemIds->isNotEmpty()) {
                $itemsById = Item::with(['type', 'category'])
                    ->whereIn('id', $lineItemIds->all())
                    ->get()
                    ->keyBy('id');

                $allowedTypeCodes = $this->machineSpareAllowedTypeCodes();
                $excludedCategoryCodes = $this->machineSpareExcludedCategoryCodes();

                foreach ($lineItemIds as $itemId) {
                    $item = $itemsById->get($itemId);
                    $typeCode = strtoupper(trim((string) ($item?->type?->code ?? '')));
                    $categoryCode = strtoupper(trim((string) ($item?->category?->code ?? '')));

                    if (! in_array($typeCode, $allowedTypeCodes, true)) {
                        return back()
                            ->withInput()
                            ->withErrors([
                                'general' => 'Selected item is not allowed for machine spare requisition.',
                            ]);
                    }

                    if ($categoryCode !== '' && in_array($categoryCode, $excludedCategoryCodes, true)) {
                        return back()
                            ->withInput()
                            ->withErrors([
                                'general' => 'Selected item category is not allowed in machine spare requisition.',
                            ]);
                    }
                }
            }
        }

        DB::transaction(function () use ($storeRequisition, $data) {
            // --- Update header ---
            $storeRequisition->requisition_date       = $data['requisition_date'];
            $storeRequisition->issue_purpose          = $data['issue_purpose'];
            $storeRequisition->project_id             = $data['project_id'];
            $storeRequisition->machine_id             = $data['issue_purpose'] === 'machine_spare'
                ? ((int) ($data['machine_id'] ?? 0) ?: null)
                : null;
            $storeRequisition->contractor_party_id    = $data['contractor_party_id'] ?? null;
            $storeRequisition->contractor_person_name = $data['contractor_person_name'] ?? null;
            $storeRequisition->remarks                = $data['remarks'] ?? null;
            // Keep status as "requested"
            $storeRequisition->save();

            // --- Update lines ---
            $linesById = $storeRequisition->lines->keyBy('id');

            foreach ($data['lines'] as $lineData) {
                $lineId = (int) $lineData['id'];

                if (! isset($linesById[$lineId])) {
                    // Safety: ignore any line that doesn't belong to this requisition
                    continue;
                }

                /** @var StoreRequisitionLine $line */
                $line = $linesById[$lineId];

                $brand = trim((string) ($lineData['preferred_make'] ?? ''));

                $line->required_qty      = $lineData['required_qty'];
                $line->description       = $lineData['description'] ?? null;
                $line->preferred_make    = $brand !== '' ? $brand : null;
                $line->segment_reference = $lineData['segment_reference'] ?? null;
                $line->remarks           = $lineData['remarks'] ?? null;

                $line->save();
            }
        });

        return redirect()
            ->route('store-requisitions.show', $storeRequisition)
            ->with('success', 'Store requisition updated successfully.');
    }

    /**
     * Show a requisition.
     */
    public function show(StoreRequisition $storeRequisition): View
    {
        $storeRequisition->load(['project', 'contractor', 'requestedBy', 'machine', 'lines.item.uom']);

        return view('store_requisitions.show', [
            'requisition' => $storeRequisition,
        ]);
    }

    /**
     * @return string[]
     */
    protected function machineSpareAllowedTypeCodes(): array
    {
        $codes = config('accounting.store.machine_spare_allowed_material_type_codes', ['SPARE', 'CONSUMABLE']);
        if (! is_array($codes)) {
            $codes = [$codes];
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            fn ($code) => strtoupper(trim((string) $code)),
            $codes
        ))));

        return $normalized ?: ['SPARE', 'CONSUMABLE'];
    }

    /**
     * @return string[]
     */
    protected function machineSpareExcludedCategoryCodes(): array
    {
        $codes = config('accounting.store.machine_spare_excluded_material_category_codes', ['FUEL', 'FUELS']);
        if (! is_array($codes)) {
            $codes = [$codes];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($code) => strtoupper(trim((string) $code)),
            $codes
        ))));
    }
}
