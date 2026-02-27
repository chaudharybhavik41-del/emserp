<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMachineRequest;
use App\Http\Requests\UpdateMachineRequest;
use App\Models\Machine;
use App\Models\MaterialType;
use App\Models\MaterialCategory;
use App\Models\MaterialSubcategory;
use App\Models\Party;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MachineController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:machinery.machine.view')->only(['index', 'show']);
        $this->middleware('permission:machinery.machine.create')->only(['create', 'store']);
        $this->middleware('permission:machinery.machine.update')->only(['edit', 'update']);
        $this->middleware('permission:machinery.machine.delete')->only('destroy');
    }

    public function index(Request $request)
    {
        $query = Machine::with([
            'category',
            'subcategory',
            'department',
            'currentContractor',
            'currentWorker',
            'currentProject'
        ]);

        // Search
        if ($search = trim($request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('serial_number', 'like', '%' . $search . '%');
            });
        }

        // Filter by category
        if ($categoryId = $request->get('category_id')) {
            $query->where('material_category_id', $categoryId);
        }

        // Filter by status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filter by assignment
        if ($assignment = $request->get('assignment')) {
            if ($assignment === 'available') {
                $query->where('is_issued', false)->where('status', 'active');
            } elseif ($assignment === 'issued') {
                $query->where('is_issued', true);
            }
        }

        // Filter by active/inactive
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $machines = $query->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        // Get MACHINERY type categories for filter
        $machineryType = MaterialType::where('code', 'MACHINERY')->first();
        $categories = $machineryType 
            ? MaterialCategory::where('material_type_id', $machineryType->id)->orderBy('sort_order')->get()
            : collect();

        $statuses = ['active', 'under_maintenance', 'breakdown', 'retired', 'disposed'];

        return view('machines.index', compact('machines', 'categories', 'statuses'));
    }

    public function create()
    {
        $machine = new Machine();

        // Get MACHINERY type and its categories
        $machineryType = MaterialType::where('code', 'MACHINERY')->firstOrFail();
        $categories = MaterialCategory::where('material_type_id', $machineryType->id)
            ->orderBy('sort_order')
            ->get();

        $subcategories = MaterialSubcategory::whereIn('material_category_id', $categories->pluck('id'))
            ->orderBy('code')
            ->get();

        $suppliers = Party::where('is_supplier', true)->orderBy('name')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return view('machines.create', compact(
            'machine',
            'machineryType',
            'categories',
            'subcategories',
            'suppliers',
            'departments'
        ));
    }

    public function store(StoreMachineRequest $request)
    {
        $data = $request->validated();

        // Get MACHINERY type ID
        $machineryType = MaterialType::where('code', 'MACHINERY')->firstOrFail();
        $data['material_type_id'] = $machineryType->id;

        // Auto-generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = Machine::generateCode($data['material_category_id']);
        }

        // Set defaults
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_issued'] = false;
        $data['current_assignment_type'] = 'unassigned';
        $data['created_by'] = Auth::id();

        // Calculate warranty expiry if warranty months provided
        if (!empty($data['purchase_date']) && !empty($data['warranty_months'])) {
            $data['warranty_expiry_date'] = now()
                ->parse($data['purchase_date'])
                ->addMonths((int) $data['warranty_months'])
                ->format('Y-m-d');
        }

        // Calculate next maintenance due date
        if (!empty($data['maintenance_frequency_days'])) {
            $data['next_maintenance_due_date'] = now()
                ->addDays((int) $data['maintenance_frequency_days'])
                ->format('Y-m-d');
        }

        $machine = Machine::create($data);

        ActivityLog::logCreated($machine, "Created machine: {$machine->code} - {$machine->name}");

        return redirect()
            ->route('machines.show', $machine)
            ->with('success', 'Machine created successfully.');
    }

    public function show(Machine $machine)
    {
        $machine->load([
            'category',
            'subcategory',
            'supplier',
            'department',
            'currentContractor',
            'currentWorker',
            'currentProject',
            'creator',
            'updater'
        ]);

        return view('machines.show', compact('machine'));
    }

    public function edit(Machine $machine)
	{
    $machineryType = MaterialType::where('code', 'MACHINERY')->firstOrFail();
    
    $categories = MaterialCategory::where('material_type_id', $machineryType->id)
        ->orderBy('name')
        ->get();
    
    $subcategories = MaterialSubcategory::whereIn(
        'material_category_id',
        $categories->pluck('id')
    )->orderBy('name')->get();
    
    $suppliers = Party::where('is_supplier', true)->orderBy('name')->get();
    $departments = Department::orderBy('name')->get();
    
    return view('machines.edit', compact(
        'machine',
        'categories',
        'subcategories',
        'suppliers',
        'departments'
    ));
	}


    public function update(UpdateMachineRequest $request, Machine $machine)
    {
        $data = $request->validated();
        $oldValues = $machine->toArray();

        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = Auth::id();

        // Recalculate warranty expiry if changed
        if (isset($data['purchase_date']) && isset($data['warranty_months'])) {
            $data['warranty_expiry_date'] = now()
                ->parse($data['purchase_date'])
                ->addMonths((int) $data['warranty_months'])
                ->format('Y-m-d');
        }

        $machine->update($data);

        ActivityLog::logUpdated($machine, $oldValues, "Updated machine: {$machine->code} - {$machine->name}");

        return redirect()
            ->route('machines.show', $machine)
            ->with('success', 'Machine updated successfully.');
    }

    public function destroy(Machine $machine)
    {
        // Guard: Don't allow delete if machine is issued
        if ($machine->is_issued) {
            return redirect()
                ->route('machines.index')
                ->with('error', 'Cannot delete machine that is currently issued. Please return it first.');
        }

        $machine->delete();

        return redirect()
            ->route('machines.index')
            ->with('success', 'Machine deleted successfully.');
    }
}