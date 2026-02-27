<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrSalaryComponent;
use Illuminate\Http\Request;

class HrSalaryComponentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = HrSalaryComponent::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Filter by component_type (correct column name)
        if ($request->filled('component_type')) {
            $query->where('component_type', $request->component_type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $components = $query
            ->orderBy('sort_order')
            ->orderBy('component_type')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('hr.salary-components.index', compact('components'));
    }

    public function create()
    {
        return view('hr.salary-components.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_salary_components,code',
            'name' => 'required|string|max:100',
            'short_name' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
            'component_type' => 'required|in:earning,deduction,employer_contribution',
            'category' => 'nullable|string|max:100',
            'calculation_type' => 'required|in:fixed,percent_of_basic,percent_of_gross,percent_of_ctc,attendance_based,slab_based,formula',
            'default_value' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'formula' => 'nullable|string|max:500',
            'is_statutory' => 'boolean',
            'affects_pf' => 'boolean',
            'affects_esi' => 'boolean',
            'affects_gratuity' => 'boolean',
            'is_taxable' => 'boolean',
            'is_part_of_ctc' => 'boolean',
            'is_part_of_gross' => 'boolean',
            'show_in_payslip' => 'boolean',
            'show_if_zero' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        
        // Normalize checkboxes
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_statutory'] = $request->boolean('is_statutory', false);
        $validated['is_taxable'] = $request->boolean('is_taxable', false);
        $validated['affects_pf'] = $request->boolean('affects_pf', false);
        $validated['affects_esi'] = $request->boolean('affects_esi', false);
        $validated['affects_gratuity'] = $request->boolean('affects_gratuity', false);
        $validated['is_part_of_ctc'] = $request->boolean('is_part_of_ctc', true);
        $validated['is_part_of_gross'] = $request->boolean('is_part_of_gross', true);
        $validated['show_in_payslip'] = $request->boolean('show_in_payslip', true);
        $validated['show_if_zero'] = $request->boolean('show_if_zero', false);

        // Auto sort_order if empty
        if (!isset($validated['sort_order']) || $validated['sort_order'] === null) {
            $validated['sort_order'] = (HrSalaryComponent::max('sort_order') ?? 0) + 1;
        }

        HrSalaryComponent::create($validated);

        return redirect()
            ->route('hr.salary-components.index')
            ->with('success', 'Salary component created successfully.');
    }

    public function edit(HrSalaryComponent $salaryComponent)
    {
        return view('hr.salary-components.form', ['component' => $salaryComponent]);
    }

    public function update(Request $request, HrSalaryComponent $salaryComponent)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_salary_components,code,' . $salaryComponent->id,
            'name' => 'required|string|max:100',
            'short_name' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
            'component_type' => 'required|in:earning,deduction,employer_contribution',
            'category' => 'nullable|string|max:100',
            'calculation_type' => 'required|in:fixed,percent_of_basic,percent_of_gross,percent_of_ctc,attendance_based,slab_based,formula',
            'default_value' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'formula' => 'nullable|string|max:500',
            'is_statutory' => 'boolean',
            'affects_pf' => 'boolean',
            'affects_esi' => 'boolean',
            'affects_gratuity' => 'boolean',
            'is_taxable' => 'boolean',
            'is_part_of_ctc' => 'boolean',
            'is_part_of_gross' => 'boolean',
            'show_in_payslip' => 'boolean',
            'show_if_zero' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        
        // Normalize checkboxes
        $validated['is_active'] = $request->boolean('is_active', $salaryComponent->is_active);
        $validated['is_statutory'] = $request->boolean('is_statutory', $salaryComponent->is_statutory);
        $validated['is_taxable'] = $request->boolean('is_taxable', $salaryComponent->is_taxable);
        $validated['affects_pf'] = $request->boolean('affects_pf', $salaryComponent->affects_pf);
        $validated['affects_esi'] = $request->boolean('affects_esi', $salaryComponent->affects_esi);
        $validated['affects_gratuity'] = $request->boolean('affects_gratuity', $salaryComponent->affects_gratuity);
        $validated['is_part_of_ctc'] = $request->boolean('is_part_of_ctc', $salaryComponent->is_part_of_ctc);
        $validated['is_part_of_gross'] = $request->boolean('is_part_of_gross', $salaryComponent->is_part_of_gross);
        $validated['show_in_payslip'] = $request->boolean('show_in_payslip', $salaryComponent->show_in_payslip);
        $validated['show_if_zero'] = $request->boolean('show_if_zero', $salaryComponent->show_if_zero);

        $salaryComponent->update($validated);

        return redirect()
            ->route('hr.salary-components.index')
            ->with('success', 'Salary component updated successfully.');
    }

    public function destroy(HrSalaryComponent $salaryComponent)
    {
        if ($salaryComponent->is_statutory) {
            return back()->with('error', 'Cannot delete statutory components.');
        }

        if ($salaryComponent->salaryStructures()->exists()) {
            return back()->with('error', 'Cannot delete component. It is used in salary structures.');
        }

        $salaryComponent->delete();

        return redirect()
            ->route('hr.salary-components.index')
            ->with('success', 'Salary component deleted successfully.');
    }
}
