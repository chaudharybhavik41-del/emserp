<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrHoliday;
use App\Models\Hr\HrLeaveApplication;
use App\Models\Hr\HrLeaveBalance;
use App\Models\Hr\HrLeaveType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class HrMyLeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Resolve the logged-in user's linked HR Employee record.
     */
    protected function myEmployee(): HrEmployee
    {
        $q = HrEmployee::query();

        // Prefer scopeActive() if present, otherwise fallback to status column
        if (method_exists(HrEmployee::class, 'scopeActive')) {
            $q->active();
        } elseif (Schema::hasColumn('hr_employees', 'status')) {
            $q->where('status', 'active');
        }

        $employee = $q->where('user_id', Auth::id())->first();

        if (!$employee) {
            abort(403, 'No active employee record is linked to your user account.');
        }

        return $employee;
    }

    /**
     * My Leave list (self-service).
     */
    public function index(Request $request)
    {
        $employee = $this->myEmployee();

        $query = HrLeaveApplication::query()
            ->with(['leaveType'])
            ->where('hr_employee_id', $employee->id);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        // Prefer created_at if present; else fallback to id.
        $orderCol = Schema::hasColumn('hr_leave_applications', 'created_at') ? 'created_at' : 'id';

        $applications = $query->orderByDesc($orderCol)
            ->paginate(20)
            ->withQueryString();

        return view('hr.my-leave.index', compact('employee', 'applications'));
    }

    /**
     * Apply Leave form (self-service).
     */
    public function create(Request $request)
    {
        $employee = $this->myEmployee();
        $year = (int) ($request->get('year') ?: now()->year);

        $leaveTypes = HrLeaveType::query()
            ->active()
            ->ordered()
            ->get()
            ->filter(fn (HrLeaveType $t) => $t->isApplicableFor($employee))
            ->values();

        // Balances are optional (depends on whether balances are being maintained/processed).
        $balances = collect();
        if (Schema::hasTable('hr_leave_balances')) {
            $balances = HrLeaveBalance::query()
                ->where('hr_employee_id', $employee->id)
                ->where('year', $year)
                ->get()
                ->keyBy('hr_leave_type_id');
        }

        // Support older/newer blade filenames.
        $view = view()->exists('hr.my-leave.form')
            ? 'hr.my-leave.form'
            : (view()->exists('hr.my-leave.create') ? 'hr.my-leave.create' : 'hr.my-leave.form');

        return view($view, compact('employee', 'leaveTypes', 'balances', 'year'));
    }

    /**
     * Store leave application (self-service).
     */
    public function store(Request $request)
    {
        $employee = $this->myEmployee();

        $validated = $request->validate([
            'hr_leave_type_id' => ['required', 'exists:hr_leave_types,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],

            // Keep nullable for backward compatibility. If UI always sends them, they will be present.
            'from_session' => ['nullable', 'in:full_day,first_half,second_half'],
            'to_session' => ['nullable', 'in:full_day,first_half,second_half'],

            'reason' => ['required', 'string', 'max:1000'],
            'contact_during_leave' => ['nullable', 'string', 'max:50'],
            'address_during_leave' => ['nullable', 'string', 'max:255'],

            'handover_to' => ['nullable', 'exists:hr_employees,id'],
            'handover_notes' => ['nullable', 'string', 'max:1000'],

            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        /** @var HrLeaveType $leaveType */
        $leaveType = HrLeaveType::query()->active()->findOrFail((int) $validated['hr_leave_type_id']);

        if (!$leaveType->isApplicableFor($employee)) {
            abort(403, 'This leave type is not applicable to you.');
        }

        $from = Carbon::parse($validated['from_date'])->startOfDay();
        $to = Carbon::parse($validated['to_date'])->startOfDay();

        $fromSession = $validated['from_session'] ?? 'full_day';
        $toSession = $validated['to_session'] ?? 'full_day';

        // If half-day is not allowed for this leave type, force full-day sessions.
        if (!$leaveType->allow_half_day) {
            $fromSession = 'full_day';
            $toSession = 'full_day';
        }

        // Same-day session sanity (prevents negative/invalid combos)
        if ($from->equalTo($to)) {
            if ($fromSession === 'second_half' && $toSession === 'first_half') {
                return back()
                    ->withInput()
                    ->with('error', 'Invalid session selection for same-day leave.');
            }

            // If the UI ever sends first_half -> second_half for same day, treat as full day.
            if ($fromSession === 'first_half' && $toSession === 'second_half') {
                $fromSession = 'full_day';
                $toSession = 'full_day';
            }
        }

        // Leave rules: advance notice + min/max days (if configured)
        if (!empty($leaveType->advance_notice_days) && (int) $leaveType->advance_notice_days > 0) {
            $minStart = now()->startOfDay()->addDays((int) $leaveType->advance_notice_days);
            if ($from->lt($minStart)) {
                return back()
                    ->withInput()
                    ->with('error', "This leave type requires at least {$leaveType->advance_notice_days} day(s) advance notice.");
            }
        }

        $totalDays = $this->calculateLeaveDays($from, $to, $fromSession, $toSession, $leaveType);

        if ($totalDays <= 0) {
            return back()
                ->withInput()
                ->with('error', 'Invalid leave duration. Please check dates and sessions.');
        }

        if (!empty($leaveType->min_days_per_application) && $totalDays < (float) $leaveType->min_days_per_application) {
            return back()
                ->withInput()
                ->with('error', "Minimum days per application for this leave type is {$leaveType->min_days_per_application}.");
        }

        if (!empty($leaveType->max_days_per_application) && (float) $leaveType->max_days_per_application > 0 && $totalDays > (float) $leaveType->max_days_per_application) {
            return back()
                ->withInput()
                ->with('error', "Maximum days per application for this leave type is {$leaveType->max_days_per_application}.");
        }

        // Prevent overlap with existing pending/approved applications
        $overlap = HrLeaveApplication::query()
            ->where('hr_employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('from_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('to_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->where('from_date', '<=', $from->toDateString())
                           ->where('to_date', '>=', $to->toDateString());
                    });
            })
            ->exists();

        if ($overlap) {
            return back()
                ->withInput()
                ->with('error', 'Your selected dates overlap with an existing leave application.');
        }

        // Document requirement enforcement (if configured)
        $docRequired = (bool) ($leaveType->document_required ?? false);
        $docAfterDays = (int) ($leaveType->document_required_after_days ?? 0);
        if ($docRequired && ($docAfterDays <= 0 || $totalDays >= $docAfterDays) && !$request->hasFile('document')) {
            return back()
                ->withInput()
                ->with('error', 'Document is required for this leave type for the selected duration.');
        }

        // Check balance (optional / schema tolerant)
        $year = (int) $from->year;

        $balanceBefore = null;
        $balanceAfter = null;

        $balance = null;
        if (Schema::hasTable('hr_leave_balances')) {
            $balance = HrLeaveBalance::query()
                ->where('hr_employee_id', $employee->id)
                ->where('hr_leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->first();
        }

        $available = $balance
            ? $this->getAvailableFromBalance($balance)
            : (float) ($leaveType->default_days_per_year ?? 0);

        $balanceBefore = $available;
        $balanceAfter = $available - $totalDays;

        // Negative balance checks
        if (!$leaveType->allow_negative_balance && $totalDays > $available) {
            return back()
                ->withInput()
                ->with('error', "Insufficient leave balance. Available: {$available} day(s).");
        }

        if ($leaveType->allow_negative_balance) {
            $limit = (float) ($leaveType->negative_balance_limit ?? 0);
            if ($limit > 0 && $balanceAfter < (-1 * $limit)) {
                return back()
                    ->withInput()
                    ->with('error', "Negative balance limit exceeded. Allowed negative: {$limit} day(s).");
            }
        }

        // Handle document upload
        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leave-documents', 'public');
        }

        // Create & save application
        $application = new HrLeaveApplication();
        $application->hr_employee_id = $employee->id;
        $application->hr_leave_type_id = $leaveType->id;
        $application->from_date = $from->toDateString();
        $application->to_date = $to->toDateString();
        $application->total_days = $totalDays;
        $application->reason = $validated['reason'];
        $application->contact_during_leave = $validated['contact_during_leave'] ?? null;
        $application->address_during_leave = $validated['address_during_leave'] ?? null;
        $application->handover_to = $validated['handover_to'] ?? null;
        $application->handover_notes = $validated['handover_notes'] ?? null;
        $application->status = 'pending';

        // application_number (only if column exists)
        if (Schema::hasColumn('hr_leave_applications', 'application_number') && empty($application->application_number)) {
            if (method_exists(HrLeaveApplication::class, 'generateApplicationNumber')) {
                $application->application_number = HrLeaveApplication::generateApplicationNumber();
            } else {
                $application->application_number = 'LA-' . now()->format('Ymd-His') . '-' . random_int(100, 999);
            }
        }

        // Optional sessions (if schema supports it)
        if (Schema::hasColumn('hr_leave_applications', 'from_session')) {
            $application->from_session = $fromSession;
        }
        if (Schema::hasColumn('hr_leave_applications', 'to_session')) {
            $application->to_session = $toSession;
        }

        // If schema has half-day columns, set them when relevant (single-day half leave)
        if (Schema::hasColumn('hr_leave_applications', 'is_half_day')) {
            $isHalfDay = ($from->equalTo($to) && $totalDays == 0.5);
            $application->is_half_day = $isHalfDay;

            if ($isHalfDay) {
                if (Schema::hasColumn('hr_leave_applications', 'half_day_date')) {
                    $application->half_day_date = $from->toDateString();
                }
                if (Schema::hasColumn('hr_leave_applications', 'half_day_type')) {
                    $application->half_day_type = ($fromSession !== 'full_day') ? $fromSession : $toSession;
                }
            }
        }

        // Store balance_before / balance_after only if columns exist
        if (Schema::hasColumn('hr_leave_applications', 'balance_before')) {
            $application->balance_before = $balanceBefore ?? 0;
        }
        if (Schema::hasColumn('hr_leave_applications', 'balance_after')) {
            $application->balance_after = $balanceAfter ?? 0;
        }

        // company_id if present in schema
        if (Schema::hasColumn('hr_leave_applications', 'company_id') && isset($employee->company_id)) {
            $application->company_id = $employee->company_id;
        }

        // Audit fields if present
        if (Schema::hasColumn('hr_leave_applications', 'applied_on')) {
            $application->applied_on = now();
        }
        if (Schema::hasColumn('hr_leave_applications', 'applied_by')) {
            $application->applied_by = Auth::id();
        }
        if (Schema::hasColumn('hr_leave_applications', 'created_by')) {
            $application->created_by = Auth::id();
        }

        if ($documentPath && Schema::hasColumn('hr_leave_applications', 'document_path')) {
            $application->document_path = $documentPath;
        }

        $application->save();

        // If leave balances are maintained and have a 'pending' column, reflect the applied leave.
        $this->incrementPendingBalanceIfPossible(
            (int) $employee->id,
            (int) $leaveType->id,
            (int) $year,
            (float) $totalDays
        );

        return redirect()
            ->route('hr.my.leave.index')
            ->with('success', 'Leave application submitted successfully.');
    }

    /**
     * My Leave Balance (self-service).
     */
    public function balance(Request $request)
    {
        $employee = $this->myEmployee();

        $year = (int) ($request->get('year') ?: now()->year);

        $leaveTypes = HrLeaveType::query()
            ->active()
            ->ordered()
            ->get()
            ->filter(fn (HrLeaveType $t) => $t->isApplicableFor($employee))
            ->values();

        $balancesByType = collect();
        if (Schema::hasTable('hr_leave_balances')) {
            $balancesByType = HrLeaveBalance::query()
                ->where('hr_employee_id', $employee->id)
                ->where('year', $year)
                ->get()
                ->keyBy('hr_leave_type_id');
        }

        // Normalize balance fields for UI (handles schema variations: used/availed/taken)
        $rows = $leaveTypes->map(function (HrLeaveType $type) use ($balancesByType) {
            /** @var HrLeaveBalance|null $b */
            $b = $balancesByType->get($type->id);

            $opening = (float) ($b?->opening_balance ?? 0);
            $credited = (float) ($b?->credited ?? 0);

            // used vs availed vs taken
            $used = 0.0;
            if ($b) {
                $used = (float) (
                    $b->getAttribute('used')
                    ?? $b->getAttribute('availed')
                    ?? $b->getAttribute('taken')
                    ?? 0
                );
            }

            $pending = (float) ($b?->getAttribute('pending') ?? 0);
            $encashed = (float) ($b?->getAttribute('encashed') ?? 0);
            $lapsed = (float) ($b?->getAttribute('lapsed') ?? 0);
            $adjusted = (float) ($b?->getAttribute('adjusted') ?? 0);

            $available = null;
            if ($b && Schema::hasColumn('hr_leave_balances', 'available_balance')) {
                // getRawOriginal bypasses any accessor overriding available_balance
                $available = $b->getRawOriginal('available_balance');
            }

            if ($available === null) {
                $available = $opening + $credited - $used - $pending - $encashed - $lapsed + $adjusted;
            }

            // Fallback entitlement (if no balance records exist, use type default)
            $entitledFallback = (float) ($type->default_days_per_year ?? 0);
            $entitled = ($b ? ($opening + $credited) : $entitledFallback);

            return [
                'leave_type' => $type,
                'entitled' => $entitled,
                'opening' => $opening,
                'credited' => $credited,
                'used' => $used,
                'pending' => $pending,
                'available' => (float) $available,
                'has_balance_row' => (bool) $b,
            ];
        });

        return view('hr.my-leave.balance', compact('employee', 'rows', 'year'));
    }

    /**
     * Cancel a pending leave application (self-service).
     */
    public function cancel(Request $request, HrLeaveApplication $application)
    {
        $employee = $this->myEmployee();

        if ((int) $application->hr_employee_id !== (int) $employee->id) {
            abort(403, 'You are not allowed to cancel this leave application.');
        }

        // status can be enum-cast (App\Enums\Hr\LeaveStatus)
        $statusObj = $application->status ?? null;
        $statusValue = $statusObj instanceof \BackedEnum ? $statusObj->value : (string) $statusObj;

        if ($statusValue !== 'pending') {
            return back()->with('error', 'Only pending leave applications can be cancelled.');
        }

        $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $application->status = 'cancelled';

        if (Schema::hasColumn('hr_leave_applications', 'cancelled_by')) {
            $application->cancelled_by = Auth::id();
        }
        if (Schema::hasColumn('hr_leave_applications', 'cancelled_at')) {
            $application->cancelled_at = now();
        }
        if (Schema::hasColumn('hr_leave_applications', 'cancellation_reason')) {
            $application->cancellation_reason = $request->input('cancellation_reason');
        }

        $application->save();

        // If leave balances are maintained and have a 'pending' column, revert pending.
        $this->decrementPendingBalanceIfPossible(
            (int) $employee->id,
            (int) $application->hr_leave_type_id,
            (int) Carbon::parse($application->from_date)->year,
            (float) ($application->total_days ?? 0)
        );

        return back()->with('success', 'Leave application cancelled.');
    }

    /**
     * Calculate leave days using leave type flags:
     * - include_weekends: include Sundays
     * - include_holidays: include holidays (hr_holidays)
     *
     * Note: schema-safe holiday date column detection is used.
     */
    private function calculateLeaveDays(Carbon $from, Carbon $to, string $fromSession, string $toSession, HrLeaveType $leaveType): float
    {
        $includeWeekends = (bool) ($leaveType->include_weekends ?? false);
        $includeHolidays = (bool) ($leaveType->include_holidays ?? false);

        $holidays = [];
        if (!$includeHolidays && Schema::hasTable('hr_holidays')) {
            $holidays = $this->getHolidayDatesInRange($from, $to);
        }

        $days = 0.0;

        foreach (CarbonPeriod::create($from, $to) as $date) {
            /** @var Carbon $date */
            if (!$includeWeekends && $date->isSunday()) {
                continue;
            }

            if (!$includeHolidays && in_array($date->format('Y-m-d'), $holidays, true)) {
                continue;
            }

            if ($date->equalTo($from) && $fromSession !== 'full_day') {
                $days += 0.5;
            } elseif ($date->equalTo($to) && $toSession !== 'full_day') {
                $days += 0.5;
            } else {
                $days += 1.0;
            }
        }

        // Ensure non-zero for valid selections
        return max(0.0, $days);
    }

    /**
     * Returns holiday dates (Y-m-d) between range. Supports both holiday_date/date columns.
     */
    private function getHolidayDatesInRange(Carbon $from, Carbon $to): array
    {
        static $holidayDateColumn = null;

        if ($holidayDateColumn === null) {
            if (Schema::hasColumn('hr_holidays', 'holiday_date')) {
                $holidayDateColumn = 'holiday_date';
            } elseif (Schema::hasColumn('hr_holidays', 'date')) {
                $holidayDateColumn = 'date';
            } else {
                $holidayDateColumn = ''; // unknown schema
            }
        }

        if ($holidayDateColumn === '') {
            return [];
        }

        return HrHoliday::query()
            ->whereBetween($holidayDateColumn, [$from->toDateString(), $to->toDateString()])
            ->pluck($holidayDateColumn)
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Schema-tolerant "available" calculation for hr_leave_balances row.
     */
    private function getAvailableFromBalance(HrLeaveBalance $balance): float
    {
        // Prefer computed/virtual available_balance column if present.
        if (Schema::hasColumn('hr_leave_balances', 'available_balance')) {
            $raw = $balance->getRawOriginal('available_balance');
            if ($raw !== null) {
                return (float) $raw;
            }
        }

        $opening = (float) ($balance->opening_balance ?? 0);
        $credited = (float) ($balance->credited ?? 0);

        $used = (float) (
            $balance->getAttribute('used')
            ?? $balance->getAttribute('availed')
            ?? $balance->getAttribute('taken')
            ?? 0
        );

        $pending = (float) ($balance->getAttribute('pending') ?? 0);
        $encashed = (float) ($balance->getAttribute('encashed') ?? 0);
        $lapsed = (float) ($balance->getAttribute('lapsed') ?? 0);
        $adjusted = (float) ($balance->getAttribute('adjusted') ?? 0);

        return $opening + $credited - $used - $pending - $encashed - $lapsed + $adjusted;
    }

    private function incrementPendingBalanceIfPossible(int $employeeId, int $leaveTypeId, int $year, float $days): void
    {
        if ($days <= 0) {
            return;
        }
        if (!Schema::hasTable('hr_leave_balances') || !Schema::hasColumn('hr_leave_balances', 'pending')) {
            return;
        }

        $balance = HrLeaveBalance::query()
            ->where('hr_employee_id', $employeeId)
            ->where('hr_leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if (!$balance) {
            return;
        }

        $balance->pending = (float) ($balance->getAttribute('pending') ?? 0) + $days;
        $balance->save();
    }

    private function decrementPendingBalanceIfPossible(int $employeeId, int $leaveTypeId, int $year, float $days): void
    {
        if ($days <= 0) {
            return;
        }
        if (!Schema::hasTable('hr_leave_balances') || !Schema::hasColumn('hr_leave_balances', 'pending')) {
            return;
        }

        $balance = HrLeaveBalance::query()
            ->where('hr_employee_id', $employeeId)
            ->where('hr_leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if (!$balance) {
            return;
        }

        $balance->pending = max(0, (float) ($balance->getAttribute('pending') ?? 0) - $days);
        $balance->save();
    }
}
