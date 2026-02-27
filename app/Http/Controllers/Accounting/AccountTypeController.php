<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountTypeController extends Controller
{
    public function __construct()
    {
        // Reuse existing Chart of Accounts permissions.
        $this->middleware('permission:accounting.accounts.view')->only(['index']);
        $this->middleware('permission:accounting.accounts.update')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $q         = trim((string) $request->input('q', ''));

        $types = AccountType::query()
            ->where('company_id', $companyId)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('code', 'like', '%' . $q . '%')
                        ->orWhere('name', 'like', '%' . $q . '%');
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('accounting.account_types.index', compact('companyId', 'types', 'q'));
    }

    public function create()
    {
        $companyId = $this->defaultCompanyId();

        $accountType = new AccountType();
        $accountType->company_id = $companyId;
        $accountType->is_active  = true;
        $accountType->is_system  = false;
        $accountType->sort_order = 0;

        return view('accounting.account_types.create', compact('companyId', 'accountType'));
    }

    public function store(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $data = $request->validate([
            'code'       => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('account_types', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name'       => ['required', 'string', 'max:150'],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['company_id'] = $companyId;
        $data['code']       = Str::lower(trim((string) $data['code']));
        $data['name']       = trim((string) $data['name']);
        $data['is_active']  = (bool) ($data['is_active'] ?? false);
        $data['is_system']  = false;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        AccountType::create($data);

        return redirect()
            ->route('accounting.account-types.index')
            ->with('success', 'Account type created.');
    }

    public function edit(AccountType $accountType)
    {
        $companyId = $this->defaultCompanyId();

        if ((int) $accountType->company_id !== $companyId) {
            abort(404);
        }

        return view('accounting.account_types.edit', compact('companyId', 'accountType'));
    }

    public function update(Request $request, AccountType $accountType)
    {
        $companyId = $this->defaultCompanyId();

        if ((int) $accountType->company_id !== $companyId) {
            abort(404);
        }

        $isSystem = (bool) $accountType->is_system;

        $rules = [
            'name'       => ['required', 'string', 'max:150'],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        if (! $isSystem) {
            $rules['code'] = [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('account_types', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($accountType->id),
            ];
        }

        $data = $request->validate($rules);

        $requestedActive = (bool) ($data['is_active'] ?? false);

        // Prevent disabling a type that is already in use (would break validations / existing accounts).
        if (! $isSystem && ! $requestedActive) {
            $inUse = Account::where('company_id', $companyId)
                ->where('type', $accountType->code)
                ->exists();

            if ($inUse) {
                return back()
                    ->withErrors(['is_active' => 'This type is already used by accounts. Update those accounts first, then deactivate.'])
                    ->withInput();
            }
        }

        $update = [
            'name'       => trim((string) $data['name']),
            'is_active'  => $isSystem ? true : $requestedActive,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        if (! $isSystem) {
            $update['code'] = Str::lower(trim((string) $data['code']));
        }

        $accountType->update($update);

        return redirect()
            ->route('accounting.account-types.index')
            ->with('success', 'Account type updated.');
    }

    public function destroy(AccountType $accountType)
    {
        $companyId = $this->defaultCompanyId();

        if ((int) $accountType->company_id !== $companyId) {
            abort(404);
        }

        if ((bool) $accountType->is_system) {
            return back()->with('error', 'System account types cannot be deleted.');
        }

        $inUse = Account::where('company_id', $companyId)
            ->where('type', $accountType->code)
            ->exists();

        if ($inUse) {
            return back()->with('error', 'This type is used by accounts. Update those accounts first.');
        }

        $accountType->delete();

        return redirect()
            ->route('accounting.account-types.index')
            ->with('success', 'Account type deleted.');
    }
}
