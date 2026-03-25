<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Hr\Concerns\AuthorizesEmployeeWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeSalary;
use App\Models\Hr\HrSalaryComponent;
use App\Models\Hr\HrSalaryStructure;
use App\Models\Hr\HrEmployeeSalaryComponent;
use App\Models\Hr\HrLwfSlab;
use App\Models\Hr\HrProfessionalTaxSlab;
use App\Models\Hr\HrPfSlab;
use App\Models\Hr\HrTaxDeclaration;
use App\Models\Hr\HrTdsSlab;
use App\Services\Hr\PayrollPeriodStalenessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HrSalaryController extends Controller
{
    use AuthorizesEmployeeWorkspace;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:hr.employee.update')->only([
            'createEmployeeSalary',
            'storeEmployeeSalary',
            'editEmployeeSalary',
            'updateEmployeeSalary',
        ]);
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
        $this->authorizeEmployeeRead($employee);

        $currentSalary = $employee->currentSalary()
            ->with([
                'components.salaryComponent',
                'salaryStructure.components',
            ])
            ->first();

        $salaryHistory = HrEmployeeSalary::where('hr_employee_id', $employee->id)
            ->with('salaryStructure')
            ->orderByDesc('effective_from')
            ->get();

        return view('hr.employees.salary.show', compact('employee', 'currentSalary', 'salaryHistory'));
    }

    /**
     * Create employee salary form
     */
    public function createEmployeeSalary(HrEmployee $employee): View
    {
        $this->authorizeEmployeeWrite($employee);

        $structures = HrSalaryStructure::where('is_active', true)
            ->with('components')
            ->orderBy('name')
            ->get();
        $components = HrSalaryComponent::where('is_active', true)->orderBy('sort_order')->get();
        $ptSlabPayload = HrProfessionalTaxSlab::query()
            ->where('is_active', true)
            ->orderBy('state_name')
            ->orderBy('salary_from')
            ->get()
            ->map(fn(HrProfessionalTaxSlab $slab) => [
                'state_code' => $slab->state_code,
                'state_name' => $slab->state_name,
                'salary_from' => (float) $slab->salary_from,
                'salary_to' => (float) $slab->salary_to,
                'tax_amount' => (float) $slab->tax_amount,
                'gender' => $slab->gender,
                'effective_from' => optional($slab->effective_from)->format('Y-m-d'),
                'effective_to' => optional($slab->effective_to)->format('Y-m-d'),
            ])
            ->values();

        return view('hr.employees.salary.create', compact('employee', 'structures', 'components', 'ptSlabPayload'));
    }

    /**
     * Store employee salary
     */
    public function storeEmployeeSalary(Request $request, HrEmployee $employee): RedirectResponse
    {
        $this->authorizeEmployeeWrite($employee);

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
            'structure_components' => 'nullable|array',
            'structure_components.*.hr_salary_component_id' => 'required_with:structure_components|exists:hr_salary_components,id',
            'structure_components.*.applicable' => 'nullable|boolean',
            'structure_components.*.monthly_amount' => 'nullable|numeric|min:0',
            'structure_components.*.calculation_type' => 'nullable|string|max:50',
            'structure_components.*.percentage' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $derivedApplicability = $this->resolveStatutoryApplicability($validated);

            // Calculate gross
            $gross = ($validated['basic'] ?? 0) +
                ($validated['hra'] ?? 0) +
                ($validated['da'] ?? 0) +
                ($validated['special_allowance'] ?? 0) +
                ($validated['conveyance'] ?? 0) +
                ($validated['medical'] ?? 0) +
                ($validated['other_allowances'] ?? 0);

            // Calculate statutory deductions
            $pfApplicable = $derivedApplicability['pf'];
            $esiApplicable = $derivedApplicability['esi'];
            $ptApplicable = $derivedApplicability['pt'];
            $tdsApplicable = $derivedApplicability['tds'];
            $lwfApplicable = $derivedApplicability['lwf'];

            $pfBreakdown = $this->calculatePfBreakdown((float) $validated['basic'], (string) $validated['effective_from']);
            $pfEmployee = $pfApplicable ? $pfBreakdown['employee'] : 0;
            $pfEmployer = $pfApplicable ? $pfBreakdown['employer_pf'] : 0;

            $esiEmployee = 0;
            $esiEmployer = 0;
            if ($esiApplicable && $gross <= 21000) {
                $esiEmployee = $gross * 0.0075;
                $esiEmployer = $gross * 0.0325;
            }

            $pt = $ptApplicable
                ? $this->calculateProfessionalTaxForEmployee($employee, $gross, (string) $validated['effective_from'])
                : 0;
            $tds = $tdsApplicable
                ? $this->calculateMonthlyTdsForEmployee($employee, $gross, (string) $validated['effective_from'])
                : 0;
            $lwf = $lwfApplicable
                ? $this->calculateLwfForEmployee($employee, (string) $validated['effective_from'])
                : ['employee' => 0.0, 'employer' => 0.0];

            $this->assertSalaryEffectiveFromDoesNotOverlap($employee, (string) $validated['effective_from']);

            $totalDeductions = $pfEmployee + $esiEmployee + $pt + $tds + $lwf['employee'];
            $net = $gross - $totalDeductions;
            $ctc = $gross + $pfBreakdown['employer_total'] + $esiEmployer + $lwf['employer'];

            // Deactivate current salary
            HrEmployeeSalary::where('hr_employee_id', $employee->id)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'effective_to' => Carbon::parse($validated['effective_from'])->subDay()->toDateString(),
                ]);

            // Create new salary
            $salaryStructureId = $this->resolveSalaryStructureId($validated['hr_salary_structure_id'] ?? null);

            $salary = HrEmployeeSalary::create([
                'hr_employee_id' => $employee->id,
                'hr_salary_structure_id' => $salaryStructureId,
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
                'remarks' => $validated['revision_reason'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->syncSalaryBreakdownComponents($salary, $validated, $pfEmployee, $esiEmployee, $pt, $tds, $lwf['employee'], $pfEmployer, $esiEmployer, $lwf['employer']);
            app(PayrollPeriodStalenessService::class)
                ->markPeriodsFromDate($validated['effective_from'], "Salary revised for {$employee->employee_code}.");

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
        $this->authorizeEmployeeWrite($employee);

        $structures = HrSalaryStructure::where('is_active', true)
            ->with('components')
            ->orderBy('name')
            ->get();
        $components = HrSalaryComponent::where('is_active', true)->orderBy('sort_order')->get();
        $ptSlabPayload = HrProfessionalTaxSlab::query()
            ->where('is_active', true)
            ->orderBy('state_name')
            ->orderBy('salary_from')
            ->get()
            ->map(fn(HrProfessionalTaxSlab $slab) => [
                'state_code' => $slab->state_code,
                'state_name' => $slab->state_name,
                'salary_from' => (float) $slab->salary_from,
                'salary_to' => (float) $slab->salary_to,
                'tax_amount' => (float) $slab->tax_amount,
                'gender' => $slab->gender,
                'effective_from' => optional($slab->effective_from)->format('Y-m-d'),
                'effective_to' => optional($slab->effective_to)->format('Y-m-d'),
            ])
            ->values();

        return view('hr.employees.salary.edit', compact('employee', 'salary', 'structures', 'components', 'ptSlabPayload'));
    }

    /**
     * Update employee salary
     */
    public function updateEmployeeSalary(Request $request, HrEmployee $employee, HrEmployeeSalary $salary): RedirectResponse
    {
        $this->authorizeEmployeeWrite($employee);

        if ($salary->hr_employee_id !== $employee->id) {
            abort(404);
        }

        if ($salary->payrolls()->exists()) {
            return back()->with('error', 'Cannot edit salary that is already linked to payroll. Create a new revision instead.');
        }

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
            'structure_components' => 'nullable|array',
            'structure_components.*.hr_salary_component_id' => 'required_with:structure_components|exists:hr_salary_components,id',
            'structure_components.*.applicable' => 'nullable|boolean',
            'structure_components.*.monthly_amount' => 'nullable|numeric|min:0',
            'structure_components.*.calculation_type' => 'nullable|string|max:50',
            'structure_components.*.percentage' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $derivedApplicability = $this->resolveStatutoryApplicability($validated);

            // Calculate gross
            $gross = ($validated['basic'] ?? 0) +
                ($validated['hra'] ?? 0) +
                ($validated['da'] ?? 0) +
                ($validated['special_allowance'] ?? 0) +
                ($validated['conveyance'] ?? 0) +
                ($validated['medical'] ?? 0) +
                ($validated['other_allowances'] ?? 0);

            // Calculate statutory deductions
            $pfApplicable = $derivedApplicability['pf'];
            $esiApplicable = $derivedApplicability['esi'];
            $ptApplicable = $derivedApplicability['pt'];
            $tdsApplicable = $derivedApplicability['tds'];
            $lwfApplicable = $derivedApplicability['lwf'];

            $pfBreakdown = $this->calculatePfBreakdown((float) $validated['basic'], (string) $validated['effective_from']);
            $pfEmployee = $pfApplicable ? $pfBreakdown['employee'] : 0;
            $pfEmployer = $pfApplicable ? $pfBreakdown['employer_pf'] : 0;

            $esiEmployee = 0;
            $esiEmployer = 0;
            if ($esiApplicable && $gross <= 21000) {
                $esiEmployee = $gross * 0.0075;
                $esiEmployer = $gross * 0.0325;
            }

            $pt = $ptApplicable
                ? $this->calculateProfessionalTaxForEmployee($employee, $gross, (string) $validated['effective_from'])
                : 0;
            $tds = $tdsApplicable
                ? $this->calculateMonthlyTdsForEmployee($employee, $gross, (string) $validated['effective_from'])
                : 0;
            $lwf = $lwfApplicable
                ? $this->calculateLwfForEmployee($employee, (string) $validated['effective_from'])
                : ['employee' => 0.0, 'employer' => 0.0];

            $this->assertSalaryEffectiveFromDoesNotOverlap($employee, (string) $validated['effective_from'], $salary->id);

            $totalDeductions = $pfEmployee + $esiEmployee + $pt + $tds + $lwf['employee'];
            $net = $gross - $totalDeductions;
            $ctc = $gross + ($pfApplicable ? $pfBreakdown['employer_total'] : 0) + $esiEmployer + $lwf['employer'];

            $salaryStructureId = $this->resolveSalaryStructureId($validated['hr_salary_structure_id'] ?? null);

            $salary->update([
                'hr_salary_structure_id' => $salaryStructureId,
                'effective_from' => $validated['effective_from'],
                'annual_ctc' => $ctc * 12,
                'monthly_ctc' => $ctc,
                'monthly_gross' => $gross,
                'monthly_basic' => $validated['basic'],
                'monthly_net' => $net,
                'remarks' => $validated['revision_reason'] ?? null,
            ]);

            $this->syncSalaryBreakdownComponents($salary, $validated, $pfEmployee, $esiEmployee, $pt, $tds, $lwf['employee'], $pfEmployer, $esiEmployer, $lwf['employer']);
            app(PayrollPeriodStalenessService::class)
                ->markPeriodsFromDate($validated['effective_from'], "Salary updated for {$employee->employee_code}.");

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
        $this->authorizeEmployeeRead($employee);

        $salaryHistory = HrEmployeeSalary::where('hr_employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->paginate(20);

        return view('hr.employees.salary.history', compact('employee', 'salaryHistory'));
    }

    private function syncSalaryBreakdownComponents(
        HrEmployeeSalary $salary,
        array $validated,
        float $pfEmployee,
        float $esiEmployee,
        float $pt,
        float $tds,
        float $lwfEmployee,
        float $pfEmployer,
        float $esiEmployer,
        float $lwfEmployer
    ): void {
        $salary->components()->delete();

        $rows = [];
        $selectedStructureComponents = collect($validated['structure_components'] ?? [])
            ->keyBy(fn(array $row) => (int) ($row['hr_salary_component_id'] ?? 0));

        foreach ($selectedStructureComponents as $componentId => $row) {
            if (!(bool) ($row['applicable'] ?? false)) {
                continue;
            }

            $component = HrSalaryComponent::find($componentId);
            if (!$component) {
                continue;
            }

            $amount = (float) ($row['monthly_amount'] ?? 0);

            $rows[$component->id] = [
                'component' => $component,
                'monthly_amount' => $amount,
                'calculation_type' => $this->normalizeEmployeeSalaryComponentCalculationType(
                    (string) ($row['calculation_type'] ?? $component->calculation_type ?? 'fixed')
                ),
                'percentage' => $row['percentage'] !== null ? (float) $row['percentage'] : null,
            ];
        }

        foreach ($this->defaultSalaryComponentDefinitions($validated, $pfEmployee, $esiEmployee, $pt, $tds, $lwfEmployee, $pfEmployer, $esiEmployer, $lwfEmployer) as $category => $definition) {
            $component = $this->resolveOrCreateSalaryComponent($category, $definition);
            $selectedRow = $rows[$component->id] ?? null;
            $isApplicable = $selectedRow ? true : ($definition['amount'] > 0);
            $amount = $this->usesDerivedServerAmount($category)
                ? (float) $definition['amount']
                : ($selectedRow['monthly_amount'] ?? (float) $definition['amount']);

            if (!$isApplicable || $amount <= 0) {
                unset($rows[$component->id]);
                continue;
            }

            $rows[$component->id] = [
                'component' => $component,
                'monthly_amount' => $amount,
                'calculation_type' => $this->normalizeEmployeeSalaryComponentCalculationType($selectedRow['calculation_type'] ?? 'fixed'),
                'percentage' => $selectedRow['percentage'] ?? null,
            ];
        }

        foreach ($rows as $row) {
            $amount = (float) ($row['monthly_amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            HrEmployeeSalaryComponent::create([
                'hr_employee_salary_id' => $salary->id,
                'hr_salary_component_id' => $row['component']->id,
                'monthly_amount' => $amount,
                'annual_amount' => $amount * 12,
                'calculation_type' => $row['calculation_type'] ?? 'fixed',
                'percentage' => $row['percentage'],
            ]);
        }
    }

    private function defaultSalaryComponentDefinitions(
        array $validated,
        float $pfEmployee,
        float $esiEmployee,
        float $pt,
        float $tds,
        float $lwfEmployee,
        float $pfEmployer,
        float $esiEmployer,
        float $lwfEmployer
    ): array {
        return [
            'basic' => ['type' => 'earning', 'name' => 'Basic Salary', 'code' => 'BASIC', 'amount' => (float) ($validated['basic'] ?? 0)],
            'hra' => ['type' => 'earning', 'name' => 'House Rent Allowance', 'code' => 'HRA', 'amount' => (float) ($validated['hra'] ?? 0)],
            'da' => ['type' => 'earning', 'name' => 'Dearness Allowance', 'code' => 'DA', 'amount' => (float) ($validated['da'] ?? 0)],
            'special_allowance' => ['type' => 'earning', 'name' => 'Special Allowance', 'code' => 'SPECIAL', 'amount' => (float) ($validated['special_allowance'] ?? 0)],
            'conveyance' => ['type' => 'earning', 'name' => 'Conveyance', 'code' => 'CONV', 'amount' => (float) ($validated['conveyance'] ?? 0)],
            'medical' => ['type' => 'earning', 'name' => 'Medical Allowance', 'code' => 'MED', 'amount' => (float) ($validated['medical'] ?? 0)],
            'other_earning' => ['type' => 'earning', 'name' => 'Other Allowances', 'code' => 'OTH', 'amount' => (float) ($validated['other_allowances'] ?? 0)],
            'pf_employee' => ['type' => 'deduction', 'name' => 'Provident Fund (Employee)', 'code' => 'PF_EE', 'amount' => $pfEmployee],
            'esi_employee' => ['type' => 'deduction', 'name' => 'ESI (Employee)', 'code' => 'ESI_EE', 'amount' => $esiEmployee],
            'professional_tax' => ['type' => 'deduction', 'name' => 'Professional Tax', 'code' => 'PT', 'amount' => $pt],
            'tds' => ['type' => 'deduction', 'name' => 'Tax Deducted at Source', 'code' => 'TDS', 'amount' => $tds],
            'lwf_employee' => ['type' => 'deduction', 'name' => 'Labour Welfare Fund (Employee)', 'code' => 'LWF_EE', 'amount' => $lwfEmployee],
            'pf_employer' => ['type' => 'employer_contribution', 'name' => 'PF (Employer)', 'code' => 'PF_ER', 'amount' => $pfEmployer],
            'esi_employer' => ['type' => 'employer_contribution', 'name' => 'ESI (Employer)', 'code' => 'ESI_ER', 'amount' => $esiEmployer],
            'lwf_employer' => ['type' => 'employer_contribution', 'name' => 'Labour Welfare Fund (Employer)', 'code' => 'LWF_ER', 'amount' => $lwfEmployer],
        ];
    }

    private function resolveOrCreateSalaryComponent(string $category, array $definition): HrSalaryComponent
    {
        $component = HrSalaryComponent::firstOrCreate(
            ['code' => $definition['code']],
            [
                'company_id' => 1,
                'name' => $definition['name'],
                'component_type' => $definition['type'],
                'category' => $category,
                'calculation_type' => 'fixed',
                'default_value' => (float) $definition['amount'],
                'is_active' => true,
                'show_in_payslip' => true,
                'show_if_zero' => false,
                'sort_order' => 0,
            ]
        );

        if ($component->category !== $category || $component->component_type !== $definition['type']) {
            $component->update([
                'category' => $category,
                'component_type' => $definition['type'],
                'name' => $component->name ?: $definition['name'],
                'is_active' => true,
            ]);
        }

        return $component;
    }

    private function resolveSalaryStructureId(?int $salaryStructureId): int
    {
        if ($salaryStructureId) {
            return $salaryStructureId;
        }

        $defaultStructure = HrSalaryStructure::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($defaultStructure) {
            return $defaultStructure->id;
        }

        return HrSalaryStructure::create([
            'company_id' => 1,
            'code' => 'CUSTOM-' . now()->format('YmdHis'),
            'name' => 'Custom Salary Structure',
            'description' => 'Auto-created fallback structure for employee salary records.',
            'base_type' => 'gross',
            'is_default' => false,
            'payroll_frequency' => 'monthly',
            'payment_day' => 1,
            'is_active' => true,
            'created_by' => auth()->id(),
        ])->id;
    }

    private function normalizeEmployeeSalaryComponentCalculationType(?string $calculationType): string
    {
        return match ((string) $calculationType) {
            'percent_of_basic', 'percent_of_gross', 'formula', 'fixed' => (string) $calculationType,
            // Employee salary rows persist the resolved monthly amount, so unsupported
            // structure-only modes are stored as fixed snapshots.
            'slab_based', 'percent_of_ctc', 'percentage', '' => 'fixed',
            default => 'fixed',
        };
    }

    private function usesDerivedServerAmount(string $category): bool
    {
        return in_array($category, [
            'pf_employee',
            'esi_employee',
            'professional_tax',
            'tds',
            'lwf_employee',
            'pf_employer',
            'esi_employer',
            'lwf_employer',
        ], true);
    }

    private function calculateProfessionalTaxForEmployee(HrEmployee $employee, float $gross, string $effectiveDate): float
    {
        if (!$employee->pt_state || $gross <= 0) {
            return 0.0;
        }

        $effectiveDate = $effectiveDate ?: now()->toDateString();
        $employeeState = mb_strtolower(trim((string) $employee->pt_state));
        $employeeGender = mb_strtolower((string) ($employee->gender ?? 'all'));

        $slab = HrProfessionalTaxSlab::query()
            ->where('is_active', true)
            ->where('effective_from', '<=', $effectiveDate)
            ->where(function ($query) use ($effectiveDate) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $effectiveDate);
            })
            ->where('salary_from', '<=', $gross)
            ->where('salary_to', '>=', $gross)
            ->get()
            ->first(function (HrProfessionalTaxSlab $slab) use ($employeeState, $employeeGender) {
                $slabStateCode = mb_strtolower(trim((string) $slab->state_code));
                $slabStateName = mb_strtolower(trim((string) $slab->state_name));
                $slabGender = mb_strtolower((string) ($slab->gender ?? 'all'));

                $stateMatches = $employeeState !== ''
                    && ($employeeState === $slabStateCode || $employeeState === $slabStateName);

                $genderMatches = $slabGender === 'all'
                    || $employeeGender === ''
                    || $employeeGender === $slabGender;

                return $stateMatches && $genderMatches;
            });

        return round((float) ($slab?->tax_amount ?? 0), 2);
    }

    private function calculatePfBreakdown(float $basic, string $effectiveDate): array
    {
        if ($basic <= 0) {
            return [
                'employee' => 0.0,
                'employer_pf' => 0.0,
                'eps' => 0.0,
                'employer_total' => 0.0,
            ];
        }

        $effectiveDate = $effectiveDate ?: now()->toDateString();
        $slab = HrPfSlab::query()
            ->where('is_active', true)
            ->where('effective_from', '<=', $effectiveDate)
            ->where(function ($query) use ($effectiveDate) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $effectiveDate);
            })
            ->orderByDesc('effective_from')
            ->first();

        if (!$slab) {
            $employee = round(min($basic * 0.12, 1800), 2);

            return [
                'employee' => $employee,
                'employer_pf' => $employee,
                'eps' => 0.0,
                'employer_total' => $employee,
            ];
        }

        $pfWages = min($basic, (float) $slab->wage_ceiling);
        $employee = round($pfWages * ((float) $slab->employee_contribution_rate / 100), 2);
        $employerPf = round($pfWages * ((float) $slab->employer_pf_rate / 100), 2);
        $eps = round($pfWages * ((float) $slab->employer_eps_rate / 100), 2);

        return [
            'employee' => $employee,
            'employer_pf' => $employerPf,
            'eps' => $eps,
            'employer_total' => round($employerPf + $eps, 2),
        ];
    }

    private function resolveStatutoryApplicability(array $validated): array
    {
        $selectedRows = collect($validated['structure_components'] ?? [])
            ->filter(fn(array $row) => (bool) ($row['applicable'] ?? false));

        if ($selectedRows->isEmpty()) {
            return [
                'pf' => (bool) ($validated['pf_applicable'] ?? false),
                'esi' => (bool) ($validated['esi_applicable'] ?? false),
                'pt' => (bool) ($validated['pt_applicable'] ?? false),
                'tds' => (bool) ($validated['tds_applicable'] ?? false),
                'lwf' => (bool) ($validated['lwf_applicable'] ?? false),
            ];
        }

        $components = HrSalaryComponent::query()
            ->whereIn('id', $selectedRows->pluck('hr_salary_component_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $selectedCategories = $selectedRows
            ->map(fn(array $row) => $components[(int) ($row['hr_salary_component_id'] ?? 0)]->category ?? null)
            ->filter()
            ->values();

        return [
            'pf' => $selectedCategories->contains(fn($category) => in_array($category, ['pf_employee', 'pf_employer', 'eps'], true))
                || (bool) ($validated['pf_applicable'] ?? false),
            'esi' => $selectedCategories->contains(fn($category) => in_array($category, ['esi_employee', 'esi_employer'], true))
                || (bool) ($validated['esi_applicable'] ?? false),
            'pt' => $selectedCategories->contains('professional_tax')
                || (bool) ($validated['pt_applicable'] ?? false),
            'tds' => $selectedCategories->contains('tds')
                || (bool) ($validated['tds_applicable'] ?? false),
            'lwf' => $selectedCategories->contains(fn($category) => in_array($category, ['lwf_employee', 'lwf_employer'], true))
                || (bool) ($validated['lwf_applicable'] ?? false),
        ];
    }

    private function calculateMonthlyTdsForEmployee(HrEmployee $employee, float $gross, string $effectiveDate): float
    {
        $financialYear = $this->financialYearForDate(Carbon::parse($effectiveDate));
        $declaration = HrTaxDeclaration::query()
            ->where('hr_employee_id', $employee->id)
            ->where('financial_year', $financialYear)
            ->first();

        $regime = (string) ($declaration?->tax_regime ?: $employee->tax_regime ?: 'new');
        $annualIncome = max(0, round($gross * 12, 2));
        $totalExemption = (float) ($declaration?->total_verified ?? $declaration?->total_declared ?? $declaration?->total_exemption ?? 0);
        $taxableIncome = max(0, $annualIncome - $totalExemption);
        $annualTax = $this->estimateAnnualTaxFromSlabs($taxableIncome, $financialYear, $regime);

        return $annualTax > 0 ? round($annualTax / 12, 2) : 0.0;
    }

    private function calculateLwfForEmployee(HrEmployee $employee, string $effectiveDate): array
    {
        if (!$employee->pt_state) {
            return ['employee' => 0.0, 'employer' => 0.0];
        }

        $effectiveOn = Carbon::parse($effectiveDate);
        $employeeState = mb_strtolower(trim((string) $employee->pt_state));

        $slab = HrLwfSlab::query()
            ->where('is_active', true)
            ->where('effective_from', '<=', $effectiveDate)
            ->where(function ($query) use ($effectiveDate) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $effectiveDate);
            })
            ->get()
            ->first(function (HrLwfSlab $slab) use ($employeeState) {
                $slabStateCode = mb_strtolower(trim((string) $slab->state_code));
                $slabStateName = mb_strtolower(trim((string) $slab->state_name));

                return $employeeState !== ''
                    && ($employeeState === $slabStateCode || $employeeState === $slabStateName);
            });

        if (!$slab || !$this->isLwfApplicableForMonth($slab, (int) $effectiveOn->month)) {
            return ['employee' => 0.0, 'employer' => 0.0];
        }

        return [
            'employee' => round((float) $slab->employee_contribution, 2),
            'employer' => round((float) $slab->employer_contribution, 2),
        ];
    }

    private function estimateAnnualTaxFromSlabs(float $taxableIncome, string $financialYear, string $regime): float
    {
        $slabs = HrTdsSlab::query()
            ->whereIn('financial_year', $this->financialYearAliases($financialYear))
            ->where('regime', $regime)
            ->where('category', 'general')
            ->where('is_active', true)
            ->orderBy('income_from')
            ->get();

        if ($slabs->isEmpty()) {
            return $this->estimateAnnualTaxFallback($taxableIncome, $regime);
        }

        $tax = 0.0;
        $cessPercent = 0.0;

        foreach ($slabs as $slab) {
            $slabStart = (float) $slab->income_from;
            $slabEnd = (float) $slab->income_to;
            if ($taxableIncome <= $slabStart) {
                continue;
            }

            $taxablePortion = min($taxableIncome, $slabEnd) - $slabStart;
            if ($taxablePortion <= 0) {
                continue;
            }

            $tax += $taxablePortion * ((float) $slab->tax_percent / 100);
            $tax += $taxablePortion * ((float) $slab->surcharge_percent / 100);
            $cessPercent = max($cessPercent, (float) $slab->cess_percent);
        }

        if ($tax <= 0) {
            return 0.0;
        }

        return round($tax * (1 + ($cessPercent / 100)), 2);
    }

    private function estimateAnnualTaxFallback(float $taxableIncome, string $regime): float
    {
        if ($taxableIncome <= 300000) {
            return 0.0;
        }

        $tax = 0.0;
        $slabs = $regime === 'old'
            ? [[250000, 0], [500000, 5], [1000000, 20], [INF, 30]]
            : [[300000, 0], [700000, 5], [1000000, 10], [1200000, 15], [1500000, 20], [INF, 30]];

        $previous = 0.0;
        foreach ($slabs as [$limit, $rate]) {
            if ($taxableIncome <= $previous) {
                break;
            }

            $amount = min($taxableIncome, $limit) - $previous;
            if ($amount > 0) {
                $tax += $amount * ($rate / 100);
            }

            $previous = $limit;
        }

        return round($tax * 1.04, 2);
    }

    private function financialYearForDate(Carbon $date): string
    {
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }

    private function financialYearAliases(string $financialYear): array
    {
        if (!preg_match('/^(\d{4})-(\d{2}|\d{4})$/', $financialYear, $matches)) {
            return [$financialYear];
        }

        $startYear = (int) $matches[1];
        $endYear = $matches[2];
        $endYearFull = strlen($endYear) === 2 ? (int) substr((string) ($startYear + 1), 0, 2) . $endYear : (int) $endYear;

        return array_values(array_unique([
            sprintf('%d-%02d', $startYear, $endYearFull % 100),
            sprintf('%d-%d', $startYear, $endYearFull),
        ]));
    }

    private function isLwfApplicableForMonth(HrLwfSlab $slab, int $month): bool
    {
        return match ((string) $slab->frequency) {
            'monthly' => true,
            'half_yearly' => in_array($month, [6, 12], true),
            'annual' => $month === 12,
            default => false,
        };
    }

    private function assertSalaryEffectiveFromDoesNotOverlap(HrEmployee $employee, string $effectiveFrom, ?int $ignoreSalaryId = null): void
    {
        $query = HrEmployeeSalary::query()
            ->where('hr_employee_id', $employee->id)
            ->whereDate('effective_from', '>=', $effectiveFrom);

        if ($ignoreSalaryId) {
            $query->where('id', '!=', $ignoreSalaryId);
        }

        if ($query->exists()) {
            throw new \RuntimeException('Effective date overlaps with an existing salary record. Use a later date or update the affected revision first.');
        }
    }
}
