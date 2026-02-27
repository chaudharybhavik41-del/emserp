<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrWorkLocation;
use Illuminate\Http\Request;

class HrWorkLocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = HrWorkLocation::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $locations = $query
            ->withCount('employees')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('hr.work-locations.index', compact('locations'));
    }

    public function create()
    {
        return view('hr.work-locations.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_work_locations,code',
            'name' => 'required|string|max:150',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric|min:-90|max:90',
            'longitude' => 'nullable|numeric|min:-180|max:180',
            'geofence_radius_meters' => 'nullable|integer|min:50|max:5000',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);

        // DB column is NOT NULL with default 100; avoid writing null
        $validated['geofence_radius_meters'] = (int) ($validated['geofence_radius_meters'] ?? 100);

        HrWorkLocation::create($validated);

        return redirect()->route('hr.work-locations.index')
            ->with('success', 'Work location created successfully.');
    }

    public function edit(HrWorkLocation $workLocation)
    {
        return view('hr.work-locations.form', ['location' => $workLocation]);
    }

    public function update(Request $request, HrWorkLocation $workLocation)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_work_locations,code,' . $workLocation->id,
            'name' => 'required|string|max:150',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric|min:-90|max:90',
            'longitude' => 'nullable|numeric|min:-180|max:180',
            'geofence_radius_meters' => 'nullable|integer|min:50|max:5000',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);

        // If field was left empty, do not overwrite with null
        if (!isset($validated['geofence_radius_meters']) || $validated['geofence_radius_meters'] === null) {
            unset($validated['geofence_radius_meters']);
        } else {
            $validated['geofence_radius_meters'] = (int) $validated['geofence_radius_meters'];
        }

        $workLocation->update($validated);

        return redirect()->route('hr.work-locations.index')
            ->with('success', 'Work location updated successfully.');
    }

    public function destroy(HrWorkLocation $workLocation)
    {
        if ($workLocation->employees()->exists()) {
            return back()->with('error', 'Cannot delete location. Employees are assigned to it.');
        }

        $workLocation->delete();

        return redirect()->route('hr.work-locations.index')
            ->with('success', 'Work location deleted successfully.');
    }
}
