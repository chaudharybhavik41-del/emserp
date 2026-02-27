<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEsiSlab;
use App\Models\Hr\HrLwfSlab;
use App\Models\Hr\HrPfSlab;
use App\Models\Hr\HrProfessionalTaxSlab;
use App\Models\Hr\HrTdsSlab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $settings = [
            'default_notice_period_days' => setting('hr.default_notice_period_days', 30),
            'default_probation_months' => setting('hr.default_probation_months', 6),
            'enable_ot_approval' => setting('hr.enable_ot_approval', true),
            'payroll_cutoff_day' => setting('hr.payroll_cutoff_day', 30),
        ];

        $statutoryCounts = [
            'pf' => HrPfSlab::count(),
            'esi' => HrEsiSlab::count(),
            'pt' => HrProfessionalTaxSlab::count(),
            'tds' => HrTdsSlab::count(),
            'lwf' => HrLwfSlab::count(),
        ];

        return view('hr.settings.index', compact('settings', 'statutoryCounts'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_notice_period_days' => 'required|integer|min:0|max:365',
            'default_probation_months' => 'required|integer|min:0|max:24',
            'enable_ot_approval' => 'nullable|boolean',
            'payroll_cutoff_day' => 'required|integer|min:1|max:31',
        ]);

        set_setting('hr.default_notice_period_days', $validated['default_notice_period_days'], 'integer', 'hr');
        set_setting('hr.default_probation_months', $validated['default_probation_months'], 'integer', 'hr');
        set_setting('hr.enable_ot_approval', $request->boolean('enable_ot_approval'), 'boolean', 'hr');
        set_setting('hr.payroll_cutoff_day', $validated['payroll_cutoff_day'], 'integer', 'hr');

        return redirect()->route('hr.settings.index')
            ->with('success', 'HR settings updated successfully.');
    }
}
