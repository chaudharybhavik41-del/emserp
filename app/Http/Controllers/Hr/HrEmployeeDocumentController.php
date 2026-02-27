<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HrEmployeeDocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(HrEmployee $employee, Request $request): View
    {
        $documents = $employee->documents()->latest()->paginate(20)->withQueryString();

        return view('hr.employees.documents.index', compact('employee', 'documents'));
    }

    public function create(HrEmployee $employee): RedirectResponse
    {
        return redirect()->route('hr.employees.documents.index', $employee)->with('show_form', true);
    }

    public function store(Request $request, HrEmployee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => 'required|in:photo,aadhar,pan,passport,driving_license,voter_id,birth_certificate,education_certificate,experience_letter,relieving_letter,offer_letter,appointment_letter,salary_slip,bank_statement,address_proof,police_verification,medical_certificate,other',
            'document_name' => 'required|string|max:150',
            'document_number' => 'nullable|string|max:100',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'issuing_authority' => 'nullable|string|max:150',
            'remarks' => 'nullable|string',
            'document_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:8192',
        ]);

        $file = $request->file('document_file');
        $path = $file->store('hr/employees/documents', 'public');

        HrEmployeeDocument::create([
            'hr_employee_id' => $employee->id,
            'document_type' => $validated['document_type'],
            'document_name' => $validated['document_name'],
            'document_number' => $validated['document_number'] ?? null,
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'issuing_authority' => $validated['issuing_authority'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'is_verified' => false,
            'remarks' => $validated['remarks'] ?? null,
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->route('hr.employees.documents.index', $employee)
            ->with('success', 'Document uploaded successfully.');
    }

    public function destroy(HrEmployee $employee, HrEmployeeDocument $document): RedirectResponse
    {
        $this->guardOwnership($employee, $document);

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->route('hr.employees.documents.index', $employee)
            ->with('success', 'Document deleted successfully.');
    }

    public function verify(HrEmployee $employee, HrEmployeeDocument $document): RedirectResponse
    {
        $this->guardOwnership($employee, $document);

        if ($document->is_verified) {
            $document->update([
                'is_verified' => false,
                'verified_by' => null,
                'verified_at' => null,
            ]);

            return redirect()->route('hr.employees.documents.index', $employee)
                ->with('success', 'Document verification removed.');
        }

        $document->update([
            'is_verified' => true,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return redirect()->route('hr.employees.documents.index', $employee)
            ->with('success', 'Document verified successfully.');
    }

    private function guardOwnership(HrEmployee $employee, HrEmployeeDocument $document): void
    {
        if ($document->hr_employee_id !== $employee->id) {
            abort(404);
        }
    }
}
