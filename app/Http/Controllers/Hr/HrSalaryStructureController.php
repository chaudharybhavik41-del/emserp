<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrSalaryStructure;
use App\Models\Hr\HrSalaryComponent;
use App\Models\Hr\HrProfessionalTaxSlab;
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
        $query = HrSalaryStructure::query()
            ->withCount('components')
            ->select('hr_salary_structures.*')
            ->selectSub(function ($subQuery) {
                $subQuery->from('hr_employee_salaries')
                    ->selectRaw('COUNT(DISTINCT hr_employee_id)')
                    ->whereColumn('hr_employee_salaries.hr_salary_structure_id', 'hr_salary_structures.id')
                    ->where('is_current', true);
            }, 'employees_count');

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

        $structures = $query->orderBy('name')
            ->paginate(20)
            ->withQueryString();

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
            'components.*.formula' => 'nullable|string|max:500',
            'components.*.min_value' => 'nullable|numeric|min:0',
            'components.*.max_value' => 'nullable|numeric|min:0',
            'components.*.is_mandatory' => 'nullable|boolean',
            'components.*.sort_order' => 'nullable|integer|min:0',
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

            foreach ($validated['components'] as $index => $comp) {
                $structure->components()->attach($comp['id'], $this->buildPivotPayload($comp, null, $index));
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
            'components.*.formula' => 'nullable|string|max:500',
            'components.*.min_value' => 'nullable|numeric|min:0',
            'components.*.max_value' => 'nullable|numeric|min:0',
            'components.*.is_mandatory' => 'nullable|boolean',
            'components.*.sort_order' => 'nullable|integer|min:0',
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
            $existingComponents = $salaryStructure->components()
                ->get()
                ->keyBy('id');
            $syncData = [];
            foreach ($validated['components'] as $index => $comp) {
                $existingComponent = $existingComponents->get($comp['id']);
                $syncData[$comp['id']] = $this->buildPivotPayload($comp, $existingComponent, $index);
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
            if ($salaryStructure->employees()->exists() || $salaryStructure->employeeSalaries()->exists()) {
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
            $newStructure->code = $this->generateDuplicateCode($salaryStructure->code);
            $newStructure->name = $salaryStructure->name . ' (Copy)';
            $newStructure->save();

            // Copy components
            foreach ($salaryStructure->components as $component) {
                $newStructure->components()->attach($component->id, [
                    'calculation_type' => $component->pivot->calculation_type,
                    'value' => $component->pivot->value,
                    'percentage' => $component->pivot->percentage,
                    'formula' => $component->pivot->formula,
                    'min_value' => $component->pivot->min_value,
                    'max_value' => $component->pivot->max_value,
                    'is_mandatory' => $component->pivot->is_mandatory ?? false,
                    'sort_order' => $component->pivot->sort_order ?? 0,
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

    private function buildPivotPayload(array $component, $existingComponent = null, int $index = 0): array
    {
        $existingPivot = $existingComponent?->pivot;
        $calculationType = $component['calculation_type'];

        return [
            'calculation_type' => $calculationType,
            'value' => $calculationType === 'fixed' ? ($component['calculation_value'] ?? $existingPivot?->value ?? 0) : ($existingPivot?->value ?? 0),
            'percentage' => str_starts_with($calculationType, 'percent_') ? ($component['percentage'] ?? $existingPivot?->percentage) : ($existingPivot?->percentage),
            'formula' => $calculationType === 'formula' ? ($component['formula'] ?? $existingPivot?->formula) : ($existingPivot?->formula),
            'min_value' => array_key_exists('min_value', $component) ? $component['min_value'] : $existingPivot?->min_value,
            'max_value' => array_key_exists('max_value', $component) ? $component['max_value'] : $existingPivot?->max_value,
            'is_mandatory' => array_key_exists('is_mandatory', $component) ? (bool) $component['is_mandatory'] : (bool) ($existingPivot?->is_mandatory ?? false),
            'sort_order' => $component['sort_order'] ?? $existingPivot?->sort_order ?? ($index + 1),
            'is_active' => true,
        ];
    }

    private function generateDuplicateCode(string $baseCode): string
    {
        $candidate = $baseCode . '_COPY';
        $suffix = 2;

        while (HrSalaryStructure::query()->where('code', $candidate)->exists()) {
            $candidate = $baseCode . '_COPY' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
