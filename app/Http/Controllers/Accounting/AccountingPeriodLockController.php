<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingPeriodLock;
use App\Services\Accounting\AccountingFinancialYearService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class AccountingPeriodLockController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.accounts.view')->only(['index', 'store', 'toggle', 'destroy']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) Config::get('accounting.default_company_id', 1);
    }

    public function index()
    {
        $companyId = $this->defaultCompanyId();
        $locks = AccountingPeriodLock::query()
            ->where('company_id', $companyId)
            ->orderByDesc('from_date')
            ->orderByDesc('id')
            ->get();
        $financialYearService = app(AccountingFinancialYearService::class);
        $currentFy = $financialYearService->currentRange();
        $previousFy = $financialYearService->previousRange();
        $previousMonth = Carbon::now()->startOfMonth()->subMonth();

        return view('accounting.period_locks.index', [
            'companyId' => $companyId,
            'locks' => $locks,
            'maxBackdateDays' => Config::get('accounting.date_controls.max_backdate_days'),
            'maxFutureDays' => Config::get('accounting.date_controls.max_future_days'),
            'enforceCurrentFinancialYear' => filter_var(
                (string) Config::get('accounting.date_controls.enforce_current_financial_year', false),
                FILTER_VALIDATE_BOOLEAN
            ),
            'currentFy' => $currentFy,
            'previousFy' => $previousFy,
            'previousMonth' => [
                'start' => $previousMonth->copy()->startOfMonth(),
                'end' => $previousMonth->copy()->endOfMonth(),
                'label' => $previousMonth->format('F Y'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable'],
        ]);

        AccountingPeriodLock::create([
            'company_id' => $companyId,
            'title' => $data['title'] ?? null,
            'from_date' => $data['from_date'],
            'to_date' => $data['to_date'],
            'reason' => $data['reason'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('accounting.period-locks.index')
            ->with('success', 'Accounting period lock saved.');
    }

    public function quickStore(Request $request, AccountingFinancialYearService $financialYearService)
    {
        $companyId = $this->defaultCompanyId();

        $data = $request->validate([
            'quick_type' => ['required', 'in:previous_month,previous_financial_year,current_financial_year_to_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $currentFy = $financialYearService->currentRange();
        $previousFy = $financialYearService->previousRange();
        $previousMonth = Carbon::now()->startOfMonth()->subMonth();

        [$fromDate, $toDate, $title] = match ($data['quick_type']) {
            'previous_month' => [
                $previousMonth->copy()->startOfMonth(),
                $previousMonth->copy()->endOfMonth(),
                'Monthly Close - ' . $previousMonth->format('F Y'),
            ],
            'previous_financial_year' => [
                $previousFy['start'],
                $previousFy['end'],
                'Financial Year Close - FY ' . $previousFy['label'],
            ],
            default => [
                $currentFy['start'],
                Carbon::now()->startOfDay(),
                'Close Through Today - FY ' . $currentFy['label'],
            ],
        };

        $existing = AccountingPeriodLock::query()
            ->where('company_id', $companyId)
            ->whereDate('from_date', $fromDate->toDateString())
            ->whereDate('to_date', $toDate->toDateString())
            ->first();

        if ($existing) {
            $existing->fill([
                'title' => $title,
                'reason' => $data['reason'] ?? $existing->reason,
                'is_active' => true,
                'updated_by' => $request->user()?->id,
            ]);
            $existing->save();

            return redirect()
                ->route('accounting.period-locks.index')
                ->with('success', 'Accounting period lock reactivated.');
        }

        AccountingPeriodLock::create([
            'company_id' => $companyId,
            'title' => $title,
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'reason' => $data['reason'] ?? null,
            'is_active' => true,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('accounting.period-locks.index')
            ->with('success', 'Quick accounting close saved.');
    }

    public function toggle(Request $request, AccountingPeriodLock $periodLock)
    {
        $periodLock->is_active = ! $periodLock->is_active;
        $periodLock->updated_by = $request->user()?->id;
        $periodLock->save();

        return redirect()
            ->route('accounting.period-locks.index')
            ->with('success', 'Accounting period lock updated.');
    }

    public function destroy(AccountingPeriodLock $periodLock)
    {
        $periodLock->delete();

        return redirect()
            ->route('accounting.period-locks.index')
            ->with('success', 'Accounting period lock deleted.');
    }
}
