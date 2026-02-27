<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecuritySettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:core.system_setting.update');
    }

    /**
     * Show security settings form.
     */
    public function index()
    {
        $settings = DB::table('system_settings')
            ->where('group', 'security')
            ->pluck('value', 'key')
            ->toArray();

        return view('settings.security', compact('settings'));
    }

    /**
     * Update security settings.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'password_min_length' => ['required', 'integer', 'min:6', 'max:32'],
            'password_require_uppercase' => ['required', 'in:true,false'],
            'password_require_number' => ['required', 'in:true,false'],
            'password_require_special' => ['required', 'in:true,false'],
            'password_expiry_days' => ['required', 'integer', 'min:0', 'max:365'],
            'password_history_count' => ['required', 'integer', 'min:0', 'max:24'],
            'max_login_attempts' => ['required', 'integer', 'min:3', 'max:10'],
            'lockout_duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'allow_multiple_sessions' => ['required', 'in:true,false'],
        ]);

        $oldSettings = DB::table('system_settings')
            ->where('group', 'security')
            ->pluck('value', 'key')
            ->toArray();

        foreach ($data as $key => $value) {
            DB::table('system_settings')
                ->updateOrInsert(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'type' => is_numeric($value) ? 'integer' : 'boolean',
                        'group' => 'security',
                        'updated_at' => now(),
                    ]
                );
        }

        ActivityLog::log(
            ActivityLog::ACTION_UPDATED,
            null,
            'Updated security settings',
            $oldSettings,
            $data
        );

        return back()->with('success', 'Security settings updated successfully.');
    }
}
