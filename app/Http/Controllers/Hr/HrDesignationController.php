<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrDesignation;
use App\Models\Hr\HrGrade;
use App\Models\Department;
use Illuminate\Http\Request;

class HrDesignationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = HrDesignation::with(['department', 'grade']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $designations = $query->withCount('employees')
                              ->orderBy('sort_order')
                              ->orderBy('name')
                              ->paginate(20)
                              ->withQueryString();

        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return view('hr.designations.index', compact('designations', 'departments'));
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $grades = HrGrade::where('is_active', true)->orderBy('level')->get();

        return view('hr.designations.form', compact('departments', 'grades'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_designations,code',
            'name' => 'required|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'hr_grade_id' => 'nullable|exists:hr_grades,id',
            'description' => 'nullable|string|max:500',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'is_supervisory' => 'boolean',
            'is_managerial' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_supervisory'] = $request->boolean('is_supervisory', false);
        $validated['is_managerial'] = $request->boolean('is_managerial', false);

        HrDesignation::create($validated);

        return redirect()->route('hr.designations.index')
                         ->with('success', 'Designation created successfully.');
    }

    public function edit(HrDesignation $designation)
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $grades = HrGrade::where('is_active', true)->orderBy('level')->get();

        return view('hr.designations.form', compact('designation', 'departments', 'grades'));
    }

    public function update(Request $request, HrDesignation $designation)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_designations,code,' . $designation->id,
            'name' => 'required|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'hr_grade_id' => 'nullable|exists:hr_grades,id',
            'description' => 'nullable|string|max:500',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'is_supervisory' => 'boolean',
            'is_managerial' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_supervisory'] = $request->boolean('is_supervisory', false);
        $validated['is_managerial'] = $request->boolean('is_managerial', false);

        $designation->update($validated);

        return redirect()->route('hr.designations.index')
                         ->with('success', 'Designation updated successfully.');
    }

    public function destroy(HrDesignation $designation)
    {
        if ($designation->employees()->exists()) {
            return back()->with('error', 'Cannot delete designation. Employees are assigned to it.');
        }

        $designation->delete();

        return redirect()->route('hr.designations.index')
                         ->with('success', 'Designation deleted successfully.');
    }
}
