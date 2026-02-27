<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeBankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HrEmployeeBankAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(HrEmployee $employee, Request $request): View
    {
        $accounts = $employee->bankAccounts()->latest()->paginate(20)->withQueryString();

        $editing = null;
        if ($request->filled('edit')) {
            $editing = $employee->bankAccounts()->whereKey($request->integer('edit'))->first();
        }

        return view('hr.employees.bank-accounts.index', compact('employee', 'accounts', 'editing'));
    }

    public function create(HrEmployee $employee): RedirectResponse
    {
        return redirect()->route('hr.employees.bank-accounts.index', $employee)->with('show_form', true);
    }

    public function store(Request $request, HrEmployee $employee): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['hr_employee_id'] = $employee->id;

        if ($request->hasFile('cancelled_cheque')) {
            $validated['cancelled_cheque_path'] = $request->file('cancelled_cheque')->store('hr/employees/bank-accounts', 'public');
        }

        if ($validated['is_primary']) {
            $employee->bankAccounts()->update(['is_primary' => false]);
        }

        HrEmployeeBankAccount::create($validated);

        return redirect()->route('hr.employees.bank-accounts.index', $employee)
            ->with('success', 'Bank account added successfully.');
    }

    public function edit(HrEmployee $employee, HrEmployeeBankAccount $bankAccount): RedirectResponse
    {
        $this->guardOwnership($employee, $bankAccount);

        return redirect()->route('hr.employees.bank-accounts.index', ['employee' => $employee->id, 'edit' => $bankAccount->id]);
    }

    public function update(Request $request, HrEmployee $employee, HrEmployeeBankAccount $bankAccount): RedirectResponse
    {
        $this->guardOwnership($employee, $bankAccount);

        $validated = $this->validateData($request, $bankAccount->id);

        if ($request->hasFile('cancelled_cheque')) {
            if ($bankAccount->cancelled_cheque_path) {
                Storage::disk('public')->delete($bankAccount->cancelled_cheque_path);
            }
            $validated['cancelled_cheque_path'] = $request->file('cancelled_cheque')->store('hr/employees/bank-accounts', 'public');
        }

        if ($validated['is_primary']) {
            $employee->bankAccounts()->whereKeyNot($bankAccount->id)->update(['is_primary' => false]);
        }

        $bankAccount->update($validated);

        return redirect()->route('hr.employees.bank-accounts.index', $employee)
            ->with('success', 'Bank account updated successfully.');
    }

    public function destroy(HrEmployee $employee, HrEmployeeBankAccount $bankAccount): RedirectResponse
    {
        $this->guardOwnership($employee, $bankAccount);

        if ($bankAccount->cancelled_cheque_path) {
            Storage::disk('public')->delete($bankAccount->cancelled_cheque_path);
        }

        $bankAccount->delete();

        return redirect()->route('hr.employees.bank-accounts.index', $employee)
            ->with('success', 'Bank account removed successfully.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:150',
            'branch_name' => 'nullable|string|max:150',
            'account_number' => 'required|string|max:30',
            'ifsc_code' => 'required|string|max:15',
            'account_holder_name' => 'required|string|max:200',
            'account_type' => 'required|in:savings,current,salary',
            'is_primary' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
            'cancelled_cheque' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        $validated['ifsc_code'] = strtoupper($validated['ifsc_code']);
        $validated['is_primary'] = $request->boolean('is_primary');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_verified'] = $request->boolean('is_verified');

        if ($validated['is_verified']) {
            $validated['verified_by'] = auth()->id();
            $validated['verified_at'] = now();
        } else {
            $validated['verified_by'] = null;
            $validated['verified_at'] = null;
        }

        return $validated;
    }

    private function guardOwnership(HrEmployee $employee, HrEmployeeBankAccount $bankAccount): void
    {
        if ($bankAccount->hr_employee_id !== $employee->id) {
            abort(404);
        }
    }
}
