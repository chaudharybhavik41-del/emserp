<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:core.system_setting.view')->only(['general']);
		$this->middleware('permission:core.system_setting.update')->only(['updateGeneral']);
    }

    public function general(SettingsService $settings)
    {
        // Keys we care about initially
        $data = [
            'app_name'      => $settings->get('general', 'app_name', config('app.name')),
            'date_format'   => $settings->get('general', 'date_format', 'd-m-Y'),
            'default_company_id' => $settings->get('general', 'default_company_id', null),

            // Engineering formula settings (used in BOM KPI calculations)
            'plate_area_mode'   => $settings->get('engineering', 'plate_area_mode', 'two_side'),
            'plate_area_factor' => $settings->get('engineering', 'plate_area_factor', 2.0),
            'plate_cut_factor'  => $settings->get('engineering', 'plate_cut_factor', 1.0),
        ];

        $companies = \App\Models\Company::orderBy('name')->get();

        return view('settings.general', compact('data', 'companies'));
    }

    public function updateGeneral(Request $request, SettingsService $settings)
    {
        $validated = $request->validate([
            'app_name'          => 'required|string|max:150',
            'date_format'       => 'required|string|max:50',
            'default_company_id'=> 'nullable|integer|exists:companies,id',

            // Engineering formulas
            'plate_area_mode'   => 'nullable|in:one_side,two_side,two_side_plus_edges,factor',
            'plate_area_factor' => 'nullable|numeric|min:0',
            'plate_cut_factor'  => 'nullable|numeric|min:0',
        ]);

        $settings->set('general', 'app_name', $validated['app_name'], 'string');
        $settings->set('general', 'date_format', $validated['date_format'], 'string');
        $settings->set('general', 'default_company_id', $validated['default_company_id'] ?? null, 'integer');

        // Engineering formulas
        $settings->set('engineering', 'plate_area_mode', $validated['plate_area_mode'] ?? 'two_side', 'string');
        $settings->set('engineering', 'plate_area_factor', $validated['plate_area_factor'] ?? 2.0, 'float');
        $settings->set('engineering', 'plate_cut_factor', $validated['plate_cut_factor'] ?? 1.0, 'float');

        // Also sync APP_NAME (optional, for display)
        config(['app.name' => $validated['app_name']]);

        return redirect()
            ->route('settings.general')
            ->with('success', 'General settings updated.');
    }
}
