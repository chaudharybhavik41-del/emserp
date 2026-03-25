<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrCandidate;
use App\Models\Hr\HrEmployee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HrCandidateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:hr.candidate.view')->only(['index', 'show', 'downloadResume']);
        $this->middleware('permission:hr.candidate.create')->only(['create', 'store']);
        $this->middleware('permission:hr.candidate.update')->only(['edit', 'update']);
        $this->middleware('permission:hr.employee.create')->only(['convertToEmployee']);
        $this->middleware('permission:hr.candidate.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $query = HrCandidate::query()
            ->with('convertedEmployee')
            ->latest('id');

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($candidateQuery) use ($search) {
                $candidateQuery->where('candidate_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('position_applied', 'like', "%{$search}%")
                    ->orWhere('current_company', 'like', "%{$search}%")
                    ->orWhere('current_designation', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($source = trim((string) $request->string('source'))) {
            $query->where('source', $source);
        }

        $candidates = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => HrCandidate::query()->count(),
            'new' => HrCandidate::query()->where('status', 'new')->count(),
            'shortlisted' => HrCandidate::query()->where('status', 'shortlisted')->count(),
            'interviewed' => HrCandidate::query()->where('status', 'interviewed')->count(),
        ];

        $sources = HrCandidate::query()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->orderBy('source')
            ->distinct()
            ->pluck('source');

        return view('hr.candidates.index', [
            'candidates' => $candidates,
            'stats' => $stats,
            'statuses' => HrCandidate::statusOptions(),
            'sources' => $sources,
        ]);
    }

    public function create(): View
    {
        $candidate = new HrCandidate;
        $candidate->candidate_code = HrCandidate::generateCandidateCode();
        $candidate->status = 'new';

        return view('hr.candidates.form', [
            'candidate' => $candidate,
            'statuses' => HrCandidate::statusOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCandidate($request);
        $storedResumePath = null;

        DB::beginTransaction();

        try {
            if ($request->hasFile('resume')) {
                $file = $request->file('resume');
                $validated['resume_path'] = $storedResumePath = $file->store('hr/candidates/resumes', 'local');
                $validated['resume_file_name'] = $file->getClientOriginalName();
                $validated['resume_file_size'] = $file->getSize();
                $validated['resume_mime_type'] = $file->getClientMimeType();
            }

            $validated['created_by'] = auth()->id();
            $validated['company_id'] = $validated['company_id'] ?? 1;

            $candidate = $this->createCandidateWithRetry($validated);

            DB::commit();

            return redirect()
                ->route('hr.candidates.show', $candidate)
                ->with('success', 'Candidate saved successfully.');
        } catch (\Throwable $throwable) {
            DB::rollBack();
            if ($storedResumePath) {
                Storage::disk('local')->delete($storedResumePath);
            }

            return back()
                ->withInput()
                ->with('error', 'Failed to save candidate: '.$throwable->getMessage());
        }
    }

    public function show(HrCandidate $candidate): View
    {
        $candidate->loadMissing('convertedEmployee');

        return view('hr.candidates.show', [
            'candidate' => $candidate,
            'statuses' => HrCandidate::statusOptions(),
        ]);
    }

    public function edit(HrCandidate $candidate): View
    {
        return view('hr.candidates.form', [
            'candidate' => $candidate,
            'statuses' => HrCandidate::statusOptions(),
        ]);
    }

    public function update(Request $request, HrCandidate $candidate): RedirectResponse
    {
        $validated = $this->validateCandidate($request, $candidate);
        $oldResumePath = $candidate->resume_path;
        $storedResumePath = null;

        DB::beginTransaction();

        try {
            if ($request->hasFile('resume')) {
                $file = $request->file('resume');
                $validated['resume_path'] = $storedResumePath = $file->store('hr/candidates/resumes', 'local');
                $validated['resume_file_name'] = $file->getClientOriginalName();
                $validated['resume_file_size'] = $file->getSize();
                $validated['resume_mime_type'] = $file->getClientMimeType();
            }

            $validated['updated_by'] = auth()->id();

            $candidate->update($validated);

            if ($storedResumePath && $oldResumePath && $oldResumePath !== $storedResumePath) {
                DB::afterCommit(fn () => Storage::disk('local')->delete($oldResumePath));
            }

            DB::commit();

            return redirect()
                ->route('hr.candidates.show', $candidate)
                ->with('success', 'Candidate updated successfully.');
        } catch (\Throwable $throwable) {
            DB::rollBack();
            if ($storedResumePath) {
                Storage::disk('local')->delete($storedResumePath);
            }

            return back()
                ->withInput()
                ->with('error', 'Failed to update candidate: '.$throwable->getMessage());
        }
    }

    public function destroy(HrCandidate $candidate): RedirectResponse
    {
        $candidate->delete();

        return redirect()
            ->route('hr.candidates.index')
            ->with('success', 'Candidate deleted successfully.');
    }

    public function convertToEmployee(HrCandidate $candidate): RedirectResponse
    {
        $candidate->loadMissing('convertedEmployee');

        if ($candidate->convertedEmployee) {
            return redirect()
                ->route('hr.employees.show', $candidate->convertedEmployee)
                ->with('info', 'This candidate is already converted to an employee.');
        }

        return redirect()->to(
            route('hr.employees.create').'?candidate_id='.$candidate->id
        );
    }

    protected function createCandidateWithRetry(array $validated): HrCandidate
    {
        $attempts = 0;

        do {
            $attempts++;
            $candidateData = $validated;
            $candidateData['candidate_code'] = HrCandidate::generateCandidateCode();

            try {
                return HrCandidate::create($candidateData);
            } catch (QueryException $exception) {
                if ($attempts >= 5 || ! $this->isCandidateCodeCollision($exception)) {
                    throw $exception;
                }
            }
        } while ($attempts < 5);

        throw new \RuntimeException('Unable to generate a unique candidate code.');
    }

    protected function isCandidateCodeCollision(QueryException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'candidate_code')
            || str_contains($message, 'hr_candidates_candidate_code_unique')
            || in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }

    public function downloadResume(HrCandidate $candidate)
    {
        abort_if(! $candidate->resume_path, 404);

        return Storage::disk('local')->download(
            $candidate->resume_path,
            $candidate->resume_file_name ?: basename($candidate->resume_path)
        );
    }

    protected function validateCandidate(Request $request, ?HrCandidate $candidate = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('hr_candidates', 'email')->ignore($candidate?->id),
            ],
            'phone' => ['required', 'string', 'max:20'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'current_location' => ['nullable', 'string', 'max:150'],
            'position_applied' => ['nullable', 'string', 'max:150'],
            'current_company' => ['nullable', 'string', 'max:150'],
            'current_designation' => ['nullable', 'string', 'max:150'],
            'total_experience_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'current_ctc' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'expected_ctc' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(array_keys(HrCandidate::statusOptions()))],
            'interview_date' => ['nullable', 'date'],
            'skills' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:8192'],
        ]);
    }
}
