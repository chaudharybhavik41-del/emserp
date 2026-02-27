<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrGrade;
use Illuminate\Http\Request;

class HrGradeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = HrGrade::query();

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

        $grades = $query->withCount(['employees', 'designations'])
                        ->orderBy('level')
                        ->paginate(20)
                        ->withQueryString();

        return view('hr.grades.index', compact('grades'));
    }

    public function create()
    {
        return view('hr.grades.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_grades,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'level' => 'required|integer|min:1|max:99',
            'min_basic' => 'nullable|numeric|min:0',
            'max_basic' => 'nullable|numeric|min:0|gte:min_basic',
            'min_gross' => 'nullable|numeric|min:0',
            'max_gross' => 'nullable|numeric|min:0|gte:min_gross',
            'probation_months' => 'nullable|integer|min:0|max:24',
            'notice_period_days' => 'nullable|integer|min:0|max:180',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);

        HrGrade::create($validated);

        return redirect()->route('hr.grades.index')
                         ->with('success', 'Grade created successfully.');
    }

    public function edit(HrGrade $grade)
    {
        return view('hr.grades.form', compact('grade'));
    }

    public function update(Request $request, HrGrade $grade)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_grades,code,' . $grade->id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'level' => 'required|integer|min:1|max:99',
            'min_basic' => 'nullable|numeric|min:0',
            'max_basic' => 'nullable|numeric|min:0|gte:min_basic',
            'min_gross' => 'nullable|numeric|min:0',
            'max_gross' => 'nullable|numeric|min:0|gte:min_gross',
            'probation_months' => 'nullable|integer|min:0|max:24',
            'notice_period_days' => 'nullable|integer|min:0|max:180',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);

        $grade->update($validated);

        return redirect()->route('hr.grades.index')
                         ->with('success', 'Grade updated successfully.');
    }

    public function destroy(HrGrade $grade)
    {
        if ($grade->employees()->exists()) {
            return back()->with('error', 'Cannot delete grade. Employees are assigned to it.');
        }

        if ($grade->designations()->exists()) {
            return back()->with('error', 'Cannot delete grade. Designations are using it.');
        }

        $grade->delete();

        return redirect()->route('hr.grades.index')
                         ->with('success', 'Grade deleted successfully.');
    }
}
