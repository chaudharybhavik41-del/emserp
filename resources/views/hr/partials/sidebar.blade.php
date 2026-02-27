{{-- HR Module Sidebar Navigation --}}

@canany(['hr.dashboard.view', 'hr.employee.view', 'hr.attendance.view', 'hr.leave.view', 'hr.payroll.view'])
<li class="nav-item">
    <a class="nav-link {{ request()->is('hr*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#hrMenu" role="button" aria-expanded="{{ request()->is('hr*') ? 'true' : 'false' }}">
        <i class="bi bi-people-fill me-2"></i>
        HR Management
        <i class="bi bi-chevron-down ms-auto"></i>
    </a>

    <div class="collapse {{ request()->is('hr*') ? 'show' : '' }}" id="hrMenu">
        <ul class="nav flex-column ms-3">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.dashboard') ? 'active' : '' }}" href="{{ route('hr.dashboard') }}">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.employees.*') ? 'active' : '' }}" href="{{ route('hr.employees.index') }}">
                    <i class="bi bi-person-badge me-2"></i> Employees
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.attendance.*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#hrAttendanceMenu">
                    <i class="bi bi-calendar-check me-2"></i> Attendance
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hr.attendance.*') ? 'show' : '' }}" id="hrAttendanceMenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.attendance.index') ? 'active' : '' }}" href="{{ route('hr.attendance.index') }}">Daily View</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.attendance.monthly') ? 'active' : '' }}" href="{{ route('hr.attendance.monthly') }}">Monthly View</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.attendance.report') ? 'active' : '' }}" href="{{ route('hr.attendance.report') }}">Report</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.attendance.ot-approval*') ? 'active' : '' }}" href="{{ route('hr.attendance.ot-approval') }}">OT Approval</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.attendance.regularization*') ? 'active' : '' }}" href="{{ route('hr.attendance.regularization') }}">Regularization</a></li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.leave.*') || request()->routeIs('hr.leave-applications.*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#hrLeaveMenu">
                    <i class="bi bi-calendar-x me-2"></i> Leave
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hr.leave.*') || request()->routeIs('hr.leave-applications.*') ? 'show' : '' }}" id="hrLeaveMenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.leave.index') ? 'active' : '' }}" href="{{ route('hr.leave.index') }}">Leave Requests</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.leave.pending') ? 'active' : '' }}" href="{{ route('hr.leave.pending') }}">Pending Approval</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.leave.calendar') ? 'active' : '' }}" href="{{ route('hr.leave.calendar') }}">Calendar</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.leave.balance-report') ? 'active' : '' }}" href="{{ route('hr.leave.balance-report') }}">Balance Report</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.leave-applications.*') ? 'active' : '' }}" href="{{ route('hr.leave-applications.index') }}">Application Register</a></li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.payroll.*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#hrPayrollMenu">
                    <i class="bi bi-currency-rupee me-2"></i> Payroll
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hr.payroll.*') ? 'show' : '' }}" id="hrPayrollMenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.payroll.index') ? 'active' : '' }}" href="{{ route('hr.payroll.index') }}">Payroll Periods</a></li>
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.payroll.create-period') }}">Create Period</a></li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.loans.*') || request()->routeIs('hr.advances.*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#hrLoanMenu">
                    <i class="bi bi-cash-stack me-2"></i> Loans & Advances
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hr.loans.*') || request()->routeIs('hr.advances.*') ? 'show' : '' }}" id="hrLoanMenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.loans.index') ? 'active' : '' }}" href="{{ route('hr.loans.index') }}">Loan Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.loans.employee-loans.*') ? 'active' : '' }}" href="{{ route('hr.loans.employee-loans.index') }}">Employee Loans</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.loan-types.*') ? 'active' : '' }}" href="{{ route('hr.loan-types.index') }}">Loan Types</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.advances.index') ? 'active' : '' }}" href="{{ route('hr.advances.index') }}">Advance Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.advances.salary-advances.*') ? 'active' : '' }}" href="{{ route('hr.advances.salary-advances.index') }}">Salary Advances</a></li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.tax.*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#hrTaxMenu">
                    <i class="bi bi-receipt me-2"></i> Tax
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hr.tax.*') ? 'show' : '' }}" id="hrTaxMenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.tax.index') ? 'active' : '' }}" href="{{ route('hr.tax.index') }}">Tax Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs('hr.tax.declarations.*') ? 'active' : '' }}" href="{{ route('hr.tax.declarations.index') }}">Declarations</a></li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.reports.*') ? 'active' : '' }}" href="{{ route('hr.reports.index') }}">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i> Reports
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.designations.*') || request()->routeIs('hr.grades.*') || request()->routeIs('hr.shifts.*') || request()->routeIs('hr.work-locations.*') || request()->routeIs('hr.attendance-policies.*') || request()->routeIs('hr.leave-types.*') || request()->routeIs('hr.leave-policies.*') || request()->routeIs('hr.holiday-calendars.*') || request()->routeIs('hr.salary-components.*') || request()->routeIs('hr.salary-structures.*') || request()->routeIs('hr.settings.*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#hrMasterMenu">
                    <i class="bi bi-gear me-2"></i> Masters & Settings
                    <i class="bi bi-chevron-down ms-auto small"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hr.designations.*') || request()->routeIs('hr.grades.*') || request()->routeIs('hr.shifts.*') || request()->routeIs('hr.work-locations.*') || request()->routeIs('hr.attendance-policies.*') || request()->routeIs('hr.leave-types.*') || request()->routeIs('hr.leave-policies.*') || request()->routeIs('hr.holiday-calendars.*') || request()->routeIs('hr.salary-components.*') || request()->routeIs('hr.salary-structures.*') || request()->routeIs('hr.settings.*') ? 'show' : '' }}" id="hrMasterMenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.designations.index') }}">Designations</a></li>
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.grades.index') }}">Grades</a></li>
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.shifts.index') }}">Shifts</a></li>
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.work-locations.index') }}">Work Locations</a></li>
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.attendance-policies.index') }}">Attendance Policies</a></li>
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.leave-types.index') }}">Leave Types</a></li>
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.leave-policies.index') }}">Leave Policies</a></li>
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.holiday-calendars.index') }}">Holiday Calendar</a></li>
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.salary-components.index') }}">Salary Components</a></li>
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.salary-structures.index') }}">Salary Structures</a></li>
                        <li class="nav-item"><a class="nav-link py-1" href="{{ route('hr.settings.index') }}">Settings</a></li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>
</li>
@endcanany
