<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:core.department.view')->only(['index', 'show']);
        $this->middleware('permission:core.department.create')->only(['create', 'store']);
        $this->middleware('permission:core.department.update')->only(['edit', 'update']);
        $this->middleware('permission:core.department.delete')->only(['destroy']);
    }

    /**
     * Display a listing of departments.
     */
    public function index(Request $request)
    {
        $query = Department::with(['parent', 'head', 'children'])
            ->withCount('users');

        // Search
        if ($search = trim($request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('is_active', $request->get('status') === 'active');
        }

        // Filter by parent (show hierarchy)
        if ($request->has('parent_id')) {
            $parentId = $request->get('parent_id');
            if ($parentId === 'root') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $parentId);
            }
        }

        $departments = $query->orderBy('sort_order')->orderBy('name')->paginate(25)->withQueryString();

        // Get tree structure for sidebar/filter
        $departmentTree = Department::root()->with('descendants')->ordered()->get();

        return view('departments.index', compact('departments', 'departmentTree'));
    }

    /**
     * Show the form for creating a new department.
     */
    public function create()
    {
        $department = new Department();
        $department->is_active = true;
        $department->sort_order = Department::max('sort_order') + 10;

        $parentDepartments = Department::getFlatTree();
        $users = User::active()->orderBy('name')->get();

        return view('departments.create', compact('department', 'parentDepartments', 'users'));
    }

    /**
     * Store a newly created department.
     */
    public function store(StoreDepartmentRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $department = Department::create($data);

        ActivityLog::logCreated($department, "Created department: {$department->name}");

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department created successfully.');
    }

    /**
     * Display the specified department.
     */
    public function show(Department $department)
    {
        $department->load(['parent', 'head', 'children', 'users']);

        $activityLogs = ActivityLog::forSubject($department)
            ->latest()
            ->take(20)
            ->get();

        return view('departments.show', compact('department', 'activityLogs'));
    }

    /**
     * Show the form for editing the specified department.
     */
    public function edit(Department $department)
    {
        // Get departments excluding this one and its descendants
        $excludeIds = array_merge([$department->id], $department->getAllDescendantIds());
        $parentDepartments = Department::getFlatTree($department->id);
        
        $users = User::active()->orderBy('name')->get();

        return view('departments.edit', compact('department', 'parentDepartments', 'users'));
    }

    /**
     * Update the specified department.
     */
    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $oldValues = $department->toArray();
        
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        // Prevent circular reference
        if (isset($data['parent_id']) && $data['parent_id']) {
            $descendantIds = $department->getAllDescendantIds();
            if (in_array($data['parent_id'], $descendantIds) || $data['parent_id'] == $department->id) {
                return back()
                    ->withInput()
                    ->withErrors(['parent_id' => 'Cannot set a descendant or self as parent.']);
            }
        }

        $department->update($data);

        ActivityLog::logUpdated($department, $oldValues, "Updated department: {$department->name}");

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department updated successfully.');
    }

    /**
     * Remove the specified department.
     */
    public function destroy(Department $department)
    {
        // Check if department has children
        if ($department->hasChildren()) {
            return back()->with('error', 'Cannot delete department with sub-departments. Please delete or move them first.');
        }

        // Check if department has users
        if ($department->users()->exists()) {
            return back()->with('error', 'Cannot delete department with assigned users. Please remove users first.');
        }

        ActivityLog::logDeleted($department, "Deleted department: {$department->name}");

        $department->delete();

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    /**
     * Reorder departments.
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:departments,id'],
            'items.*.sort_order' => ['required', 'integer'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        foreach ($request->items as $item) {
            Department::where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
                'parent_id' => $item['parent_id'] ?? null,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get department tree as JSON (for AJAX).
     */
    public function tree()
    {
        $departments = Department::root()
            ->with('descendants')
            ->ordered()
            ->get();

        return response()->json($departments);
    }
}
