<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrTaxDeclaration;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrTaxController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $fy = $request->get('financial_year', $this->currentFinancialYear());

        $declarations = HrTaxDeclaration::with('employee')
            ->where('financial_year', $fy)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'total' => HrTaxDeclaration::where('financial_year', $fy)->count(),
            'submitted' => HrTaxDeclaration::where('financial_year', $fy)->where('status', 'submitted')->count(),
            'verified' => HrTaxDeclaration::where('financial_year', $fy)->where('status', 'verified')->count(),
            'declared_amount' => HrTaxDeclaration::where('financial_year', $fy)->sum('total_declared'),
        ];

        return view('hr.tax.index', compact('declarations', 'summary', 'fy'));
    }

    public function computation(HrEmployee $employee, Request $request): View
    {
        $fy = $request->get('financial_year', $this->currentFinancialYear());

        $declaration = HrTaxDeclaration::with('details')
            ->where('hr_employee_id', $employee->id)
            ->where('financial_year', $fy)
            ->first();

        $monthlyGross = (float) optional($employee->currentSalary)->monthly_gross;
        $annualIncome = $monthlyGross * 12;
        $totalExemption = (float) ($declaration?->total_verified ?? $declaration?->total_declared ?? 0);
        $taxableIncome = max(0, $annualIncome - $totalExemption);

        $taxLiability = $this->estimateTaxLiability($taxableIncome, $declaration?->tax_regime ?? 'new');

        return view('hr.tax.computation', compact(
            'employee',
            'fy',
            'declaration',
            'monthlyGross',
            'annualIncome',
            'totalExemption',
            'taxableIncome',
            'taxLiability'
        ));
    }

    private function currentFinancialYear(): string
    {
        $now = now();
        $start = $now->month >= 4 ? $now->year : $now->year - 1;
        return sprintf('%d-%02d', $start, ($start + 1) % 100);
    }

    private function estimateTaxLiability(float $taxableIncome, string $regime): float
    {
        // Simplified estimator to keep computation screen functional.
        if ($taxableIncome <= 300000) {
            return 0;
        }

        $tax = 0;
        $slabs = $regime === 'old'
            ? [
                [250000, 0],
                [500000, 5],
                [1000000, 20],
                [INF, 30],
            ]
            : [
                [300000, 0],
                [700000, 5],
                [1000000, 10],
                [1200000, 15],
                [1500000, 20],
                [INF, 30],
            ];

        $previous = 0;
        foreach ($slabs as [$limit, $rate]) {
            if ($taxableIncome <= $previous) {
                break;
            }
            $amount = min($taxableIncome, $limit) - $previous;
            if ($amount > 0) {
                $tax += $amount * ($rate / 100);
            }
            $previous = $limit;
        }

        return round($tax * 1.04, 2); // include cess
    }
}
