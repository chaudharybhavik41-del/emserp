<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrSalaryStructure;
use App\Models\Hr\HrSalaryComponent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrSalaryStructureController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = HrSalaryStructure::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Safely count employees if relationship exists
        try {
            $structures = $query->withCount('employees')
                                ->orderBy('name')
                                ->paginate(20)
                                ->withQueryString();
        } catch (\Exception $e) {
            $structures = $query->orderBy('name')
                                ->paginate(20)
                                ->withQueryString();
        }

        return view('hr.salary-structures.index', compact('structures'));
    }

    public function create()
    {
        // FIXED: Group by 'component_type' instead of 'type'
        $components = HrSalaryComponent::where('is_active', true)
                                       ->orderBy('sort_order')
                                       ->orderBy('name')
                                       ->get()
                                       ->groupBy('component_type');  // FIXED

        $totalComponents = HrSalaryComponent::where('is_active', true)->count();

        return view('hr.salary-structures.form', compact('components', 'totalComponents'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_salary_structures,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'components' => 'required|array|min:1',
            'components.*.id' => 'required|exists:hr_salary_components,id',
            'components.*.calculation_type' => 'required|in:fixed,percent_of_basic,percent_of_gross,percent_of_ctc,formula,slab_based',
            'components.*.calculation_value' => 'nullable|numeric|min:0',
            'components.*.percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);

        DB::beginTransaction();
        try {
            $structure = HrSalaryStructure::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
            ]);

            foreach ($validated['components'] as $comp) {
                $structure->components()->attach($comp['id'], [
                    'calculation_type' => $comp['calculation_type'],
                    'value' => $comp['calculation_value'] ?? 0,
                    'percentage' => $comp['percentage'] ?? null,
                    'is_active' => true,
                ]);
            }

            DB::commit();

            return redirect()->route('hr.salary-structures.index')
                             ->with('success', 'Salary structure created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error creating salary structure: ' . $e->getMessage())
                        ->withInput();
        }
    }

    public function show(HrSalaryStructure $salaryStructure)
    {
        $salaryStructure->load('components');
        
        // Group components by type for display
        $groupedComponents = $salaryStructure->components->groupBy('component_type');
        
        return view('hr.salary-structures.show', [
            'structure' => $salaryStructure,
            'groupedComponents' => $groupedComponents,
        ]);
    }

    public function edit(HrSalaryStructure $salaryStructure)
    {
        $salaryStructure->load('components');
        
        // FIXED: Group by 'component_type' instead of 'type'
        $components = HrSalaryComponent::where('is_active', true)
                                       ->orderBy('sort_order')
                                       ->orderBy('name')
                                       ->get()
                                       ->groupBy('component_type');  // FIXED

        $totalComponents = HrSalaryComponent::where('is_active', true)->count();

        return view('hr.salary-structures.form', [
            'structure' => $salaryStructure,
            'components' => $components,
            'totalComponents' => $totalComponents,
        ]);
    }

    public function update(Request $request, HrSalaryStructure $salaryStructure)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_salary_structures,code,' . $salaryStructure->id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'components' => 'required|array|min:1',
            'components.*.id' => 'required|exists:hr_salary_components,id',
            'components.*.calculation_type' => 'required|in:fixed,percent_of_basic,percent_of_gross,percent_of_ctc,formula,slab_based',
            'components.*.calculation_value' => 'nullable|numeric|min:0',
            'components.*.percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);

        DB::beginTransaction();
        try {
            $salaryStructure->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
            ]);

            // Sync components
            $syncData = [];
            foreach ($validated['components'] as $comp) {
                $syncData[$comp['id']] = [
                    'calculation_type' => $comp['calculation_type'],
                    'value' => $comp['calculation_value'] ?? 0,
                    'percentage' => $comp['percentage'] ?? null,
                    'is_active' => true,
                ];
            }
            $salaryStructure->components()->sync($syncData);

            DB::commit();

            return redirect()->route('hr.salary-structures.index')
                             ->with('success', 'Salary structure updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error updating salary structure: ' . $e->getMessage())
                        ->withInput();
        }
    }

    public function destroy(HrSalaryStructure $salaryStructure)
    {
        try {
            if ($salaryStructure->employees()->exists()) {
                return back()->with('error', 'Cannot delete structure. Employees are assigned to it.');
            }
        } catch (\Exception $e) {
            // employees relationship might not exist yet
        }

        $salaryStructure->components()->detach();
        $salaryStructure->delete();

        return redirect()->route('hr.salary-structures.index')
                         ->with('success', 'Salary structure deleted successfully.');
    }

    public function duplicate(HrSalaryStructure $salaryStructure)
    {
        DB::beginTransaction();
        try {
            $newStructure = $salaryStructure->replicate();
            $newStructure->code = $salaryStructure->code . '_COPY';
            $newStructure->name = $salaryStructure->name . ' (Copy)';
            $newStructure->save();

            // Copy components
            foreach ($salaryStructure->components as $component) {
                $newStructure->components()->attach($component->id, [
                    'calculation_type' => $component->pivot->calculation_type,
                    'calculation_value' => $component->pivot->calculation_value,
                    'percentage' => $component->pivot->percentage,
                    'based_on' => $component->pivot->based_on,
                    'is_active' => $component->pivot->is_active ?? true,
                ]);
            }

            DB::commit();

            return redirect()->route('hr.salary-structures.edit', $newStructure)
                             ->with('success', 'Salary structure duplicated. Please update the code and name.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error duplicating salary structure: ' . $e->getMessage());
        }
    }
}
