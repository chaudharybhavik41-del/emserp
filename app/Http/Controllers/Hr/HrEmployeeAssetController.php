<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrEmployeeAssetController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(HrEmployee $employee, Request $request): View
    {
        $assets = $employee->assets()->latest()->paginate(20)->withQueryString();

        $editing = null;
        if ($request->filled('edit')) {
            $editing = $employee->assets()->whereKey($request->integer('edit'))->first();
        }

        return view('hr.employees.assets.index', compact('employee', 'assets', 'editing'));
    }

    public function create(HrEmployee $employee): RedirectResponse
    {
        return redirect()->route('hr.employees.assets.index', $employee)->with('show_form', true);
    }

    public function store(Request $request, HrEmployee $employee): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['hr_employee_id'] = $employee->id;
        $validated['issued_by'] = auth()->id();

        HrEmployeeAsset::create($validated);

        return redirect()->route('hr.employees.assets.index', $employee)
            ->with('success', 'Asset assigned successfully.');
    }

    public function edit(HrEmployee $employee, HrEmployeeAsset $asset): RedirectResponse
    {
        $this->guardOwnership($employee, $asset);

        return redirect()->route('hr.employees.assets.index', ['employee' => $employee->id, 'edit' => $asset->id]);
    }

    public function update(Request $request, HrEmployee $employee, HrEmployeeAsset $asset): RedirectResponse
    {
        $this->guardOwnership($employee, $asset);

        $asset->update($this->validateData($request));

        return redirect()->route('hr.employees.assets.index', $employee)
            ->with('success', 'Asset updated successfully.');
    }

    public function destroy(HrEmployee $employee, HrEmployeeAsset $asset): RedirectResponse
    {
        $this->guardOwnership($employee, $asset);

        $asset->delete();

        return redirect()->route('hr.employees.assets.index', $employee)
            ->with('success', 'Asset removed successfully.');
    }

    public function returnAsset(HrEmployee $employee, HrEmployeeAsset $asset): RedirectResponse
    {
        $this->guardOwnership($employee, $asset);

        $asset->update([
            'status' => 'returned',
            'return_date' => now()->toDateString(),
            'returned_to' => auth()->id(),
            'condition_at_return' => request('condition_at_return', 'good'),
            'remarks' => request('remarks') ?: $asset->remarks,
        ]);

        return redirect()->route('hr.employees.assets.index', $employee)
            ->with('success', 'Asset marked as returned.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'asset_type' => 'required|string|max:50',
            'asset_name' => 'required|string|max:150',
            'asset_code' => 'nullable|string|max:50',
            'serial_number' => 'nullable|string|max:100',
            'make' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'issued_date' => 'required|date',
            'return_date' => 'nullable|date|after_or_equal:issued_date',
            'asset_value' => 'nullable|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'status' => 'required|in:issued,returned,lost,damaged',
            'condition_at_issue' => 'required|in:new,good,fair',
            'condition_at_return' => 'nullable|in:good,fair,damaged',
            'remarks' => 'nullable|string',
        ]);

        $validated['asset_value'] = $validated['asset_value'] ?? 0;
        $validated['deposit_amount'] = $validated['deposit_amount'] ?? 0;

        return $validated;
    }

    private function guardOwnership(HrEmployee $employee, HrEmployeeAsset $asset): void
    {
        if ($asset->hr_employee_id !== $employee->id) {
            abort(404);
        }
    }
}
