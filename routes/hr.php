<?php

use Illuminate\Support\Facades\Route;

// Dashboard & Core
use App\Http\Controllers\Hr\HrDashboardController;
use App\Http\Controllers\Hr\HrEmployeeController;

// Self Service
use App\Http\Controllers\Hr\HrMyLeaveController;

// Masters
use App\Http\Controllers\Hr\HrDesignationController;
use App\Http\Controllers\Hr\HrGradeController;
use App\Http\Controllers\Hr\HrShiftController;
use App\Http\Controllers\Hr\HrWorkLocationController;
use App\Http\Controllers\Hr\HrAttendancePolicyController;
use App\Http\Controllers\Hr\HrLeaveTypeController;
use App\Http\Controllers\Hr\HrLeavePolicyController;
use App\Http\Controllers\Hr\HrHolidayCalendarController;
use App\Http\Controllers\Hr\HrHolidayController;
use App\Http\Controllers\Hr\HrSalaryComponentController;
use App\Http\Controllers\Hr\HrSalaryStructureController;
use App\Http\Controllers\Hr\HrLoanTypeController;

// Employee Sub-modules
use App\Http\Controllers\Hr\HrEmployeeDocumentController;
use App\Http\Controllers\Hr\HrEmployeeQualificationController;
use App\Http\Controllers\Hr\HrEmployeeExperienceController;
use App\Http\Controllers\Hr\HrEmployeeDependentController;
use App\Http\Controllers\Hr\HrEmployeeNomineeController;
use App\Http\Controllers\Hr\HrEmployeeBankAccountController;
use App\Http\Controllers\Hr\HrEmployeeAssetController;

// Operations
use App\Http\Controllers\Hr\HrAttendanceController;
use App\Http\Controllers\Hr\HrAttendanceBulkEntryController;
use App\Http\Controllers\Hr\HrLeaveController;
use App\Http\Controllers\Hr\HrLeaveApplicationController;
use App\Http\Controllers\Hr\HrSalaryController;
use App\Http\Controllers\Hr\HrPayrollController;

// Loans & Advances
use App\Http\Controllers\Hr\HrLoanController;
use App\Http\Controllers\Hr\HrEmployeeLoanController;
use App\Http\Controllers\Hr\HrAdvanceController;
use App\Http\Controllers\Hr\HrSalaryAdvanceController;

// Tax
use App\Http\Controllers\Hr\HrTaxController;
use App\Http\Controllers\Hr\HrTaxDeclarationController;

// Reports & Settings
use App\Http\Controllers\Hr\HrReportController;
use App\Http\Controllers\Hr\HrSettingsController;
use App\Http\Controllers\Hr\HrPfSlabController;
use App\Http\Controllers\Hr\HrEsiSlabController;
use App\Http\Controllers\Hr\HrPtSlabController;
use App\Http\Controllers\Hr\HrTdsSlabController;
use App\Http\Controllers\Hr\HrLwfSlabController;

/*
|--------------------------------------------------------------------------
| HR Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('hr')->name('hr.')->group(function () {

    // Dashboard
    Route::get('/', [HrDashboardController::class, 'index'])->name('dashboard');

// ===========================
// SELF SERVICE (Employee)
// ===========================
// Any authenticated user with a linked HR Employee profile (hr_employees.user_id)
// can apply and track their own leave.
Route::prefix('my')->name('my.')->group(function () {
    Route::prefix('leave')->name('leave.')->group(function () {
        Route::get('/', [HrMyLeaveController::class, 'index'])->name('index');
        Route::get('balance', [HrMyLeaveController::class, 'balance'])->name('balance');

        Route::get('create', [HrMyLeaveController::class, 'create'])->name('create');
        Route::post('/', [HrMyLeaveController::class, 'store'])->name('store');

        Route::post('{application}/cancel', [HrMyLeaveController::class, 'cancel'])->name('cancel');
    });
});

    // ===========================
    // MASTERS
    // ===========================

    // Designations
    Route::resource('designations', HrDesignationController::class)->except(['show']);

    // Grades
    Route::resource('grades', HrGradeController::class)->except(['show']);

    // Shifts
    Route::resource('shifts', HrShiftController::class);
    Route::post('shifts/{shift}/duplicate', [HrShiftController::class, 'duplicate'])->name('shifts.duplicate');

    // Work Locations
    Route::resource('work-locations', HrWorkLocationController::class)->except(['show']);

    // Attendance Policies
    Route::resource('attendance-policies', HrAttendancePolicyController::class)->except(['show']);

    // Leave Types
    Route::resource('leave-types', HrLeaveTypeController::class)->except(['show']);

    // Leave Policies
    Route::resource('leave-policies', HrLeavePolicyController::class);
    Route::post('leave-policies/{leavePolicy}/duplicate', [HrLeavePolicyController::class, 'duplicate'])->name('leave-policies.duplicate');

    // Holiday Calendars
    Route::resource('holiday-calendars', HrHolidayCalendarController::class);
    Route::post('holiday-calendars/copy-to-next-year', [HrHolidayCalendarController::class, 'copyToNextYear'])->name('holiday-calendars.copy-to-next-year');

    // Salary Components
    Route::resource('salary-components', HrSalaryComponentController::class)->except(['show']);

    // Salary Structures
    Route::resource('salary-structures', HrSalaryStructureController::class);
    Route::post('salary-structures/{salaryStructure}/duplicate', [HrSalaryStructureController::class, 'duplicate'])
        ->name('salary-structures.duplicate');

    // Loan Types
    Route::resource('loan-types', HrLoanTypeController::class)->except(['show']);

    // ===========================
    // EMPLOYEE MANAGEMENT
    // ===========================

    Route::resource('employees', HrEmployeeController::class);

    // Employee Additional Actions
    Route::prefix('employees/{employee}')->name('employees.')->group(function () {
        Route::post('confirm', [HrEmployeeController::class, 'confirm'])->name('confirm');
        Route::post('separation', [HrEmployeeController::class, 'separation'])->name('separation');
        Route::get('id-card', [HrEmployeeController::class, 'idCard'])->name('id-card');

        // Documents
        Route::resource('documents', HrEmployeeDocumentController::class)->except(['show', 'edit', 'update']);
        Route::post('documents/{document}/verify', [HrEmployeeDocumentController::class, 'verify'])->name('documents.verify');

        // Qualifications
        Route::resource('qualifications', HrEmployeeQualificationController::class)->except(['show']);

        // Experience
        Route::resource('experiences', HrEmployeeExperienceController::class)->except(['show']);

        // Dependents
        Route::resource('dependents', HrEmployeeDependentController::class)->except(['show']);

        // Nominees
        Route::resource('nominees', HrEmployeeNomineeController::class)->except(['show']);

        // Bank Accounts
        Route::resource('bank-accounts', HrEmployeeBankAccountController::class)->except(['show']);

        // Assets
        Route::resource('assets', HrEmployeeAssetController::class)->except(['show']);
        Route::post('assets/{asset}/return', [HrEmployeeAssetController::class, 'returnAsset'])->name('assets.return');

        // Salary
        Route::get('salary', [HrSalaryController::class, 'employeeSalary'])->name('salary.show');
        Route::get('salary/create', [HrSalaryController::class, 'createEmployeeSalary'])->name('salary.create');
        Route::post('salary', [HrSalaryController::class, 'storeEmployeeSalary'])->name('salary.store');
        Route::get('salary/{salary}/edit', [HrSalaryController::class, 'editEmployeeSalary'])->name('salary.edit');
        Route::put('salary/{salary}', [HrSalaryController::class, 'updateEmployeeSalary'])->name('salary.update');
        Route::get('salary/history', [HrSalaryController::class, 'salaryHistory'])->name('salary.history');

        // Leave Balance
        Route::get('leave-balance', [HrLeaveController::class, 'employeeBalance'])->name('leave-balance');
        Route::post('leave-balance/adjust', [HrLeaveController::class, 'adjustBalance'])->name('leave-balance.adjust');
    });

    // ===========================
    // ATTENDANCE
    // ===========================

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', [HrAttendanceController::class, 'index'])->name('index');
        Route::get('monthly', [HrAttendanceController::class, 'monthly'])->name('monthly');
        Route::get('report', [HrAttendanceController::class, 'report'])->name('report');

        // Manual Entry (must be before {attendance} route)
        Route::match(['get', 'post'], 'manual-entry', [HrAttendanceController::class, 'manualEntry'])->name('manual-entry');
        
        // Bulk Entry (All Employees) - must be before {attendance} route
		Route::match(['get', 'post'], 'bulk-entry', [\App\Http\Controllers\Hr\HrAttendanceBulkEntryController::class, 'handle'])
   			 ->name('bulk-entry');
      
        // Process Attendance
        Route::post('process', [HrAttendanceController::class, 'process'])->name('process');

        // Overtime Approval
        Route::get('ot-approval', [HrAttendanceController::class, 'bulkOtApproval'])->name('ot-approval');
        Route::post('ot-approval', [HrAttendanceController::class, 'bulkOtApproval'])->name('ot-approval.store');
        Route::post('{attendance}/approve-ot', [HrAttendanceController::class, 'approveOt'])->name('approve-ot');
        Route::post('{attendance}/reject-ot', [HrAttendanceController::class, 'rejectOt'])->name('reject-ot');

        // Regularization
        Route::match(['get', 'post'], 'regularization', [HrAttendanceController::class, 'regularization'])->name('regularization');
        Route::post('regularization/{regularization}/approve', [HrAttendanceController::class, 'approveRegularization'])
            ->name('regularization.approve');
        Route::post('regularization/{regularization}/reject', [HrAttendanceController::class, 'rejectRegularization'])
            ->name('regularization.reject');

        // Punch Import
        Route::match(['get', 'post'], 'import-punches', [HrAttendanceController::class, 'importPunches'])->name('import-punches');

        // Keep catch-all show route last so static attendance URLs don't get shadowed.
        Route::get('{attendance}', [HrAttendanceController::class, 'show'])->name('show');
    });

    // ===========================
    // LEAVE MANAGEMENT
    // ===========================

    // Leave Applications (standalone)
    Route::resource('leave-applications', HrLeaveApplicationController::class);
    Route::post('leave-applications/{leaveApplication}/approve', [HrLeaveApplicationController::class, 'approve'])->name('leave-applications.approve');
    Route::post('leave-applications/{leaveApplication}/reject', [HrLeaveApplicationController::class, 'reject'])->name('leave-applications.reject');
    Route::post('leave-applications/{leaveApplication}/cancel', [HrLeaveApplicationController::class, 'cancel'])->name('leave-applications.cancel');

    Route::prefix('leave')->name('leave.')->group(function () {

        Route::get('/', [HrLeaveController::class, 'index'])->name('index');
        Route::get('pending', [HrLeaveController::class, 'pending'])->name('pending');
        Route::get('calendar', [HrLeaveController::class, 'calendar'])->name('calendar');
        Route::get('balance-report', [HrLeaveController::class, 'balanceReport'])->name('balance-report');

        // Create / submit
        Route::get('create', [HrLeaveController::class, 'create'])->name('create');
        Route::post('/', [HrLeaveController::class, 'store'])->name('store');

        // Approvals / status changes
        Route::post('{application}/approve', [HrLeaveController::class, 'approve'])->name('approve');
        Route::post('{application}/reject', [HrLeaveController::class, 'reject'])->name('reject');
        Route::post('{application}/cancel', [HrLeaveController::class, 'cancel'])->name('cancel');

        // Bulk Approve
        Route::post('bulk-approve', [HrLeaveController::class, 'bulkApprove'])->name('bulk-approve');

        // Year-End Processing
        Route::match(['get', 'post'], 'year-end', [HrLeaveController::class, 'yearEndProcessing'])->name('year-end');

        // Applications under leave prefix (legacy / optional)
        Route::resource('applications', HrLeaveApplicationController::class);
        Route::post('applications/{application}/approve', [HrLeaveApplicationController::class, 'approve'])->name('applications.approve');
        Route::post('applications/{application}/reject', [HrLeaveApplicationController::class, 'reject'])->name('applications.reject');
        Route::post('applications/{application}/cancel', [HrLeaveApplicationController::class, 'cancel'])->name('applications.cancel');

        // Show (keep last to avoid catching other routes)
        Route::get('{application}', [HrLeaveController::class, 'show'])->name('show');
    });

    // ===========================
    // PAYROLL
    // ===========================

    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [HrPayrollController::class, 'index'])->name('index');

        // Payroll Period
        Route::match(['get', 'post'], 'create-period', [HrPayrollController::class, 'createPeriod'])->name('create-period');
        Route::get('period/{period}', [HrPayrollController::class, 'period'])->name('period');
        Route::post('period/{period}/process', [HrPayrollController::class, 'process'])->name('period.process');
        Route::post('period/{period}/lock-attendance', [HrPayrollController::class, 'lockAttendance'])->name('period.lock-attendance');
        Route::post('period/{period}/bulk-approve', [HrPayrollController::class, 'bulkApprove'])->name('period.bulk-approve');
        Route::post('period/{period}/bulk-pay', [HrPayrollController::class, 'bulkPay'])->name('period.bulk-pay');
        Route::post('period/{period}/close', [HrPayrollController::class, 'closePeriod'])->name('period.close');

        // Individual Payroll
        Route::get('{payroll}', [HrPayrollController::class, 'show'])->name('show');
        Route::get('{payroll}/payslip', [HrPayrollController::class, 'payslip'])->name('payslip');
        Route::get('{payroll}/payslip-pdf', [HrPayrollController::class, 'payslipPdf'])->name('payslip-pdf');
        Route::post('{payroll}/approve', [HrPayrollController::class, 'approve'])->name('approve');
        Route::post('{payroll}/pay', [HrPayrollController::class, 'pay'])->name('pay');
        Route::post('{payroll}/hold', [HrPayrollController::class, 'hold'])->name('hold');
        Route::post('{payroll}/release', [HrPayrollController::class, 'release'])->name('release');

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('bank-statement/{period}', [HrPayrollController::class, 'bankStatement'])->name('bank-statement');
            Route::get('pf-report/{period}', [HrPayrollController::class, 'pfReport'])->name('pf-report');
            Route::get('esi-report/{period}', [HrPayrollController::class, 'esiReport'])->name('esi-report');
            Route::get('pt-report/{period}', [HrPayrollController::class, 'ptReport'])->name('pt-report');
            Route::get('tds-report/{period}', [HrPayrollController::class, 'tdsReport'])->name('tds-report');
            Route::get('salary-register/{period}', [HrPayrollController::class, 'salaryRegister'])->name('salary-register');
            Route::get('department-wise/{period}', [HrPayrollController::class, 'departmentWise'])->name('department-wise');
        });
    });

    // ===========================
    // LOANS & ADVANCES
    // ===========================

    Route::prefix('loans')->name('loans.')->group(function () {
        Route::get('/', [HrLoanController::class, 'index'])->name('index');
        Route::resource('employee-loans', HrEmployeeLoanController::class);
        Route::post('employee-loans/{loan}/approve', [HrEmployeeLoanController::class, 'approve'])->name('employee-loans.approve');
        Route::post('employee-loans/{loan}/reject', [HrEmployeeLoanController::class, 'reject'])->name('employee-loans.reject');
        Route::post('employee-loans/{loan}/disburse', [HrEmployeeLoanController::class, 'disburse'])->name('employee-loans.disburse');
        Route::get('employee-loans/{loan}/schedule', [HrEmployeeLoanController::class, 'schedule'])->name('employee-loans.schedule');
    });

    Route::prefix('advances')->name('advances.')->group(function () {
        Route::get('/', [HrAdvanceController::class, 'index'])->name('index');
        Route::resource('salary-advances', HrSalaryAdvanceController::class);
        Route::post('salary-advances/{advance}/approve', [HrSalaryAdvanceController::class, 'approve'])->name('salary-advances.approve');
        Route::post('salary-advances/{advance}/reject', [HrSalaryAdvanceController::class, 'reject'])->name('salary-advances.reject');
        Route::post('salary-advances/{advance}/disburse', [HrSalaryAdvanceController::class, 'disburse'])->name('salary-advances.disburse');
    });

    // ===========================
    // TAX DECLARATIONS
    // ===========================

    Route::prefix('tax')->name('tax.')->group(function () {
        Route::get('/', [HrTaxController::class, 'index'])->name('index');
        Route::resource('declarations', HrTaxDeclarationController::class);
        Route::post('declarations/{declaration}/submit', [HrTaxDeclarationController::class, 'submit'])->name('declarations.submit');
        Route::post('declarations/{declaration}/verify', [HrTaxDeclarationController::class, 'verify'])->name('declarations.verify');
        Route::get('computation/{employee}', [HrTaxController::class, 'computation'])->name('computation');
    });

    // ===========================
    // REPORTS
    // ===========================

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [HrReportController::class, 'index'])->name('index');
        Route::get('headcount', [HrReportController::class, 'headcount'])->name('headcount');
        Route::get('attrition', [HrReportController::class, 'attrition'])->name('attrition');
        Route::get('birthday', [HrReportController::class, 'birthday'])->name('birthday');
        Route::get('anniversary', [HrReportController::class, 'anniversary'])->name('anniversary');
        Route::get('probation-due', [HrReportController::class, 'probationDue'])->name('probation-due');
        Route::get('contract-expiry', [HrReportController::class, 'contractExpiry'])->name('contract-expiry');
        Route::get('document-expiry', [HrReportController::class, 'documentExpiry'])->name('document-expiry');
        Route::get('employee-directory', [HrReportController::class, 'employeeDirectory'])->name('employee-directory');
        Route::get('muster-roll', [HrReportController::class, 'musterRoll'])->name('muster-roll');
    });

    // ===========================
    // SETTINGS
    // ===========================

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [HrSettingsController::class, 'index'])->name('index');
        Route::post('/', [HrSettingsController::class, 'update'])->name('update');

        // Statutory Slabs
        Route::resource('pf-slabs', HrPfSlabController::class)->except(['show']);
        Route::resource('esi-slabs', HrEsiSlabController::class)->except(['show']);
        Route::resource('pt-slabs', HrPtSlabController::class)->except(['show']);
        Route::resource('tds-slabs', HrTdsSlabController::class)->except(['show']);
        Route::resource('lwf-slabs', HrLwfSlabController::class)->except(['show']);
    });

});
