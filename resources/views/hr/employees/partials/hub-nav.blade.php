@php
    $employeeHubLinks = [
        'overview' => route('hr.employees.show', $employee),
        'attendance' => route('hr.employees.attendance', $employee),
        'leave' => route('hr.employees.leave', $employee),
        'salary' => route('hr.employees.salary.show', $employee),
        'payroll' => route('hr.employees.payroll', $employee),
        'loans-advances' => route('hr.employees.loans-advances', $employee),
        'compliance' => route('hr.employees.compliance', $employee),
        'timeline' => route('hr.employees.timeline', $employee),
    ];
@endphp

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="fw-semibold">{{ $employee->full_name }}</div>
                <div class="text-muted small">{{ $employee->employee_code }} | {{ $employee->designation?->name ?? 'No designation' }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach($employeeHubLinks as $key => $url)
                    <a href="{{ $url }}"
                       class="btn btn-sm {{ ($activeSection ?? 'overview') === $key ? 'btn-dark' : 'btn-outline-secondary' }}">
                        {{ match($key) {
                            'overview' => 'Overview',
                            'attendance' => 'Attendance',
                            'leave' => 'Leave',
                            'salary' => 'Salary',
                            'payroll' => 'Payroll',
                            'loans-advances' => 'Loans & Advances',
                            'compliance' => 'Compliance',
                            'timeline' => 'Timeline',
                        } }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
