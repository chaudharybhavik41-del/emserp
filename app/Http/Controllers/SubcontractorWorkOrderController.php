<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Project;
use App\Models\SubcontractorWorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubcontractorWorkOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:subcontractor_ra.view')->only(['index', 'show', 'lookup']);
        $this->middleware('permission:subcontractor_ra.create')->only(['create', 'store']);
        $this->middleware('permission:subcontractor_ra.update')->only(['edit', 'update']);
        $this->middleware('permission:subcontractor_ra.delete')->only('destroy');
    }

    public function index(Request $request)
    {
        $query = SubcontractorWorkOrder::query()
            ->with(['subcontractor', 'project'])
            ->orderByDesc('work_order_date')
            ->orderByDesc('id');

        if ($request->filled('subcontractor_id')) {
            $query->where('subcontractor_id', (int) $request->input('subcontractor_id'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->input('project_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $workOrders = $query->paginate(20);
        $subcontractors = Party::query()->where('is_contractor', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::query()->orderBy('name')->get();

        return view('subcontractor_work_orders.index', compact('workOrders', 'subcontractors', 'projects'));
    }

    public function create()
    {
        $subcontractors = Party::query()->where('is_contractor', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::query()->orderBy('name')->get();
        $nextWorkOrderNumber = SubcontractorWorkOrder::generateNextNumber();

        return view('subcontractor_work_orders.create', compact('subcontractors', 'projects', 'nextWorkOrderNumber'));
    }

    public function store(Request $request)
    {
        $companyId = (int) config('accounting.default_company_id', 1);
        $validated = $this->validateWorkOrder($request);
        $this->ensureSubcontractor($validated['subcontractor_id']);

        $workOrder = SubcontractorWorkOrder::create([
            'company_id' => $companyId,
            'subcontractor_id' => (int) $validated['subcontractor_id'],
            'project_id' => (int) $validated['project_id'],
            'work_order_number' => $validated['work_order_number'] ?: SubcontractorWorkOrder::generateNextNumber($companyId),
            'work_order_date' => $validated['work_order_date'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'payment_terms_days' => $validated['payment_terms_days'] ?? null,
            'retention_percent' => $validated['retention_percent'] ?? 0,
            'security_deposit_percent' => $validated['security_deposit_percent'] ?? 0,
            'other_terms' => $validated['other_terms'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'status' => $validated['status'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('accounting.subcontractor-work-orders.show', $workOrder)
            ->with('success', 'Subcontractor work order created successfully.');
    }

    public function show(SubcontractorWorkOrder $subcontractorWorkOrder)
    {
        $subcontractorWorkOrder->load([
            'subcontractor',
            'project',
            'creator',
            'updater',
            'raBills' => fn ($query) => $query->with(['subcontractor', 'project'])->orderByDesc('bill_date')->limit(10),
        ]);

        return view('subcontractor_work_orders.show', compact('subcontractorWorkOrder'));
    }

    public function edit(SubcontractorWorkOrder $subcontractorWorkOrder)
    {
        $subcontractors = Party::query()->where('is_contractor', true)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::query()->orderBy('name')->get();

        return view('subcontractor_work_orders.edit', compact('subcontractorWorkOrder', 'subcontractors', 'projects'));
    }

    public function update(Request $request, SubcontractorWorkOrder $subcontractorWorkOrder)
    {
        $validated = $this->validateWorkOrder($request, $subcontractorWorkOrder);
        $this->ensureSubcontractor($validated['subcontractor_id']);

        $subcontractorWorkOrder->fill([
            'subcontractor_id' => (int) $validated['subcontractor_id'],
            'project_id' => (int) $validated['project_id'],
            'work_order_number' => $validated['work_order_number'],
            'work_order_date' => $validated['work_order_date'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'payment_terms_days' => $validated['payment_terms_days'] ?? null,
            'retention_percent' => $validated['retention_percent'] ?? 0,
            'security_deposit_percent' => $validated['security_deposit_percent'] ?? 0,
            'other_terms' => $validated['other_terms'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'status' => $validated['status'],
            'updated_by' => Auth::id(),
        ]);
        $subcontractorWorkOrder->save();

        return redirect()
            ->route('accounting.subcontractor-work-orders.show', $subcontractorWorkOrder)
            ->with('success', 'Subcontractor work order updated successfully.');
    }

    public function destroy(SubcontractorWorkOrder $subcontractorWorkOrder)
    {
        if ($subcontractorWorkOrder->raBills()->exists()) {
            return redirect()
                ->route('accounting.subcontractor-work-orders.show', $subcontractorWorkOrder)
                ->with('error', 'This work order is already used in RA bills and cannot be deleted.');
        }

        $subcontractorWorkOrder->delete();

        return redirect()
            ->route('accounting.subcontractor-work-orders.index')
            ->with('success', 'Subcontractor work order deleted successfully.');
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subcontractor_id' => ['required', 'integer', 'exists:parties,id'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        $rows = SubcontractorWorkOrder::query()
            ->where('company_id', (int) config('accounting.default_company_id', 1))
            ->where('subcontractor_id', (int) $data['subcontractor_id'])
            ->where('project_id', (int) $data['project_id'])
            ->where('status', '<>', 'cancelled')
            ->orderByDesc('work_order_date')
            ->orderBy('work_order_number')
            ->get()
            ->map(fn (SubcontractorWorkOrder $workOrder) => [
                'id' => (int) $workOrder->id,
                'subcontractor_id' => (int) $workOrder->subcontractor_id,
                'project_id' => (int) $workOrder->project_id,
                'work_order_number' => (string) $workOrder->work_order_number,
                'work_order_date' => $workOrder->work_order_date?->format('Y-m-d'),
                'payment_terms_days' => $workOrder->payment_terms_days,
                'retention_percent' => (float) ($workOrder->retention_percent ?? 0),
                'security_deposit_percent' => (float) ($workOrder->security_deposit_percent ?? 0),
                'other_terms' => (string) ($workOrder->other_terms ?? ''),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    protected function validateWorkOrder(Request $request, ?SubcontractorWorkOrder $workOrder = null): array
    {
        $companyId = (int) config('accounting.default_company_id', 1);
        $currentId = $workOrder?->id;

        return $request->validate([
            'subcontractor_id' => ['required', 'integer', 'exists:parties,id'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'work_order_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subcontractor_work_orders', 'work_order_number')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($currentId),
            ],
            'work_order_date' => ['required', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'retention_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'security_deposit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'other_terms' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'active', 'closed', 'cancelled'])],
        ]);
    }

    protected function ensureSubcontractor(int $partyId): void
    {
        $party = Party::query()->findOrFail($partyId);

        if (! $party->is_contractor) {
            throw ValidationException::withMessages([
                'subcontractor_id' => 'Selected party must be a subcontractor / contractor.',
            ]);
        }
    }
}
