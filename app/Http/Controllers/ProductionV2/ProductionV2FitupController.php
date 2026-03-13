<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionAssemblyPartRequirement;
use App\Models\ProductionV2\ProductionFitup;
use App\Models\ProductionV2\ProductionFitupConsumption;
use App\Models\ProductionV2\ProductionInspectionEvent;
use App\Models\ProductionV2\ProductionWeldingEvent;
use App\Models\ProductionV2\ProductionWipItem;
use App\Support\ProductionV2\DailyDprManager;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ProductionV2FitupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view')->only(['index', 'show']);
        $this->middleware('permission:production.dpr.create')->only(['create', 'store']);
    }

    public function index(Project $project)
    {
        $rows = ProductionFitup::query()
            ->where('project_id', $project->id)
            ->with(['assembly', 'contractor', 'supervisor'])
            ->withCount('consumptions')
            ->orderByDesc('id')
            ->paginate(25);

        return view('production_v2.fitups.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Request $request, Project $project)
    {
        $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $request->integer('dpr_id'), 'fitup');
        $selectedAssemblyId = (int) $request->integer('assembly_id');
        $assemblies = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->orderBy('assembly_code')
            ->get();

        $selectedAssembly = $selectedAssemblyId > 0
            ? ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->where('status', 'released')
                ->with(['requirements.partDefinition.uom'])
                ->find($selectedAssemblyId)
            : null;

        $availableWip = collect();
        $requirementRows = collect();
        if ($selectedAssembly) {
            $partIds = $selectedAssembly->requirements->pluck('part_definition_id')->all();
            $availableWip = ProductionWipItem::query()
                ->where('project_id', $project->id)
                ->whereIn('part_definition_id', $partIds)
                ->where('status', 'available')
                ->where(function ($query) use ($selectedAssembly) {
                    $query->whereNull('reserved_for_assembly_id')
                        ->orWhere('reserved_for_assembly_id', $selectedAssembly->id);
                })
                ->with(['partDefinition', 'uom', 'motherStock'])
                ->orderBy('part_definition_id')
                ->orderBy('piece_no')
                ->orderBy('lot_no')
                ->get()
                ->groupBy('part_definition_id');

            $requirementRows = $selectedAssembly->requirements
                ->sortBy([
                    ['consumption_sequence', 'asc'],
                    ['id', 'asc'],
                ])
                ->values()
                ->map(function (ProductionAssemblyPartRequirement $requirement, int $index) use ($availableWip, $request) {
                    $wipRows = $availableWip->get($requirement->part_definition_id, collect())->values();
                    $availableQty = (float) $wipRows->sum(fn ($row) => (float) $row->qty);
                    $defaultWipId = old('rows.' . $index . '.wip_item_id');
                    if ($defaultWipId === null && $wipRows->count() === 1) {
                        $defaultWipId = (string) $wipRows->first()->id;
                    }

                    return [
                        'requirement' => $requirement,
                        'wip_rows' => $wipRows,
                        'available_qty' => $availableQty,
                        'default_wip_id' => $defaultWipId,
                        'default_consumed_qty' => old(
                            'rows.' . $index . '.consumed_qty',
                            min((float) $requirement->required_qty, $availableQty > 0 ? $availableQty : (float) $requirement->required_qty)
                        ),
                        'default_specified_dimension_text' => old(
                            'rows.' . $index . '.specified_dimension_text',
                            $this->specifiedDimensionText($requirement)
                        ),
                        'default_observed_dimension_text' => old('rows.' . $index . '.observed_dimension_text'),
                        'default_dimension_ok' => old('rows.' . $index . '.dimension_ok'),
                        'default_remarks' => old('rows.' . $index . '.remarks'),
                        'has_shortage' => $availableQty + 0.0001 < (float) $requirement->required_qty,
                    ];
                });
        }

        return view('production_v2.fitups.form', [
            'project' => $project,
            'assemblies' => $assemblies,
            'selectedAssembly' => $selectedAssembly,
            'availableWip' => $availableWip,
            'requirementRows' => $requirementRows,
            'contractors' => Party::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->limit(300)->get(['id', 'name']),
            'selectedDpr' => $selectedDpr,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'dpr_id' => ['nullable', 'integer', 'exists:production_dprs,id'],
            'assembly_id' => [
                'required',
                'integer',
                Rule::exists('production_v2_assemblies', 'id')->where(fn ($query) => $query->where('project_id', $project->id)->where('status', 'released')),
            ],
            'fitup_date' => ['required', 'date'],
            'shift' => ['nullable', 'string', 'max:30'],
            'contractor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'inspector_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(['draft', 'approved'])],
            'remarks' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.part_definition_id' => ['required', 'integer', 'exists:production_v2_part_definitions,id'],
            'rows.*.wip_item_id' => ['nullable', 'integer', 'exists:production_v2_wip_items,id'],
            'rows.*.consumed_qty' => ['nullable', 'numeric', 'min:0.001'],
            'rows.*.observed_dimension_text' => ['nullable', 'string', 'max:200'],
            'rows.*.specified_dimension_text' => ['nullable', 'string', 'max:200'],
            'rows.*.dimension_ok' => ['nullable', 'boolean'],
            'rows.*.remarks' => ['nullable', 'string'],
        ]);

        $selectedDpr = null;
        if (! empty($data['dpr_id'])) {
            $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $data['dpr_id'], 'fitup');
            if (! $selectedDpr) {
                return back()->withInput()->withErrors(['dpr_id' => 'Selected DPR is not valid for Production V2 fit-up.']);
            }
        }

        $assembly = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->with('requirements')
            ->findOrFail((int) $data['assembly_id']);

        $requirementsByPartId = $assembly->requirements->keyBy('part_definition_id');
        $rows = collect($data['rows'])
            ->values()
            ->filter(function (array $row) {
                return ! empty($row['wip_item_id']) || ! empty($row['consumed_qty']) || ! empty($row['observed_dimension_text']) || ! empty($row['specified_dimension_text']) || array_key_exists('dimension_ok', $row);
            })
            ->all();

        $errors = [];
        $selectedWipIds = [];
        foreach ($data['rows'] as $index => $row) {
            $partDefinitionId = (int) ($row['part_definition_id'] ?? 0);
            $requirement = $requirementsByPartId->get($partDefinitionId);
            if (! $requirement) {
                $errors["rows.$index.part_definition_id"] = 'Selected row part does not belong to this assembly.';
                continue;
            }

            $wipItemId = (int) ($row['wip_item_id'] ?? 0);
            $consumedQty = isset($row['consumed_qty']) && $row['consumed_qty'] !== '' ? (float) $row['consumed_qty'] : null;

            if ($requirement->is_mandatory && $wipItemId <= 0) {
                $errors["rows.$index.wip_item_id"] = 'This required part must be linked to available WIP.';
            }

            if ($wipItemId > 0) {
                if (in_array($wipItemId, $selectedWipIds, true)) {
                    $errors["rows.$index.wip_item_id"] = 'The same WIP item cannot be consumed twice in one fit-up.';
                } else {
                    $selectedWipIds[] = $wipItemId;
                }
            }

            if ($wipItemId <= 0 && $consumedQty !== null) {
                $errors["rows.$index.wip_item_id"] = 'Select a WIP item before entering consumed quantity.';
            }
        }

        if (empty($rows)) {
            $errors['rows'] = 'Add at least one WIP consumption row for this fit-up.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $fitup = DB::transaction(function () use ($project, $data, $assembly, $rows, $requirementsByPartId, $selectedDpr) {
            $fitup = ProductionFitup::query()->create([
                'project_id' => $project->id,
                'assembly_id' => $assembly->id,
                'dpr_id' => $selectedDpr?->id,
                'fitup_date' => $data['fitup_date'],
                'shift' => $data['shift'] ?? $selectedDpr?->shift,
                'contractor_party_id' => $data['contractor_party_id'] ?? $selectedDpr?->contractor_party_id,
                'supervisor_id' => $data['supervisor_id'] ?? $selectedDpr?->worker_user_id,
                'inspector_id' => $data['inspector_id'] ?? null,
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($rows as $row) {
                $partDefinitionId = (int) $row['part_definition_id'];
                /** @var \App\Models\ProductionV2\ProductionAssemblyPartRequirement $requirement */
                $requirement = $requirementsByPartId->get($partDefinitionId);
                $wip = ProductionWipItem::query()
                    ->where('project_id', $project->id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->findOrFail((int) $row['wip_item_id']);

                if ((int) $wip->part_definition_id !== $partDefinitionId) {
                    throw ValidationException::withMessages([
                        'rows' => ['Selected WIP does not match the required assembly part.'],
                    ]);
                }

                if (! $wip->is_interchangeable && $wip->reserved_for_assembly_id !== null && (int) $wip->reserved_for_assembly_id !== (int) $assembly->id) {
                    throw ValidationException::withMessages([
                        'rows' => ['Selected WIP is reserved for another assembly.'],
                    ]);
                }

                $consumeQty = (float) ($row['consumed_qty'] ?? 0);
                $currentQty = (float) $wip->qty;

                if ($consumeQty <= 0) {
                    throw ValidationException::withMessages([
                        'rows' => ['Consumed quantity must be greater than zero for selected WIP rows.'],
                    ]);
                }

                if ($consumeQty > $currentQty) {
                    throw new \RuntimeException('Consumed qty cannot exceed available WIP qty.');
                }

                if ($consumeQty < $currentQty) {
                    $remaining = $currentQty - $consumeQty;
                    ProductionWipItem::query()->create([
                        'project_id' => $wip->project_id,
                        'part_definition_id' => $wip->part_definition_id,
                        'cut_batch_id' => $wip->cut_batch_id,
                        'piece_no' => $wip->piece_no ? ($wip->piece_no . '-BAL') : null,
                        'lot_no' => $wip->lot_no ? ($wip->lot_no . '-BAL') : ProductionWipItem::generateReference($project->code, 'LOT'),
                        'qty' => $remaining,
                        'uom_id' => $wip->uom_id,
                        'thickness_mm' => $wip->thickness_mm,
                        'width_mm' => $wip->width_mm,
                        'length_mm' => $wip->length_mm,
                        'weight_kg' => $wip->weight_kg && $currentQty > 0 ? ((float) $wip->weight_kg * ($remaining / $currentQty)) : null,
                        'mother_stock_item_id' => $wip->mother_stock_item_id,
                        'plate_number' => $wip->plate_number,
                        'heat_number' => $wip->heat_number,
                        'mtc_number' => $wip->mtc_number,
                        'is_interchangeable' => $wip->is_interchangeable,
                        'reserved_for_assembly_id' => $wip->is_interchangeable ? null : $assembly->id,
                        'status' => 'available',
                        'remarks' => 'Balance created during fit-up consumption from WIP #' . $wip->id,
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);

                    $wip->qty = $consumeQty;
                    if ($wip->weight_kg && $currentQty > 0) {
                        $wip->weight_kg = (float) $wip->weight_kg * ($consumeQty / $currentQty);
                    }
                }

                $wip->status = 'consumed';
                $wip->reserved_for_assembly_id = $assembly->id;
                $wip->updated_by = auth()->id();
                $wip->save();

                ProductionFitupConsumption::query()->create([
                    'fitup_id' => $fitup->id,
                    'assembly_id' => $assembly->id,
                    'wip_item_id' => $wip->id,
                    'consumed_qty' => $consumeQty,
                    'part_definition_id' => $partDefinitionId,
                    'uom_id' => $requirement->uom_id ?: $wip->uom_id,
                    'observed_dimension_text' => $row['observed_dimension_text'] ?? null,
                    'specified_dimension_text' => $row['specified_dimension_text'] ?? null,
                    'dimension_ok' => array_key_exists('dimension_ok', $row) ? (bool) $row['dimension_ok'] : null,
                    'plate_number_snapshot' => $wip->plate_number,
                    'heat_number_snapshot' => $wip->heat_number,
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }

            return $fitup;
        });

        return redirect()
            ->route('projects.production-v2.fitups.show', ['project' => $project->id, 'fitup' => $fitup->id])
            ->with('success', 'Production V2 fit-up created and WIP consumed.');
    }

    public function show(Project $project, ProductionFitup $fitup)
    {
        abort_unless((int) $fitup->project_id === (int) $project->id, 404);
        return view('production_v2.fitups.show', $this->showData($project, $fitup));
    }

    public function print(Project $project, ProductionFitup $fitup)
    {
        abort_unless((int) $fitup->project_id === (int) $project->id, 404);

        return view('production_v2.fitups.print', $this->showData($project, $fitup));
    }

    protected function specifiedDimensionText(ProductionAssemblyPartRequirement $requirement): ?string
    {
        $part = $requirement->partDefinition;
        if (! $part) {
            return null;
        }

        if ($part->is_section_item && $part->length_mm) {
            return 'L ' . $this->formatNumber($part->length_mm) . ' mm';
        }

        if ($part->thickness_mm && $part->width_mm && $part->length_mm) {
            return 'T ' . $this->formatNumber($part->thickness_mm)
                . ' x W ' . $this->formatNumber($part->width_mm)
                . ' x L ' . $this->formatNumber($part->length_mm) . ' mm';
        }

        if ($part->width_mm && $part->length_mm) {
            return 'W ' . $this->formatNumber($part->width_mm)
                . ' x L ' . $this->formatNumber($part->length_mm) . ' mm';
        }

        if ($part->length_mm) {
            return 'L ' . $this->formatNumber($part->length_mm) . ' mm';
        }

        return $part->drawing_ref ?: null;
    }

    protected function formatNumber(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    protected function showData(Project $project, ProductionFitup $fitup): array
    {
        $fitup->load(['assembly', 'contractor', 'supervisor', 'inspector', 'consumptions.partDefinition', 'consumptions.uom', 'consumptions.wipItem.motherStock']);

        $latestWeldingEvent = ProductionWeldingEvent::query()
            ->where('project_id', $project->id)
            ->where('assembly_id', $fitup->assembly_id)
            ->latest('weld_date')
            ->latest('id')
            ->first();

        $latestInspectionEvent = ProductionInspectionEvent::query()
            ->where('project_id', $project->id)
            ->where('assembly_id', $fitup->assembly_id)
            ->latest('inspection_date')
            ->latest('id')
            ->first();

        return [
            'project' => $project,
            'fitup' => $fitup,
            'latestWeldingEvent' => $latestWeldingEvent,
            'latestInspectionEvent' => $latestInspectionEvent,
        ];
    }
}
