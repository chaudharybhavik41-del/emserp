<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Hr\HrAttendancePolicy;
use App\Models\Hr\HrDesignation;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrGrade;
use App\Models\Hr\HrLeavePolicy;
use App\Models\Hr\HrSalaryStructure;
use App\Models\Hr\HrShift;
use App\Models\Hr\HrWorkLocation;
use App\Models\User;
use App\Services\Hr\EmployeeUserProvisioningService;
use App\Enums\Hr\EmployeeStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HrEmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:hr.employee.view')->only(['index', 'show']);
        $this->middleware('permission:hr.employee.create')->only(['create', 'store']);
        $this->middleware('permission:hr.employee.update')->only(['edit', 'update']);
        $this->middleware('permission:hr.employee.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $query = HrEmployee::with(['department', 'designation', 'grade', 'reportingManager'])
            ->orderBy('employee_code');

        // Filters
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('personal_mobile', 'like', "%{$search}%")
                    ->orWhere('official_email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            if ($status === 'active') {
                $query->active();
            } else {
                $query->where('status', $status);
            }
        }

        if ($department = $request->get('department_id')) {
            $query->where('department_id', $department);
        }

        if ($designation = $request->get('designation_id')) {
            $query->where('hr_designation_id', $designation);
        }

        if ($employmentType = $request->get('employment_type')) {
            $query->where('employment_type', $employmentType);
        }

        $employees = $query->paginate(25)->withQueryString();

        // For filters
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $designations = HrDesignation::where('is_active', true)->orderBy('name')->get();
        $statuses = EmployeeStatus::options();
        $employmentTypes = [
            'permanent' => 'Permanent',
            'probation' => 'Probation',
            'contract' => 'Contract',
            'trainee' => 'Trainee',
            'intern' => 'Intern',
            'consultant' => 'Consultant',
            'casual' => 'Casual',
            'daily_wage' => 'Daily Wage',
        ];

        // Stats
        $stats = [
            'total' => HrEmployee::count(),
            'active' => HrEmployee::active()->count(),
            'on_probation' => HrEmployee::onProbation()->count(),
            'left_this_month' => HrEmployee::whereMonth('date_of_leaving', now()->month)
                ->whereYear('date_of_leaving', now()->year)->count(),
        ];

        return view('hr.employees.index', compact(
            'employees', 'departments', 'designations', 'statuses', 
            'employmentTypes', 'stats'
        ));
    }

    public function create(): View
    {
        $employee = new HrEmployee();
        $employee->employee_code = HrEmployee::generateEmployeeCode();
        
        return view('hr.employees.form', $this->getFormData($employee));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateEmployee($request);

        DB::beginTransaction();
        try {
            // Handle photo upload
            if ($request->hasFile('photo')) {
                $validated['photo_path'] = $request->file('photo')
                    ->store('hr/employees/photos', 'public');
            }

            $validated['created_by'] = auth()->id();
            
            $employee = HrEmployee::create($validated);


            // Create/link user account + set user's primary department (if requested)
            if ($request->boolean('create_user_account')) {
                app(EmployeeUserProvisioningService::class)->provisionForEmployee($employee);
            }

DB::commit();

            return redirect()
                ->route('hr.employees.show', $employee)
                ->with('success', 'Employee created successfully.');

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Failed to create employee: ' . $e->getMessage());
        }
    }

    public function show(HrEmployee $employee): View
    {
        $employee->load([
            'department',
            'designation',
            'grade',
            'reportingManager',
            'subordinates',
            'workLocation',
            'defaultShift',
            'currentSalary.components',
            'leaveBalances.leaveType',
            'documents',
            'qualifications',
            'experiences',
            'dependents',
            'nominees',
            'bankAccounts',
            'assets' => fn($q) => $q->where('status', 'issued'),
            'trainings' => fn($q) => $q->latest()->limit(5),
        ]);

        // Recent attendance
        $recentAttendance = $employee->attendances()
            ->with('shift')
            ->orderByDesc('attendance_date')
            ->limit(10)
            ->get();

        // Leave balance for current year
        $leaveBalances = $employee->leaveBalances()
            ->with('leaveType')
            ->where('year', now()->year)
            ->get();

        // Recent payslips
        $recentPayrolls = $employee->payrolls()
            ->with('period')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        return view('hr.employees.show', compact(
            'employee', 'recentAttendance', 'leaveBalances', 'recentPayrolls'
        ));
    }

    public function edit(HrEmployee $employee): View
    {
        return view('hr.employees.form', $this->getFormData($employee));
    }

    public function update(Request $request, HrEmployee $employee): RedirectResponse
    {
        $validated = $this->validateEmployee($request, $employee->id);

        DB::beginTransaction();
        try {
            // Handle photo upload
            if ($request->hasFile('photo')) {
                // Delete old photo
                if ($employee->photo_path) {
                    Storage::disk('public')->delete($employee->photo_path);
                }
                $validated['photo_path'] = $request->file('photo')
                    ->store('hr/employees/photos', 'public');
            }

            $validated['updated_by'] = auth()->id();

            $employee->update($validated);


            // Sync linked user + user's primary department.
            // - If employee already has a linked user: keep it updated.
            // - If employee has no user: allow creating/linking a user when checkbox is checked.
            if ($employee->user_id || $request->boolean('create_user_account')) {
                app(EmployeeUserProvisioningService::class)->provisionForEmployee($employee);
            }

DB::commit();

            return redirect()
                ->route('hr.employees.show', $employee)
                ->with('success', 'Employee updated successfully.');

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Failed to update employee: ' . $e->getMessage());
        }
    }

    public function destroy(HrEmployee $employee): RedirectResponse
    {
        // Check if employee has payroll records
        if ($employee->payrolls()->exists()) {
            return back()->with('error', 'Cannot delete employee with payroll records. Consider marking as inactive instead.');
        }

        DB::beginTransaction();
        try {
            // Delete photo
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }

            $employee->delete();

            DB::commit();

            return redirect()
                ->route('hr.employees.index')
                ->with('success', 'Employee deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete employee: ' . $e->getMessage());
        }
    }

    // Additional Actions

    public function confirm(HrEmployee $employee): RedirectResponse
    {
        if ($employee->confirmation_date) {
            return back()->with('error', 'Employee is already confirmed.');
        }

        $employee->update([
            'confirmation_date' => now(),
            'employment_type' => 'permanent',
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Employee confirmed successfully.');
    }

    public function separation(Request $request, HrEmployee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'date_of_leaving' => 'required|date',
            'leaving_reason' => 'required|string|max:255',
            'status' => 'required|in:resigned,terminated,absconded,retired',
        ]);

        $employee->update([
            'date_of_leaving' => $validated['date_of_leaving'],
            'leaving_reason' => $validated['leaving_reason'],
            'status' => $validated['status'],
            'is_active' => false,
            'updated_by' => auth()->id(),
        ]);

        // Deactivate user account
        if ($employee->user) {
            $employee->user->update(['is_active' => false]);
        }

        return back()->with('success', 'Employee separation processed successfully.');
    }

    public function idCard(HrEmployee $employee): View
    {
        $employee->load(['department', 'designation']);
        return view('hr.employees.id-card', compact('employee'));
    }

    // Private Methods

    private function getFormData(HrEmployee $employee): array
    {
        return [
            'employee' => $employee,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'designations' => HrDesignation::where('is_active', true)->orderBy('name')->get(),
            'grades' => HrGrade::where('is_active', true)->orderBy('level')->get(),
            'shifts' => HrShift::where('is_active', true)->orderBy('name')->get(),
            'locations' => HrWorkLocation::where('is_active', true)->orderBy('name')->get(),
            'attendancePolicies' => HrAttendancePolicy::where('is_active', true)->get(),
            'leavePolicies' => HrLeavePolicy::where('is_active', true)->get(),
            'salaryStructures' => HrSalaryStructure::where('is_active', true)->get(),
            'managers' => HrEmployee::active()->orderBy('first_name')->get(),
            'statuses' => EmployeeStatus::options(),
            'employmentTypes' => [
                'permanent' => 'Permanent',
                'probation' => 'Probation',
                'contract' => 'Contract',
                'trainee' => 'Trainee',
                'intern' => 'Intern',
                'consultant' => 'Consultant',
                'casual' => 'Casual',
                'daily_wage' => 'Daily Wage',
            ],
            'employeeCategories' => [
                'staff' => 'Staff',
                'worker' => 'Worker',
                'supervisor' => 'Supervisor',
                'manager' => 'Manager',
                'executive' => 'Executive',
                'contractor_employee' => 'Contractor Employee',
            ],
            'genders' => [
                'male' => 'Male',
                'female' => 'Female',
                'other' => 'Other',
            ],
            'maritalStatuses' => [
                'single' => 'Single',
                'married' => 'Married',
                'divorced' => 'Divorced',
                'widowed' => 'Widowed',
            ],
            'bloodGroups' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
            'states' => $this->getIndianStates(),
        ];
    }

    private function validateEmployee(Request $request, ?int $employeeId = null): array
    {
        $uniqueRule = $employeeId ? "unique:hr_employees,employee_code,{$employeeId}" : 'unique:hr_employees,employee_code';

        $rules = [
            'employee_code' => ['required', 'string', 'max:20', $uniqueRule],
            'biometric_id' => ['nullable', 'string', 'max:50'],
            'card_number' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:200'],
            'mother_name' => ['nullable', 'string', 'max:200'],
            'spouse_name' => ['nullable', 'string', 'max:200'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
            'caste_category' => ['nullable', 'string', 'max:50'],
            
            'personal_email' => ['nullable', 'email', 'max:150'],
            'official_email' => ['nullable', 'email', 'max:150'],
            'personal_mobile' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:200'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            
            'present_address' => ['nullable', 'string'],
            'present_city' => ['nullable', 'string', 'max:100'],
            'present_state' => ['nullable', 'string', 'max:100'],
            'present_pincode' => ['nullable', 'string', 'max:10'],
            'permanent_address' => ['nullable', 'string'],
            'permanent_city' => ['nullable', 'string', 'max:100'],
            'permanent_state' => ['nullable', 'string', 'max:100'],
            'permanent_pincode' => ['nullable', 'string', 'max:10'],
            'address_same_as_present' => ['nullable', 'boolean'],
            
            'pan_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'aadhar_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{12}$/'],
            'passport_number' => ['nullable', 'string', 'max:20'],
            'passport_expiry' => ['nullable', 'date'],
            'driving_license' => ['nullable', 'string', 'max:30'],
            'dl_expiry' => ['nullable', 'date'],
            'voter_id' => ['nullable', 'string', 'max:30'],
            
            'date_of_joining' => ['required', 'date'],
            'confirmation_date' => ['nullable', 'date', 'after_or_equal:date_of_joining'],
            'employment_type' => ['required', 'in:permanent,probation,contract,trainee,intern,consultant,casual,daily_wage'],
            'employee_category' => ['required', 'in:staff,worker,supervisor,manager,executive,contractor_employee'],
            'probation_period_months' => ['nullable', 'integer', 'min:0', 'max:24'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:180'],
            
            'department_id' => ['nullable', 'exists:departments,id'],
            'hr_designation_id' => ['nullable', 'exists:hr_designations,id'],
            'hr_grade_id' => ['nullable', 'exists:hr_grades,id'],
            'reporting_to' => ['nullable', 'exists:hr_employees,id'],
            'cost_center' => ['nullable', 'string', 'max:50'],
            'work_location_id' => ['nullable', 'exists:hr_work_locations,id'],
            
            'default_shift_id' => ['nullable', 'exists:hr_shifts,id'],
            'hr_attendance_policy_id' => ['nullable', 'exists:hr_attendance_policies,id'],
            'hr_leave_policy_id' => ['nullable', 'exists:hr_leave_policies,id'],
            'overtime_applicable' => ['nullable', 'boolean'],
            'attendance_mode' => ['nullable', 'in:biometric,manual,both'],
            
            'hr_salary_structure_id' => ['nullable', 'exists:hr_salary_structures,id'],
            'payment_mode' => ['nullable', 'in:bank_transfer,cheque,cash'],
            'pf_applicable' => ['nullable', 'boolean'],
            'pf_number' => ['nullable', 'string', 'max:30'],
            'pf_join_date' => ['nullable', 'date'],
            'eps_applicable' => ['nullable', 'boolean'],
            'esi_applicable' => ['nullable', 'boolean'],
            'esi_number' => ['nullable', 'string', 'max:30'],
            'esi_join_date' => ['nullable', 'date'],
            'pt_applicable' => ['nullable', 'boolean'],
            'pt_state' => ['nullable', 'string', 'max:50'],
            'lwf_applicable' => ['nullable', 'boolean'],
            'tds_applicable' => ['nullable', 'boolean'],
            'tax_regime' => ['nullable', 'in:old,new'],
            
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_branch' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_ifsc' => ['nullable', 'string', 'max:15'],
            'bank_account_type' => ['nullable', 'in:savings,current,salary'],
            
            'gratuity_applicable' => ['nullable', 'boolean'],
            'health_insurance_enrolled' => ['nullable', 'boolean'],
            'health_insurance_policy_no' => ['nullable', 'string', 'max:50'],
            'sum_insured' => ['nullable', 'numeric', 'min:0'],
            
            'highest_qualification' => ['nullable', 'string', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:100'],
            'total_experience_months' => ['nullable', 'integer', 'min:0'],
            
            'status' => ['nullable', 'in:active,inactive,resigned,terminated,absconded,retired,deceased'],
            'is_active' => ['nullable', 'boolean'],
            
            'photo' => ['nullable', 'image', 'max:2048'],
        ];

        // If we are creating/linking a login user account (or already linked), require Official Email and Department.
        // This prevents partial records that cannot be linked to users/departments.
        $existingUserId = null;
        if ($employeeId) {
            $existingUserId = HrEmployee::whereKey($employeeId)->value('user_id');
        }

        $needsLogin = $request->boolean('create_user_account') || !is_null($existingUserId);

        if ($needsLogin) {
            // Official Email must exist to create/link a user.
            if (isset($rules['official_email']) && is_array($rules['official_email'])) {
                $rules['official_email'][] = 'required';
            }

            // Department must exist so we can set user's primary department.
            if (isset($rules['department_id']) && is_array($rules['department_id'])) {
                // Replace nullable with required (keeping exists rule).
                $rules['department_id'] = array_values(array_unique(array_merge(['required'], $rules['department_id'])));
            }
        }

        return $request->validate($rules);

    }

    private function getIndianStates(): array
    {
        return [
            'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
            'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
            'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
            'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
            'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
            'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
            'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry',
        ];
    }
}
