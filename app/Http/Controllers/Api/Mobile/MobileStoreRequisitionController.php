<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Machine;
use App\Models\Party;
use App\Models\Project;
use App\Models\StoreRequisition;
use App\Models\StoreStockItem;
use App\Models\Uom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileStoreRequisitionController extends Controller
{
    public function formData(Request $request): JsonResponse
    {
        $this->ensureCreatePermission($request);

        return response()->json([
            'projects' => Project::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'code' => $project->code,
                    'name' => $project->name,
                ])
                ->values(),
            'contractors' => Party::query()
                ->where('is_contractor', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (Party $party): array => [
                    'id' => $party->id,
                    'code' => $party->code,
                    'name' => $party->name,
                ])
                ->values(),
            'machines' => Machine::query()
                ->orderBy('code')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'current_project_id'])
                ->map(fn (Machine $machine): array => [
                    'id' => $machine->id,
                    'code' => $machine->code,
                    'name' => $machine->name,
                    'current_project_id' => $machine->current_project_id,
                ])
                ->values(),
            'uoms' => Uom::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (Uom $uom): array => [
                    'id' => $uom->id,
                    'code' => $uom->code,
                    'name' => $uom->name,
                ])
                ->values(),
            'issue_purposes' => [
                ['id' => 'general', 'label' => 'General'],
                ['id' => 'machine_spare', 'label' => 'Machine Spare'],
            ],
        ]);
    }

    public function itemLookup(Request $request): JsonResponse
    {
        $this->ensureCreatePermission($request);

        $queryText = trim((string) $request->input('q', ''));
        $purpose = trim((string) $request->input('purpose', ''));
        $limit = max(5, min(50, (int) $request->input('limit', 20)));

        $query = Item::query()
            ->with(['uom', 'type'])
            ->where('is_active', true)
            ->whereHas('type', function ($inner): void {
                $inner->where('code', '!=', 'RAW');
            });

        if ($purpose === 'machine_spare') {
            $allowedTypeCodes = $this->machineSpareAllowedTypeCodes();
            $excludedCategoryCodes = $this->machineSpareExcludedCategoryCodes();

            if (! empty($allowedTypeCodes)) {
                $query->whereHas('type', function ($inner) use ($allowedTypeCodes): void {
                    $inner->whereIn('code', $allowedTypeCodes);
                });
            }

            if (! empty($excludedCategoryCodes)) {
                $query->where(function ($inner) use ($excludedCategoryCodes): void {
                    $inner->whereNull('material_category_id')
                        ->orWhereDoesntHave('category', function ($categoryQuery) use ($excludedCategoryCodes): void {
                            $categoryQuery->whereIn('code', $excludedCategoryCodes);
                        });
                });
            }
        }

        if ($queryText !== '') {
            $query->where(function ($inner) use ($queryText): void {
                $inner->where('code', 'like', '%' . $queryText . '%')
                    ->orWhere('name', 'like', '%' . $queryText . '%')
                    ->orWhere('short_name', 'like', '%' . $queryText . '%');
            });
        }

        $items = $query
            ->orderBy('code')
            ->limit($limit)
            ->get();

        return response()->json([
            'results' => $items->map(function (Item $item): array {
                $label = trim(($item->code ? $item->code . ' - ' : '') . $item->name);
                if (! empty($item->grade)) {
                    $label .= ' (' . $item->grade . ')';
                }

                return [
                    'id' => $item->id,
                    'text' => $label,
                    'code' => $item->code,
                    'name' => $item->name,
                    'grade' => $item->grade,
                    'uom_id' => $item->uom_id,
                    'uom_name' => optional($item->uom)->name,
                ];
            })->values(),
        ]);
    }

    public function availableBrands(Request $request): JsonResponse
    {
        $this->ensureCreatePermission($request);

        $itemId = (int) $request->input('item_id');
        $projectId = $request->integer('project_id') ?: null;

        if ($itemId <= 0) {
            return response()->json(['brands' => []]);
        }

        $query = StoreStockItem::query()
            ->where('item_id', $itemId)
            ->where('status', 'available')
            ->whereNotIn('material_category', ['steel_plate', 'steel_section'])
            ->where(function ($inner): void {
                $inner->where('qty_pcs_available', '>', 0)
                    ->orWhere('weight_kg_available', '>', 0);
            });

        if ($projectId) {
            $query->where(function ($scope) use ($projectId): void {
                $scope->where(function ($clientScope) use ($projectId): void {
                    $clientScope->where('is_client_material', true)
                        ->where('project_id', $projectId);
                })->orWhere(function ($ownScope) use ($projectId): void {
                    $ownScope->where('is_client_material', false)
                        ->where(function ($projectScope) use ($projectId): void {
                            $projectScope->whereNull('project_id')
                                ->orWhere('project_id', $projectId);
                        });
                });
            });
        }

        $brands = collect($query->select('brand')->distinct()->pluck('brand')->all())
            ->map(fn ($brand) => is_string($brand) ? trim($brand) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        natcasesort($brands);

        return response()->json([
            'brands' => array_values($brands),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureViewPermission($request);

        $perPage = max(1, min(50, (int) $request->integer('per_page', 15)));

        $query = StoreRequisition::query()
            ->with(['project', 'contractor', 'requestedBy', 'machine', 'lines.item.uom'])
            ->orderByDesc('requisition_date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('issue_purpose')) {
            $query->where('issue_purpose', (string) $request->input('issue_purpose'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->input('project_id'));
        }

        $requisitions = $query->paginate($perPage);

        return response()->json([
            'data' => $requisitions->getCollection()
                ->map(fn (StoreRequisition $requisition): array => $this->serializeRequisition($requisition, false))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $requisitions->currentPage(),
                'last_page' => $requisitions->lastPage(),
                'per_page' => $requisitions->perPage(),
                'total' => $requisitions->total(),
            ],
        ]);
    }

    public function show(Request $request, StoreRequisition $storeRequisition): JsonResponse
    {
        $this->ensureViewPermission($request);

        $storeRequisition->load(['project', 'contractor', 'requestedBy', 'machine', 'lines.item.uom']);

        return response()->json([
            'data' => $this->serializeRequisition($storeRequisition, true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureCreatePermission($request);

        $data = $request->validate([
            'requisition_date' => ['required', 'date'],
            'issue_purpose' => ['required', 'string', 'in:general,machine_spare'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'contractor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'contractor_person_name' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'lines.*.required_qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.preferred_make' => ['nullable', 'string', 'max:100'],
            'lines.*.segment_reference' => ['nullable', 'string', 'max:100'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $issuePurpose = (string) ($data['issue_purpose'] ?? 'general');

        if ($issuePurpose === 'machine_spare' && empty($data['machine_id'])) {
            throw ValidationException::withMessages([
                'machine_id' => 'Machine is required for machine spare requisition.',
            ]);
        }

        if ($issuePurpose === 'general' && empty($data['project_id'])) {
            throw ValidationException::withMessages([
                'project_id' => 'Project is required for general requisition.',
            ]);
        }

        if ($issuePurpose === 'machine_spare' && empty($data['project_id']) && ! empty($data['machine_id'])) {
            $data['project_id'] = Machine::query()->whereKey((int) $data['machine_id'])->value('current_project_id');
        }

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
                    throw ValidationException::withMessages([
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
                        throw ValidationException::withMessages([
                            'general' => 'Selected item is not allowed for machine spare requisition.',
                        ]);
                    }

                    if ($categoryCode !== '' && in_array($categoryCode, $excludedCategoryCodes, true)) {
                        throw ValidationException::withMessages([
                            'general' => 'Selected item category is not allowed in machine spare requisition.',
                        ]);
                    }
                }
            }
        }

        $requisition = DB::transaction(function () use ($data, $issuePurpose, $request): StoreRequisition {
            $requisition = new StoreRequisition();
            $requisition->requisition_date = $data['requisition_date'];
            $requisition->issue_purpose = $issuePurpose;
            $requisition->project_id = $data['project_id'] ?? null;
            $requisition->machine_id = $issuePurpose === 'machine_spare'
                ? ((int) ($data['machine_id'] ?? 0) ?: null)
                : null;
            $requisition->contractor_party_id = $data['contractor_party_id'] ?? null;
            $requisition->contractor_person_name = $data['contractor_person_name'] ?? null;
            $requisition->requested_by_user_id = $request->user()?->id;
            $requisition->status = 'requested';
            $requisition->remarks = $data['remarks'] ?? null;
            $requisition->save();

            $requisition->requisition_number = app(\App\Services\DocumentNumberService::class)
                ->storeRequisition($requisition);
            $requisition->save();

            foreach ($data['lines'] as $lineData) {
                $brand = trim((string) ($lineData['preferred_make'] ?? ''));

                $requisition->lines()->create([
                    'item_id' => $lineData['item_id'],
                    'uom_id' => $lineData['uom_id'],
                    'description' => $lineData['description'] ?? null,
                    'required_qty' => $lineData['required_qty'],
                    'issued_qty' => 0,
                    'preferred_make' => $brand !== '' ? $brand : null,
                    'segment_reference' => $lineData['segment_reference'] ?? null,
                    'remarks' => $lineData['remarks'] ?? null,
                ]);
            }

            return $requisition;
        });

        $requisition->load(['project', 'contractor', 'requestedBy', 'machine', 'lines.item.uom']);

        return response()->json([
            'message' => 'Store requisition created successfully.',
            'data' => $this->serializeRequisition($requisition, true),
        ], 201);
    }

    private function serializeRequisition(StoreRequisition $requisition, bool $includeLines): array
    {
        $data = [
            'id' => $requisition->id,
            'requisition_number' => $requisition->requisition_number,
            'requisition_date' => optional($requisition->requisition_date)->toDateString(),
            'issue_purpose' => $requisition->issue_purpose,
            'status' => $requisition->status,
            'remarks' => $requisition->remarks,
            'is_fully_issued' => $requisition->isFullyIssued(),
            'project' => $requisition->project ? [
                'id' => $requisition->project->id,
                'code' => $requisition->project->code,
                'name' => $requisition->project->name,
            ] : null,
            'contractor' => $requisition->contractor ? [
                'id' => $requisition->contractor->id,
                'code' => $requisition->contractor->code,
                'name' => $requisition->contractor->name,
            ] : null,
            'machine' => $requisition->machine ? [
                'id' => $requisition->machine->id,
                'code' => $requisition->machine->code,
                'name' => $requisition->machine->name,
            ] : null,
            'requested_by' => $requisition->requestedBy ? [
                'id' => $requisition->requestedBy->id,
                'name' => $requisition->requestedBy->name,
                'email' => $requisition->requestedBy->email,
            ] : null,
        ];

        if ($includeLines) {
            $data['lines'] = $requisition->lines->map(function ($line): array {
                return [
                    'id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item?->code,
                    'item_name' => $line->item?->name,
                    'uom_id' => $line->uom_id,
                    'uom_name' => $line->uom?->name,
                    'required_qty' => (float) $line->required_qty,
                    'issued_qty' => (float) $line->issued_qty,
                    'description' => $line->description,
                    'preferred_make' => $line->preferred_make,
                    'segment_reference' => $line->segment_reference,
                    'remarks' => $line->remarks,
                ];
            })->values()->all();
        }

        return $data;
    }

    private function ensureViewPermission(Request $request): void
    {
        abort_unless($request->user()?->can('store.requisition.view'), 403);
    }

    private function ensureCreatePermission(Request $request): void
    {
        abort_unless($request->user()?->can('store.requisition.create'), 403);
    }

    /**
     * @return string[]
     */
    private function machineSpareAllowedTypeCodes(): array
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
    private function machineSpareExcludedCategoryCodes(): array
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
