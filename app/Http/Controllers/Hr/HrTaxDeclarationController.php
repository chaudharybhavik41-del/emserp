<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrTaxDeclaration;
use App\Models\Hr\HrTaxDeclarationDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HrTaxDeclarationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $query = HrTaxDeclaration::with('employee')->latest();

        if ($request->filled('financial_year')) {
            $query->where('financial_year', $request->financial_year);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $declarations = $query->paginate(20)->withQueryString();

        return view('hr.tax.declarations.index', compact('declarations'));
    }

    public function create(): View
    {
        $employees = HrEmployee::active()->orderBy('employee_code')->get();
        return view('hr.tax.declarations.form', compact('employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);

        $declaration = DB::transaction(function () use ($validated) {
            $declaration = HrTaxDeclaration::create([
                'hr_employee_id' => $validated['hr_employee_id'],
                'financial_year' => $validated['financial_year'],
                'tax_regime' => $validated['tax_regime'],
                'status' => 'draft',
            ]);

            $this->syncDetails($declaration, $validated['details'] ?? []);

            return $declaration;
        });

        return redirect()->route('hr.tax.declarations.show', $declaration)
            ->with('success', 'Tax declaration created successfully.');
    }

    public function show(HrTaxDeclaration $declaration): View
    {
        $declaration->load(['employee', 'details', 'verifier']);

        return view('hr.tax.declarations.show', compact('declaration'));
    }

    public function edit(HrTaxDeclaration $declaration): View
    {
        $declaration->load('details');
        $employees = HrEmployee::active()->orderBy('employee_code')->get();

        return view('hr.tax.declarations.form', compact('declaration', 'employees'));
    }

    public function update(Request $request, HrTaxDeclaration $declaration): RedirectResponse
    {
        $validated = $this->validateData($request, $declaration->id);

        DB::transaction(function () use ($declaration, $validated) {
            $declaration->update([
                'hr_employee_id' => $validated['hr_employee_id'],
                'financial_year' => $validated['financial_year'],
                'tax_regime' => $validated['tax_regime'],
            ]);

            $this->syncDetails($declaration, $validated['details'] ?? []);
        });

        return redirect()->route('hr.tax.declarations.show', $declaration)
            ->with('success', 'Tax declaration updated successfully.');
    }

    public function destroy(HrTaxDeclaration $declaration): RedirectResponse
    {
        if (in_array($declaration->status, ['submitted', 'verified', 'locked'], true)) {
            return back()->with('error', 'Submitted/verified declaration cannot be deleted.');
        }

        $declaration->details()->delete();
        $declaration->delete();

        return redirect()->route('hr.tax.declarations.index')
            ->with('success', 'Tax declaration deleted successfully.');
    }

    public function submit(HrTaxDeclaration $declaration): RedirectResponse
    {
        $declaration->recalculateTotals();
        $declaration->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Tax declaration submitted successfully.');
    }

    public function verify(Request $request, HrTaxDeclaration $declaration): RedirectResponse
    {
        $validated = $request->validate([
            'details' => 'nullable|array',
            'details.*.id' => 'required|exists:hr_tax_declaration_details,id',
            'details.*.verified_amount' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($declaration, $validated) {
            foreach ($validated['details'] ?? [] as $row) {
                $detail = $declaration->details()->whereKey($row['id'])->first();
                if (!$detail) {
                    continue;
                }

                $verified = (float) ($row['verified_amount'] ?? $detail->declared_amount);
                $detail->update([
                    'verified_amount' => $verified,
                    'proof_verified' => true,
                ]);
            }

            $declaration->recalculateTotals();
            $declaration->update([
                'status' => 'verified',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);
        });

        return back()->with('success', 'Tax declaration verified successfully.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'hr_employee_id' => 'required|exists:hr_employees,id',
            'financial_year' => 'required|string|max:10',
            'tax_regime' => 'required|in:old,new',
            'details' => 'nullable|array',
            'details.*.section_code' => 'required_with:details|string|max:20',
            'details.*.section_name' => 'required_with:details|string|max:100',
            'details.*.investment_type' => 'required_with:details|string|max:100',
            'details.*.description' => 'nullable|string|max:500',
            'details.*.declared_amount' => 'required_with:details|numeric|min:0',
            'details.*.max_limit' => 'nullable|numeric|min:0',
        ]);
    }

    private function syncDetails(HrTaxDeclaration $declaration, array $details): void
    {
        $declaration->details()->delete();

        foreach ($details as $row) {
            if (empty($row['section_code']) || empty($row['investment_type'])) {
                continue;
            }

            HrTaxDeclarationDetail::create([
                'hr_tax_declaration_id' => $declaration->id,
                'section_code' => $row['section_code'],
                'section_name' => $row['section_name'] ?? $row['section_code'],
                'investment_type' => $row['investment_type'],
                'description' => $row['description'] ?? null,
                'declared_amount' => $row['declared_amount'] ?? 0,
                'max_limit' => $row['max_limit'] ?? 0,
                'verified_amount' => 0,
                'proof_submitted' => false,
                'proof_verified' => false,
            ]);
        }

        $declaration->refresh();
        $declaration->recalculateTotals();
    }
}
