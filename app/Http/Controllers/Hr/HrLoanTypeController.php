<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrLoanType;
use Illuminate\Http\Request;

class HrLoanTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = HrLoanType::query();

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

        $loanTypes = $query->withCount('loans')
                           ->orderBy('name')
                           ->paginate(20)
                           ->withQueryString();

        return view('hr.loan-types.index', compact('loanTypes'));
    }

    public function create()
    {
        return view('hr.loan-types.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_loan_types,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'max_amount' => 'nullable|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_tenure_months' => 'nullable|integer|min:1|max:120',
            'min_tenure_months' => 'nullable|integer|min:1|max:120',
            'interest_rate' => 'nullable|numeric|min:0|max:50',
            'interest_type' => 'nullable|in:simple,compound,none',
            'max_emi_percentage' => 'nullable|numeric|min:0|max:100',
            'processing_fee_percentage' => 'nullable|numeric|min:0|max:10',
            'requires_guarantor' => 'boolean',
            'requires_approval' => 'boolean',
            'min_service_months' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['requires_guarantor'] = $request->boolean('requires_guarantor', false);
        $validated['requires_approval'] = $request->boolean('requires_approval', true);

        HrLoanType::create($validated);

        return redirect()->route('hr.loan-types.index')
                         ->with('success', 'Loan type created successfully.');
    }

    public function edit(HrLoanType $loanType)
    {
        return view('hr.loan-types.form', compact('loanType'));
    }

    public function update(Request $request, HrLoanType $loanType)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_loan_types,code,' . $loanType->id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'max_amount' => 'nullable|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_tenure_months' => 'nullable|integer|min:1|max:120',
            'min_tenure_months' => 'nullable|integer|min:1|max:120',
            'interest_rate' => 'nullable|numeric|min:0|max:50',
            'interest_type' => 'nullable|in:simple,compound,none',
            'max_emi_percentage' => 'nullable|numeric|min:0|max:100',
            'processing_fee_percentage' => 'nullable|numeric|min:0|max:10',
            'requires_guarantor' => 'boolean',
            'requires_approval' => 'boolean',
            'min_service_months' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['requires_guarantor'] = $request->boolean('requires_guarantor', false);
        $validated['requires_approval'] = $request->boolean('requires_approval', true);

        $loanType->update($validated);

        return redirect()->route('hr.loan-types.index')
                         ->with('success', 'Loan type updated successfully.');
    }

    public function destroy(HrLoanType $loanType)
    {
        if ($loanType->loans()->exists()) {
            return back()->with('error', 'Cannot delete loan type. It has loans assigned.');
        }

        $loanType->delete();

        return redirect()->route('hr.loan-types.index')
                         ->with('success', 'Loan type deleted successfully.');
    }
}
