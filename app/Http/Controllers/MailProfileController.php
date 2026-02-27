<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMailProfileRequest;
use App\Http\Requests\UpdateMailProfileRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\MailProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:core.mail_profile.view')->only(['index']);
        $this->middleware('permission:core.mail_profile.create')->only(['create', 'store']);
        $this->middleware('permission:core.mail_profile.update')->only(['edit', 'update', 'sendTest']);
        $this->middleware('permission:core.mail_profile.delete')->only(['destroy']);
    }
    public function index(Request $request)
    {
        $companies = Company::orderBy('name')->get();

        $query = MailProfile::with(['company', 'department']);

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('from_email', 'like', $like)
                    ->orWhere('from_name', 'like', $like)
                    ->orWhere('smtp_host', 'like', $like)
                    ->orWhere('smtp_username', 'like', $like);
            });
        }

        $scope = $request->get('scope');
        if ($scope === 'global') {
            $query->whereNull('company_id')->whereNull('department_id');
        } elseif ($scope === 'company') {
            $query->whereNotNull('company_id')->whereNull('department_id');
        } elseif ($scope === 'department') {
            $query->whereNotNull('department_id');
        }

        $companyId = $request->get('company_id');
        if ($companyId !== null && $companyId !== '') {
            $query->where('company_id', (int) $companyId);
        }

        $status = $request->get('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $default = $request->get('default');
        if ($default === 'default') {
            $query->where('is_default', true);
        } elseif ($default === 'non_default') {
            $query->where('is_default', false);
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = ['code', 'name', 'from_email', 'company_id', 'department_id', 'is_default', 'is_active', 'created_at'];

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            // Preserve existing behaviour
            $query->orderBy('code');
        }

        $profiles = $query->paginate(25)->withQueryString();

        return view('mail_profiles.index', compact('profiles', 'companies'));
    }

    public function create()
    {
        $profile = new MailProfile();
        $companies = Company::orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        return view('mail_profiles.create', compact('profile', 'companies', 'departments'));
    }

    public function store(StoreMailProfileRequest $request)
    {
        $data = $request->validated();
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active');

        if ($data['is_default']) {
            MailProfile::where('is_default', true)->update(['is_default' => false]);
        }

        $profile = new MailProfile();
        $profile->fill($data);
        $this->applyLegacySmtpColumns($profile, $data);
        $profile->save();

        return redirect()
            ->route('mail-profiles.index')
            ->with('success', 'Mail profile created successfully.');
    }

    public function edit(MailProfile $mail_profile)
    {
        $profile = $mail_profile;
        $companies = Company::orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        return view('mail_profiles.edit', compact('profile', 'companies', 'departments'));
    }

    public function update(UpdateMailProfileRequest $request, MailProfile $mail_profile)
    {
        $data = $request->validated();
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active');

        if ($data['is_default']) {
            MailProfile::where('is_default', true)
                ->where('id', '!=', $mail_profile->id)
                ->update(['is_default' => false]);
        }

        $mail_profile->fill($data);
        $this->applyLegacySmtpColumns($mail_profile, $data);
        $mail_profile->save();

        return redirect()
            ->route('mail-profiles.index')
            ->with('success', 'Mail profile updated successfully.');
    }

    public function destroy(MailProfile $mail_profile)
    {
        // guardrail: later we can prevent delete if templates use this profile
        $mail_profile->delete();

        return redirect()
            ->route('mail-profiles.index')
            ->with('success', 'Mail profile deleted successfully.');
    }

    public function sendTest(Request $request, MailProfile $mail_profile)
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        $to = $request->input('test_email');

        // Prepare dynamic mailer config
        $transport = [
            'transport'  => 'smtp',
            'host'       => $mail_profile->smtp_host,
            'port'       => $mail_profile->smtp_port,
            'encryption' => $mail_profile->smtp_encryption ?: null,
            'username'   => $mail_profile->smtp_username,
            'password'   => $mail_profile->smtp_password,
        ];

        config(['mail.mailers.profile_test' => $transport]);
        config(['mail.from' => [
            'address' => $mail_profile->from_email,
            'name'    => $mail_profile->from_name ?: config('app.name'),
        ]]);

        try {
            Mail::mailer('profile_test')->raw(
                'This is a test email from EMS Infra ERP mail profile: '.$mail_profile->code,
                function ($message) use ($to, $mail_profile) {
                    $message->to($to)
                        ->subject('Test email from '.$mail_profile->code);

                    if ($mail_profile->reply_to) {
                        $message->replyTo($mail_profile->reply_to);
                    }
                }
            );

            $mail_profile->update([
                'last_tested_at'    => now(),
                'last_test_success' => true,
                'last_test_error'   => null,
            ]);

            return back()->with('success', 'Test email sent successfully to '.$to.'.');
        } catch (\Throwable $e) {
            Log::error('Mail profile test failed', [
                'profile_id' => $mail_profile->id,
                'error'      => $e->getMessage(),
            ]);

            $mail_profile->update([
                'last_tested_at'    => now(),
                'last_test_success' => false,
                'last_test_error'   => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to send test email: '.$e->getMessage());
        }
    }

    /**
     * Backward-compatibility bridge.
     *
     * Some deployments still have the legacy SMTP columns (host/port/encryption/username/password)
     * created by an older migration. New UI saves smtp_* columns. If those legacy columns exist
     * and are NOT NULL, inserts will fail unless we populate them.
     */
    protected function applyLegacySmtpColumns(MailProfile $profile, array $data): void
    {
        if (! Schema::hasTable('mail_profiles')) {
            return;
        }

        if (Schema::hasColumn('mail_profiles', 'host') && $profile->getAttribute('host') === null) {
            $profile->setAttribute('host', $data['smtp_host'] ?? null);
        }

        if (Schema::hasColumn('mail_profiles', 'port') && $profile->getAttribute('port') === null) {
            $profile->setAttribute('port', $data['smtp_port'] ?? null);
        }

        if (Schema::hasColumn('mail_profiles', 'encryption') && $profile->getAttribute('encryption') === null) {
            $profile->setAttribute('encryption', $data['smtp_encryption'] ?? null);
        }

        if (Schema::hasColumn('mail_profiles', 'username') && $profile->getAttribute('username') === null) {
            $profile->setAttribute('username', $data['smtp_username'] ?? null);
        }

        // Only set legacy password when a new password is explicitly provided.
        if (Schema::hasColumn('mail_profiles', 'password') && array_key_exists('smtp_password', $data)) {
            // NOTE: legacy column might be VARCHAR(255). We intentionally avoid storing raw passwords.
            // This is only to satisfy old NOT NULL constraints in mixed-schema deployments.
            $profile->setAttribute('password', $data['smtp_password'] ? hash('sha256', $data['smtp_password']) : null);
        }
    }
}


