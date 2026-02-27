<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployeeLoan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrLoanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $summary = [
            'pending' => HrEmployeeLoan::whereIn('status', ['applied', 'pending_approval'])->count(),
            'approved' => HrEmployeeLoan::where('status', 'approved')->count(),
            'active' => HrEmployeeLoan::where('status', 'active')->count(),
            'outstanding' => HrEmployeeLoan::whereIn('status', ['approved', 'disbursed', 'active'])->sum('total_outstanding'),
        ];

        $recentLoans = HrEmployeeLoan::with(['employee', 'loanType'])
            ->latest()
            ->limit(10)
            ->get();

        return view('hr.loans.index', compact('summary', 'recentLoans'));
    }
}
