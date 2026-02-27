<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrShift;
use Illuminate\Http\Request;

class HrShiftController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = HrShift::query();

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

        $shifts = $query->withCount('employees')
                        ->orderBy('start_time')
                        ->paginate(20)
                        ->withQueryString();

        return view('hr.shifts.index', compact('shifts'));
    }

    public function create()
    {
        return view('hr.shifts.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_shifts,code',
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'working_hours' => 'required|numeric|min:1|max:24',
            'break_duration_minutes' => 'nullable|integer|min:0|max:120',
            'grace_period_minutes' => 'nullable|integer|min:0|max:60',
            'late_mark_after_minutes' => 'nullable|integer|min:0',
            'half_day_late_minutes' => 'nullable|integer|min:0',
            'absent_after_minutes' => 'nullable|integer|min:0',
            'early_going_grace_minutes' => 'nullable|integer|min:0|max:60',
            'half_day_early_minutes' => 'nullable|integer|min:0',
            'ot_applicable' => 'boolean',
            'ot_start_after_minutes' => 'nullable|integer|min:0',
            'ot_rate_multiplier' => 'nullable|numeric|min:1|max:5',
            'max_ot_hours_per_day' => 'nullable|numeric|min:0|max:12',
            'min_ot_minutes' => 'nullable|integer|min:0',
            'is_night_shift' => 'boolean',
            'spans_next_day' => 'boolean',
            'is_flexible' => 'boolean',
            'auto_half_day_on_single_punch' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['ot_applicable'] = $request->boolean('ot_applicable', false);
        $validated['is_night_shift'] = $request->boolean('is_night_shift', false);
        $validated['spans_next_day'] = $request->boolean('spans_next_day', false);
        $validated['is_flexible'] = $request->boolean('is_flexible', false);
        $validated['auto_half_day_on_single_punch'] = $request->boolean('auto_half_day_on_single_punch', false);

        HrShift::create($validated);

        return redirect()->route('hr.shifts.index')
                         ->with('success', 'Shift created successfully.');
    }

    public function show(HrShift $shift)
    {
        $shift->loadCount('employees');
        return view('hr.shifts.show', compact('shift'));
    }

    public function edit(HrShift $shift)
    {
        return view('hr.shifts.form', compact('shift'));
    }

    public function update(Request $request, HrShift $shift)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_shifts,code,' . $shift->id,
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'working_hours' => 'required|numeric|min:1|max:24',
            'break_duration_minutes' => 'nullable|integer|min:0|max:120',
            'grace_period_minutes' => 'nullable|integer|min:0|max:60',
            'late_mark_after_minutes' => 'nullable|integer|min:0',
            'half_day_late_minutes' => 'nullable|integer|min:0',
            'absent_after_minutes' => 'nullable|integer|min:0',
            'early_going_grace_minutes' => 'nullable|integer|min:0|max:60',
            'half_day_early_minutes' => 'nullable|integer|min:0',
            'ot_applicable' => 'boolean',
            'ot_start_after_minutes' => 'nullable|integer|min:0',
            'ot_rate_multiplier' => 'nullable|numeric|min:1|max:5',
            'max_ot_hours_per_day' => 'nullable|numeric|min:0|max:12',
            'min_ot_minutes' => 'nullable|integer|min:0',
            'is_night_shift' => 'boolean',
            'spans_next_day' => 'boolean',
            'is_flexible' => 'boolean',
            'auto_half_day_on_single_punch' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['ot_applicable'] = $request->boolean('ot_applicable', false);
        $validated['is_night_shift'] = $request->boolean('is_night_shift', false);
        $validated['spans_next_day'] = $request->boolean('spans_next_day', false);
        $validated['is_flexible'] = $request->boolean('is_flexible', false);
        $validated['auto_half_day_on_single_punch'] = $request->boolean('auto_half_day_on_single_punch', false);

        $shift->update($validated);

        return redirect()->route('hr.shifts.index')
                         ->with('success', 'Shift updated successfully.');
    }

    public function destroy(HrShift $shift)
    {
        if ($shift->employees()->exists()) {
            return back()->with('error', 'Cannot delete shift. Employees are assigned to it.');
        }

        $shift->delete();

        return redirect()->route('hr.shifts.index')
                         ->with('success', 'Shift deleted successfully.');
    }

    public function duplicate(HrShift $shift)
    {
        $newShift = $shift->replicate();
        $newShift->code = $shift->code . '_COPY';
        $newShift->name = $shift->name . ' (Copy)';
        $newShift->save();

        return redirect()->route('hr.shifts.edit', $newShift)
                         ->with('success', 'Shift duplicated. Please update the code and name.');
    }
}
