<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeSalary;
use App\Models\Hr\HrSalaryComponent;
use App\Models\Hr\HrSalaryStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HrSalaryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:hr.salary.view')->only(['index', 'show', 'employeeSalary', 'salaryHistory']);
        $this->middleware('permission:hr.salary.create')->only(['create', 'store', 'createEmployeeSalary', 'storeEmployeeSalary']);
        $this->middleware('permission:hr.salary.edit')->only(['edit', 'update', 'editEmployeeSalary', 'updateEmployeeSalary']);
    }

    /**
     * List all salary structures
     */
    public function index(Request $request): View
    {
        $query = HrSalaryStructure::withCount('employees');

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
        }

        if ($request->get('status') !== null) {
            $query->where('is_active', $request->get('status'));
        }

        $structures = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('hr.salary.index', compact('structures'));
    }

    /**
     * Show salary structure details
     */
    public function show(HrSalaryStructure $salaryStructure): View
    {
        $salaryStructure->load(['components', 'employees']);

        return view('hr.salary.show', compact('salaryStructure'));
    }

    /**
     * Create new salary structure
     */
    public function create(): View
    {
        $components = HrSalaryComponent::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('hr.salary.create', compact('components'));
    }

    /**
     * Store new salary structure
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:hr_salary_structures,code',
            'description' => 'nullable|string|max:500',
            'effective_from' => 'required|date',
            'is_active' => 'boolean',
            'components' => 'required|array',
            'components.*.id' => 'required|exists:hr_salary_components,id',
            'components.*.calculation_type' => 'required|in:fixed,percentage,formula',
            'components.*.calculation_value' => 'required|numeric',
            'components.*.is_active' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $structure = HrSalaryStructure::create([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $validated['description'] ?? null,
                'effective_from' => $validated['effective_from'],
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['components'] as $component) {
                $structure->components()->attach($component['id'], [
                    'calculation_type' => $component['calculation_type'],
                    'calculation_value' => $component['calculation_value'],
                    'is_active' => $component['is_active'] ?? true,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('hr.salary-structures.show', $structure)
                ->with('success', 'Salary structure created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create salary structure: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Edit salary structure
     */
    public function edit(HrSalaryStructure $salaryStructure): View
    {
        $salaryStructure->load('components');

        $components = HrSalaryComponent::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('hr.salary.edit', compact('salaryStructure', 'components'));
    }

    /**
     * Update salary structure
     */
    public function update(Request $request, HrSalaryStructure $salaryStructure): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:hr_salary_structures,code,' . $salaryStructure->id,
            'description' => 'nullable|string|max:500',
            'effective_from' => 'required|date',
            'is_active' => 'boolean',
            'components' => 'required|array',
            'components.*.id' => 'required|exists:hr_salary_components,id',
            'components.*.calculation_type' => 'required|in:fixed,percentage,formula',
            'components.*.calculation_value' => 'required|numeric',
            'components.*.is_active' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $salaryStructure->update([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $validated['description'] ?? null,
                'effective_from' => $validated['effective_from'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Sync components
            $syncData = [];
            foreach ($validated['components'] as $component) {
                $syncData[$component['id']] = [
                    'calculation_type' => $component['calculation_type'],
                    'calculation_value' => $component['calculation_value'],
                    'is_active' => $component['is_active'] ?? true,
                ];
            }
            $salaryStructure->components()->sync($syncData);

            DB::commit();

            return redirect()
                ->route('hr.salary-structures.show', $salaryStructure)
                ->with('success', 'Salary structure updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update salary structure: ' . $e->getMessage())->withInput();
        }
    }

    // ========================
    // EMPLOYEE SALARY METHODS
    // ========================

    /**
     * Show employee's current salary
     */
    public function employeeSalary(HrEmployee $employee): View
    {
        $currentSalary = $employee->currentSalary;
        $salaryHistory = HrEmployeeSalary::where('hr_employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->get();

        return view('hr.employees.salary.show', compact('employee', 'currentSalary', 'salaryHistory'));
    }

    /**
     * Create employee salary form
     */
    public function createEmployeeSalary(HrEmployee $employee): View
    {
        $structures = HrSalaryStructure::where('is_active', true)->orderBy('name')->get();
        $components = HrSalaryComponent::where('is_active', true)->orderBy('sort_order')->get();

        return view('hr.employees.salary.create', compact('employee', 'structures', 'components'));
    }

    /**
     * Store employee salary
     */
    public function storeEmployeeSalary(Request $request, HrEmployee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'hr_salary_structure_id' => 'nullable|exists:hr_salary_structures,id',
            'effective_from' => 'required|date',
            'basic' => 'required|numeric|min:0',
            'hra' => 'nullable|numeric|min:0',
            'da' => 'nullable|numeric|min:0',
            'special_allowance' => 'nullable|numeric|min:0',
            'conveyance' => 'nullable|numeric|min:0',
            'medical' => 'nullable|numeric|min:0',
            'other_allowances' => 'nullable|numeric|min:0',
            'pf_applicable' => 'boolean',
            'esi_applicable' => 'boolean',
            'pt_applicable' => 'boolean',
            'tds_applicable' => 'boolean',
            'lwf_applicable' => 'boolean',
            'revision_reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Calculate gross
            $gross = ($validated['basic'] ?? 0) + 
                     ($validated['hra'] ?? 0) + 
                     ($validated['da'] ?? 0) + 
                     ($validated['special_allowance'] ?? 0) + 
                     ($validated['conveyance'] ?? 0) + 
                     ($validated['medical'] ?? 0) + 
                     ($validated['other_allowances'] ?? 0);

            // Calculate statutory deductions
            $pfEmployee = $validated['pf_applicable'] ? min($validated['basic'] * 0.12, 1800) : 0;
            $pfEmployer = $validated['pf_applicable'] ? min($validated['basic'] * 0.12, 1800) : 0;

            $esiEmployee = 0;
            $esiEmployer = 0;
            if ($validated['esi_applicable'] && $gross <= 21000) {
                $esiEmployee = $gross * 0.0075;
                $esiEmployer = $gross * 0.0325;
            }

            // PT (simplified - use actual slabs)
            $pt = $validated['pt_applicable'] ? 200 : 0;

            $totalDeductions = $pfEmployee + $esiEmployee + $pt;
            $net = $gross - $totalDeductions;
            $ctc = $gross + $pfEmployer + $esiEmployer;

            // Deactivate current salary
            HrEmployeeSalary::where('hr_employee_id', $employee->id)
                ->where('is_current', true)
                ->update(['is_current' => false, 'effective_to' => $validated['effective_from']]);

            // Create new salary
            $salary = HrEmployeeSalary::create([
                'hr_employee_id' => $employee->id,
                'hr_salary_structure_id' => $validated['hr_salary_structure_id'],
                'effective_from' => $validated['effective_from'],
                'annual_ctc' => $ctc * 12,
                'monthly_ctc' => $ctc,
                'monthly_gross' => $gross,
                'monthly_basic' => $validated['basic'],
                'monthly_net' => $net,
                'is_current' => true,
                'revision_type' => 'manual',
                'increment_percent' => 0,
                'previous_ctc' => 0,
                'remarks' => $validated['revision_reason'],
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()
                ->route('hr.employees.salary.show', $employee)
                ->with('success', 'Employee salary created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create salary: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Edit employee salary
     */
    public function editEmployeeSalary(HrEmployee $employee, HrEmployeeSalary $salary): View
    {
        $structures = HrSalaryStructure::where('is_active', true)->orderBy('name')->get();
        $components = HrSalaryComponent::where('is_active', true)->orderBy('sort_order')->get();

        return view('hr.employees.salary.edit', compact('employee', 'salary', 'structures', 'components'));
    }

    /**
     * Update employee salary
     */
    public function updateEmployeeSalary(Request $request, HrEmployee $employee, HrEmployeeSalary $salary): RedirectResponse
    {
        $validated = $request->validate([
            'hr_salary_structure_id' => 'nullable|exists:hr_salary_structures,id',
            'effective_from' => 'required|date',
            'basic' => 'required|numeric|min:0',
            'hra' => 'nullable|numeric|min:0',
            'da' => 'nullable|numeric|min:0',
            'special_allowance' => 'nullable|numeric|min:0',
            'conveyance' => 'nullable|numeric|min:0',
            'medical' => 'nullable|numeric|min:0',
            'other_allowances' => 'nullable|numeric|min:0',
            'pf_applicable' => 'boolean',
            'esi_applicable' => 'boolean',
            'pt_applicable' => 'boolean',
            'tds_applicable' => 'boolean',
            'lwf_applicable' => 'boolean',
            'revision_reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Calculate gross
            $gross = ($validated['basic'] ?? 0) + 
                     ($validated['hra'] ?? 0) + 
                     ($validated['da'] ?? 0) + 
                     ($validated['special_allowance'] ?? 0) + 
                     ($validated['conveyance'] ?? 0) + 
                     ($validated['medical'] ?? 0) + 
                     ($validated['other_allowances'] ?? 0);

            // Calculate statutory deductions
            $pfEmployee = $validated['pf_applicable'] ? min($validated['basic'] * 0.12, 1800) : 0;
            $pfEmployer = $validated['pf_applicable'] ? min($validated['basic'] * 0.12, 1800) : 0;

            $esiEmployee = 0;
            $esiEmployer = 0;
            if ($validated['esi_applicable'] && $gross <= 21000) {
                $esiEmployee = $gross * 0.0075;
                $esiEmployer = $gross * 0.0325;
            }

            $pt = $validated['pt_applicable'] ? 200 : 0;

            $totalDeductions = $pfEmployee + $esiEmployee + $pt;
            $net = $gross - $totalDeductions;
            $ctc = $gross + $pfEmployer + $esiEmployer;

            $salary->update([
                'hr_salary_structure_id' => $validated['hr_salary_structure_id'],
                'effective_from' => $validated['effective_from'],
                'annual_ctc' => $ctc * 12,
                'monthly_ctc' => $ctc,
                'monthly_gross' => $gross,
                'monthly_basic' => $validated['basic'],
                'monthly_net' => $net,
                'remarks' => $validated['revision_reason'],
            ]);

            DB::commit();

            return redirect()
                ->route('hr.employees.salary.show', $employee)
                ->with('success', 'Salary updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update salary: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Employee salary history
     */
    public function salaryHistory(HrEmployee $employee): View
    {
        $salaryHistory = HrEmployeeSalary::where('hr_employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->paginate(20);

        return view('hr.employees.salary.history', compact('employee', 'salaryHistory'));
    }
}
