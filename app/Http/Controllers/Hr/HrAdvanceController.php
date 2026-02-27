<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrSalaryAdvance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrAdvanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $summary = [
            'applied' => HrSalaryAdvance::where('status', 'applied')->count(),
            'approved' => HrSalaryAdvance::where('status', 'approved')->count(),
            'recovering' => HrSalaryAdvance::where('status', 'recovering')->count(),
            'outstanding' => HrSalaryAdvance::whereIn('status', ['approved', 'disbursed', 'recovering'])->sum('balance_amount'),
        ];

        $recentAdvances = HrSalaryAdvance::with('employee')->latest()->limit(10)->get();

        return view('hr.advances.index', compact('summary', 'recentAdvances'));
    }
}
