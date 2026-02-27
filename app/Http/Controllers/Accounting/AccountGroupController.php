<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class AccountGroupController extends Controller
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

    /**
     * Flatten groups in a parent->child order and attach a computed `indent_name` attribute.
     *
     * @param  \Illuminate\Support\Collection<int,AccountGroup>  $groups
     * @return array<int,AccountGroup>
     */
    protected function flattenGroups($groups): array
    {
        $byParent = $groups->groupBy('parent_id');
        $flat     = [];

        $walk = function ($parentId, int $depth) use (&$walk, &$byParent, &$flat) {
            $children = $byParent->get($parentId, collect());

            foreach ($children as $g) {
                $g->indent_name = str_repeat('— ', $depth) . $g->name;
                $g->depth       = $depth;
                $flat[]         = $g;

                $walk($g->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $flat;
    }

    /**
     * Build parent dropdown options with indentation.
     */
    protected function parentOptions(int $companyId, ?int $excludeId = null): array
    {
        $groups = AccountGroup::where('company_id', $companyId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $flat = $this->flattenGroups($groups);

        $options = [];
        foreach ($flat as $g) {
            $options[$g->id] = $g->indent_name ?? $g->name;
        }

        return $options;
    }

    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $q         = trim((string) $request->input('q', ''));

        $groups = AccountGroup::with('parent')
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $flatGroups = $this->flattenGroups($groups);

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $flatGroups = array_values(array_filter($flatGroups, function ($g) use ($needle) {
                return str_contains(mb_strtolower((string) $g->code), $needle)
                    || str_contains(mb_strtolower((string) $g->name), $needle);
            }));
        }

        return view('accounting.account_groups.index', compact('companyId', 'flatGroups', 'q'));
    }

    public function create(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $group = new AccountGroup();
        $group->company_id = $companyId;
        $group->sort_order = 0;
        $group->is_primary = false;

        // Optional: preselect parent from querystring.
        $parentId = $request->integer('parent_id');
        if ($parentId) {
            $group->parent_id = $parentId;
        }

        $parentOptions = $this->parentOptions($companyId);

        return view('accounting.account_groups.create', compact('companyId', 'group', 'parentOptions'));
    }

    public function store(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $data = $request->validate([
            'code'       => [
                'required',
                'string',
                'max:30',
                Rule::unique('account_groups', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name'       => ['required', 'string', 'max:255'],
            'nature'     => ['required', 'string', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'parent_id'  => [
                'nullable',
                'integer',
                Rule::exists('account_groups', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['company_id'] = $companyId;
        $data['code'] = strtoupper(trim((string) $data['code']));
        $data['name'] = trim((string) $data['name']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_primary'] = (bool) ($data['is_primary'] ?? false);

        if (! empty($data['parent_id'])) {
            $parent = AccountGroup::where('company_id', $companyId)->findOrFail((int) $data['parent_id']);

            // Enforce consistent nature for sub-groups (inherit from parent).
            $data['nature']     = $parent->nature;
            $data['is_primary'] = false;
        }

        if ($data['is_primary']) {
            // Primary groups cannot have a parent.
            $data['parent_id'] = null;
        }

        AccountGroup::create($data);

        return redirect()
            ->route('accounting.account-groups.index')
            ->with('success', 'Account group created.');
    }

    public function edit(AccountGroup $accountGroup)
    {
        $companyId = $this->defaultCompanyId();

        if ((int) $accountGroup->company_id !== $companyId) {
            abort(404);
        }

        $parentOptions = $this->parentOptions($companyId, $accountGroup->id);

        return view('accounting.account_groups.edit', compact('companyId', 'accountGroup', 'parentOptions'));
    }

    public function update(Request $request, AccountGroup $accountGroup)
    {
        $companyId = $this->defaultCompanyId();

        if ((int) $accountGroup->company_id !== $companyId) {
            abort(404);
        }

        $isPrimary = (bool) $accountGroup->is_primary;

        // Primary groups are treated as "system masters": keep them stable.
        if ($isPrimary) {
            $data = $request->validate([
                'name'       => ['required', 'string', 'max:255'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ]);

            $accountGroup->update([
                'name'       => trim((string) $data['name']),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            return redirect()
                ->route('accounting.account-groups.index')
                ->with('success', 'Primary group updated.');
        }

        $data = $request->validate([
            'code'       => [
                'required',
                'string',
                'max:30',
                Rule::unique('account_groups', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($accountGroup->id),
            ],
            'name'       => ['required', 'string', 'max:255'],
            'nature'     => ['required', 'string', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'parent_id'  => [
                'nullable',
                'integer',
                Rule::exists('account_groups', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['code'] = strtoupper(trim((string) $data['code']));
        $data['name'] = trim((string) $data['name']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_primary'] = (bool) ($data['is_primary'] ?? false);

        if (! empty($data['parent_id'])) {
            // Prevent self-parenting.
            if ((int) $data['parent_id'] === (int) $accountGroup->id) {
                return back()->withErrors(['parent_id' => 'A group cannot be its own parent.'])->withInput();
            }

            $parent = AccountGroup::where('company_id', $companyId)->findOrFail((int) $data['parent_id']);
            $data['nature']     = $parent->nature;
            $data['is_primary'] = false;
        }

        if ($data['is_primary']) {
            $data['parent_id'] = null;
        }

        $accountGroup->update($data);

        return redirect()
            ->route('accounting.account-groups.index')
            ->with('success', 'Account group updated.');
    }

    public function destroy(AccountGroup $accountGroup)
    {
        $companyId = $this->defaultCompanyId();

        if ((int) $accountGroup->company_id !== $companyId) {
            abort(404);
        }

        if ((bool) $accountGroup->is_primary) {
            return back()->with('error', 'Primary groups cannot be deleted.');
        }

        $hasChildren = AccountGroup::where('company_id', $companyId)
            ->where('parent_id', $accountGroup->id)
            ->exists();

        if ($hasChildren) {
            return back()->with('error', 'This group has sub-groups. Delete/move them first.');
        }

        $hasAccounts = Account::where('company_id', $companyId)
            ->where('account_group_id', $accountGroup->id)
            ->exists();

        if ($hasAccounts) {
            return back()->with('error', 'This group is linked to accounts. Move those accounts first.');
        }

        $accountGroup->delete();

        return redirect()
            ->route('accounting.account-groups.index')
            ->with('success', 'Account group deleted.');
    }
}
