<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\AccountType;
use App\Models\Accounting\VoucherLine;
use App\Models\GstAccountRate;
use App\Models\Party;
use App\Services\Accounting\AccountGstRateRecorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Services\Accounting\AccountCodeGeneratorService;

class AccountController extends Controller
{
    /**
     * Ledger types available in the UI.
     */
    protected array $ledgerTypes = [
        'ledger'   => 'Ledger (Generic)',
        'debtor'   => 'Sundry Debtor (Customer)',
        'creditor' => 'Sundry Creditor (Supplier / Contractor)',
        'party'    => 'Party (Auto-managed from Party master)',
        'bank'     => 'Bank Account',
        'cash'     => 'Cash-in-hand',
        'tax'      => 'Tax / Duty',
        'inventory'=> 'Inventory',
        'wip'      => 'Work-in-progress',
        'income'   => 'Income',
        'expense'  => 'Expense',
    ];

    public function __construct(
        protected AccountGstRateRecorderService $accountGstRateRecorder,
        protected AccountCodeGeneratorService $accountCodeGenerator
    ) {
        $this->middleware('permission:accounting.accounts.view')->only('index');
        $this->middleware('permission:accounting.accounts.create')->only(['create', 'store']);
        $this->middleware('permission:accounting.accounts.update')->only(['edit', 'update']);
        $this->middleware('permission:accounting.accounts.delete')->only('destroy');
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    /**
     * Build a flattened group list with indentation for the dropdown.
     */
    protected function groupOptions(): array
    {
        $companyId = $this->defaultCompanyId();

        $groups = AccountGroup::where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $byParent = $groups->groupBy('parent_id');
        $result   = [];

        $walk = function ($parentId, int $depth) use (&$walk, & $byParent, & $result) {
            $children = $byParent->get($parentId, collect());
            foreach ($children as $group) {
                $group->indent_name = str_repeat('— ', $depth) . $group->name;
                $result[]           = $group;
                $walk($group->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $result;
    }


    /**
     * Ledger/account types used in the UI dropdown.
     *
     * We support BOTH:
     * 1) Database-driven types (account_types table) for user-managed types, and
     * 2) A safe fallback to the hardcoded list (for older DBs or before migrations are run).
     */
    protected function ledgerTypeOptions(?int $companyId = null): array
    {
        $companyId = $companyId ?? $this->defaultCompanyId();

        // Safe fallback (existing behaviour)
        $fallback = $this->ledgerTypes;

        try {
            if (!Schema::hasTable('account_types')) {
                return $fallback;
            }

            $types = AccountType::where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['code', 'name']);

            $map = [];
            foreach ($types as $t) {
                $map[$t->code] = $t->name;
            }

            return !empty($map) ? $map : $fallback;
        } catch (\Throwable $e) {
            // Never break the COA screen due to a missing table, bad migration, etc.
            return $fallback;
        }
    }

    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $ledgerTypes = $this->ledgerTypeOptions($companyId);

        // Filters
        $q        = trim((string) $request->get('q', ''));
        $groupId  = $request->integer('group_id') ?: null;
        $type     = trim((string) $request->get('type', ''));
        $status   = trim((string) $request->get('status', '')); // all|active|inactive

        // Load groups (for tree + filter dropdown)
        $groups = AccountGroup::where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Build descendants map for group filter (parent -> all descendants)
        $childrenByParent = $groups->groupBy('parent_id');

        $collectDescendants = function (?int $pid) use (&$collectDescendants, $childrenByParent) {
            $ids = [];
            $children = $childrenByParent->get($pid, collect());
            foreach ($children as $c) {
                $ids[] = (int) $c->id;
                $ids = array_merge($ids, $collectDescendants((int) $c->id));
            }
            return $ids;
        };

        $filterGroupIds = [];
        if ($groupId) {
            $filterGroupIds = array_merge([(int) $groupId], $collectDescendants((int) $groupId));
        }

        // Accounts query (no pagination; tree view works best with full set)
        $accountsQuery = Account::with('group')
            ->where('company_id', $companyId);

        if ($q !== '') {
            $accountsQuery->where(function ($qq) use ($q) {
                $qq->where('name', 'like', '%' . $q . '%')
                    ->orWhere('code', 'like', '%' . $q . '%');
            });
        }

        if (!empty($filterGroupIds)) {
            $accountsQuery->whereIn('account_group_id', $filterGroupIds);
        }

        if ($type !== '') {
            $accountsQuery->where('type', $type);
        }

        if ($status === 'active') {
            $accountsQuery->where('is_active', true);
        } elseif ($status === 'inactive') {
            $accountsQuery->where('is_active', false);
        }

        // Order: numeric codes first by code, then by name
        $accounts = $accountsQuery
            ->orderByRaw("CASE WHEN accounts.code REGEXP '^[0-9]+$' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->orderBy('name')
            ->get();

        $accountsByGroupId = $accounts->groupBy('account_group_id');

        // Build nested group tree nodes
        $buildTree = function ($parentId, int $depth) use (&$buildTree, $childrenByParent, $accountsByGroupId) {
            $nodes = [];
            $children = $childrenByParent->get($parentId, collect());
            foreach ($children as $g) {
                $accs = $accountsByGroupId->get($g->id, collect());
                $childNodes = $buildTree($g->id, $depth + 1);

                // Determine whether this node has any visible content
                $hasChildContent = false;
                foreach ($childNodes as $cn) {
                    if (!empty($cn['has_content'])) { $hasChildContent = true; break; }
                }

                $hasContent = ($accs->count() > 0) || $hasChildContent;

                $nodes[] = [
                    'group' => $g,
                    'depth' => $depth,
                    'accounts' => $accs,
                    'children' => $childNodes,
                    'has_content' => $hasContent,
                ];
            }
            return $nodes;
        };

        $tree = $buildTree(null, 0);

        // Filter dropdown options (flat)
        $flatGroups = $this->flattenGroupsForFilter($groups);

        return view('accounting.accounts.index', [
            'companyId' => $companyId,
            'ledgerTypes' => $ledgerTypes,
            'accountsCount' => $accounts->count(),
            'tree' => $tree,
            'flatGroups' => $flatGroups,

            // filters
            'q' => $q,
            'groupId' => $groupId,
            'type' => $type,
            'status' => $status,
            'hasFilters' => ($q !== '' || $groupId || $type !== '' || in_array($status, ['active','inactive'], true)),
        ]);
    }
    /**
     * Flatten groups for filters with indentation.
     *
     * @return array<int, \App\Models\Accounting\AccountGroup>
     */
    protected function flattenGroupsForFilter($groups): array
    {
        $byParent = $groups->groupBy('parent_id');
        $out = [];

        $walk = function ($parentId, int $depth) use (&$walk, &$byParent, &$out) {
            $children = $byParent->get($parentId, collect());
            foreach ($children as $g) {
                $g->indent_name = str_repeat('— ', $depth) . $g->name;
                $out[] = $g;
                $walk($g->id, $depth + 1);
            }
        };

        $walk(null, 0);
        return $out;
    }

    /**
     * Allowed ledger types for a given account group (prevents mismatches).
     */
    protected function allowedTypesForGroup(AccountGroup $group, array $ledgerTypes): array
    {
        $code = strtoupper(trim((string) ($group->code ?? '')));
        $nature = strtolower(trim((string) ($group->nature ?? '')));

        // Hard allowlist by group code (safe defaults; can be extended anytime)
        $map = [
            'BANK_ACCOUNTS'     => ['bank'],
            'CASH_IN_HAND'      => ['cash'],
            'SUNDRY_DEBTORS'    => ['debtor'],
            'SUNDRY_CREDITORS'  => ['creditor'],
'BANK_OD_CC'        => ['bank'], // OD/CC are liability but still "bank-like" for payments
            'GST_INPUT_GROUP'   => ['tax'],
            'GST_OUTPUT_GROUP'  => ['tax'],
            'DUTIES_TAXES'      => ['tax'],
            'TDS_PAYABLE_G'     => ['tax'],
            'TDS_RECEIVABLE_G'  => ['tax'],
            'TCS_RECEIVABLE_G'  => ['tax'],

            'INVENTORY'         => ['inventory'],

            // WIP group typically uses 'wip' type (keep ledger allowed for flexibility)
            'WORK_IN_PROGRESS'  => ['wip', 'ledger'],

            // Income
            'SALES'             => ['income', 'ledger'],
            'REVENUE'           => ['income', 'ledger'],
            'OTHER_INCOME'      => ['income', 'ledger'],

            // Expenses
            'DIRECT_EXPENSES'   => ['expense', 'ledger'],
            'INDIRECT_EXPENSES' => ['expense', 'ledger'],
            'CONSUMABLE_EXP'    => ['expense', 'ledger'],
        ];

        $allowed = $map[$code] ?? [];

        // Fallback by nature (broad but still safe)
        if (empty($allowed)) {
            $allowed = match ($nature) {
                'asset'     => ['ledger', 'bank', 'cash', 'debtor', 'inventory', 'tax'],
                'liability' => ['ledger', 'creditor', 'tax'],
                'equity'    => ['ledger'],
                'income'    => ['income', 'ledger'],
                'expense'   => ['expense', 'ledger'],
                default     => ['ledger'],
            };
        }

        // Never allow manual 'party' type in UI
        $allowed = array_values(array_filter($allowed, fn ($t) => $t !== 'party'));

        // Keep only types that exist in ledgerTypes list
        $allowed = array_values(array_filter($allowed, fn ($t) => array_key_exists($t, $ledgerTypes)));

        // Ensure at least ledger remains if possible
        if (empty($allowed) && array_key_exists('ledger', $ledgerTypes)) {
            $allowed = ['ledger'];
        }

        return $allowed;
    }

    /**
     * Build group_id -> allowed types map for JS filtering on create/edit form.
     */
    protected function groupTypeMap(int $companyId, array $ledgerTypes): array
    {
        $groups = AccountGroup::where('company_id', $companyId)->get(['id', 'code', 'nature']);
        $map = [];
        foreach ($groups as $g) {
            $map[(int) $g->id] = $this->allowedTypesForGroup($g, $ledgerTypes);
        }
        return $map;
    }

    public function create()
    {
        $account                    = new Account();
        $account->company_id        = $this->defaultCompanyId();
        $account->is_gst_applicable = false;

        $groups      = $this->groupOptions();
        $ledgerTypes = $this->ledgerTypeOptions($account->company_id);
        $hasVouchers = false;
        $latestGstRate  = null;

        $groupTypeMap = $this->groupTypeMap($account->company_id, $ledgerTypes);

        return view('accounting.accounts.create', compact(
            'account',
            'groups',
            'ledgerTypes',
            'groupTypeMap',
            'hasVouchers',
            'latestGstRate'
        ));
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->input('company_id', $this->defaultCompanyId());

        $ledgerTypes = $this->ledgerTypeOptions($companyId);

        $rules = [
            'company_id'           => ['nullable', 'integer'],
            'account_group_id'     => ['required', 'integer', 'exists:account_groups,id'],
            'name'                 => ['required', 'string', 'max:255'],
            'code'                 => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('accounts', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'type'                 => ['required', 'string', Rule::in(array_keys($ledgerTypes))],
            'opening_balance'      => ['nullable', 'numeric'],
            'opening_balance_type' => ['required', Rule::in(['dr', 'cr'])],
            'opening_balance_date' => ['nullable', 'date'],
            'gstin'                => ['nullable', 'string', 'max:32'],
            'pan'                  => ['nullable', 'string', 'max:32'],
            'credit_limit'         => ['nullable', 'numeric'],
            'credit_days'          => ['nullable', 'integer'],
            'is_active'            => ['boolean'],
            'is_gst_applicable'    => ['nullable', 'boolean'],

            // GST config fields
            'hsn_sac_code'        => ['nullable', 'string', 'max:32'],
            'gst_rate_percent'    => ['nullable', 'numeric', 'between:0,100'],
            'gst_effective_from'  => ['nullable', 'date'],
            'is_reverse_charge'   => ['nullable', 'boolean'],
        ];

        $mode = (string) config('accounting.ledger_code_mode', 'manual');

        // In numeric_auto mode, ledger codes are system-generated and non-editable.
        // Ignore any incoming code and avoid validation rejecting user-entered duplicates.
        if ($mode === 'numeric_auto') {
            $rules['code'] = ['nullable', 'string', 'max:64'];
        }

        $data = $request->validate($rules);

        // Enforce type validity for selected group (prevents mismatch)
        $selectedGroup = AccountGroup::query()
            ->where('company_id', $companyId)
            ->where('id', (int) $data['account_group_id'])
            ->first();

        if (! $selectedGroup) {
            return back()->withErrors(['account_group_id' => 'Invalid account group.'])->withInput();
        }

        $allowedTypes = $this->allowedTypesForGroup($selectedGroup, $ledgerTypes);
        if (! in_array((string) $data['type'], $allowedTypes, true)) {
            return back()
                ->withErrors(['type' => 'Selected Type is not allowed for the chosen Group.'])
                ->withInput();
        }

        if ($mode === 'numeric_auto') {
            $data['code'] = $this->accountCodeGenerator->nextCode(
                companyId: $companyId,
                accountGroupId: (int) $data['account_group_id']
            );
        }
        $data['company_id']        = $companyId;
        $data['is_active']         = $request->boolean('is_active', true);
        $data['is_gst_applicable'] = $request->boolean('is_gst_applicable', false);

        // Block manual creation of ledgers with Party codes to avoid duplicates with PartyAccountService.
        if ($mode !== 'numeric_auto' && ! empty($data['code'])) {
            $partyWithSameCode = Party::where('code', $data['code'])->first();
            if ($partyWithSameCode) {
                return back()
                    ->withErrors([
                        'code' => 'This code is already used by Party "' . $partyWithSameCode->name . '". ' .
                            'Party ledgers are auto-managed from Party master.',
                    ])
                    ->withInput();
            }
        }

        // If GST is marked applicable, ensure rate is present.
        if ($data['is_gst_applicable'] && ($data['gst_rate_percent'] === null)) {
            return back()
                ->withErrors(['gst_rate_percent' => 'GST rate is required when GST is applicable on this ledger.'])
                ->withInput();
        }

        // Only persist account columns; GST fields go into GST history.
        $accountData = Arr::only($data, [
            'company_id',
            'account_group_id',
            'name',
            'code',
            'type',
            'opening_balance',
            'opening_balance_type',
            'opening_balance_date',
            'gstin',
            'pan',
            'credit_limit',
            'credit_days',
            'is_active',
            'is_gst_applicable',
        ]);

        $account = Account::create($accountData);

        // Maintain GST rate history for this ledger (DEV-6)
        $this->accountGstRateRecorder->syncForAccount(
            $account,
            $data['is_gst_applicable'] ? ($data['gst_rate_percent'] ?? null) : null,
            $data['gst_effective_from'] ?? null,
            $data['hsn_sac_code'] ?? null,
            (bool) ($data['is_reverse_charge'] ?? false),
        );

        return redirect()
            ->route('accounting.accounts.index')
            ->with('success', 'Account created successfully.');
    }

    public function edit(Account $account)
    {
        $groups      = $this->groupOptions();
        $ledgerTypes = $this->ledgerTypeOptions($account->company_id);
        $hasVouchers = VoucherLine::where('account_id', $account->id)->exists();
        $today = now()->toDateString();
        $latestGstRate = GstAccountRate::query()
            ->where('account_id', $account->id)
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $today);
            })
            ->orderByDesc('effective_from')
            ->first();

        if (! $latestGstRate) {
            $latestGstRate = GstAccountRate::query()
                ->where('account_id', $account->id)
                ->orderByDesc('effective_from')
                ->first();
        }

        $groupTypeMap = $this->groupTypeMap((int) $account->company_id, $ledgerTypes);

        return view('accounting.accounts.edit', compact(
            'account',
            'groups',
            'ledgerTypes',
            'groupTypeMap',
            'hasVouchers',
            'latestGstRate'
        ));
    }

    public function update(Request $request, Account $account)
    {

  
        $ledgerTypes = $this->ledgerTypeOptions((int) $account->company_id);

        $rules = [
            'account_group_id'     => ['required', 'integer', 'exists:account_groups,id'],
            'name'                 => ['required', 'string', 'max:255'],
            'code'                 => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('accounts', 'code')
                    ->where(fn ($q) => $q->where('company_id', $account->company_id))
                    ->ignore($account->id),
            ],
            'type'                 => ['required', 'string', Rule::in(array_keys($ledgerTypes))],
            'opening_balance'      => ['nullable', 'numeric'],
            'opening_balance_type' => ['required', Rule::in(['dr', 'cr'])],
            'opening_balance_date' => ['nullable', 'date'],
            'gstin'                => ['nullable', 'string', 'max:32'],
            'pan'                  => ['nullable', 'string', 'max:32'],
            'credit_limit'         => ['nullable', 'numeric'],
            'credit_days'          => ['nullable', 'integer'],
            'is_active'            => ['boolean'],
            'is_gst_applicable'    => ['nullable', 'boolean'],

            'hsn_sac_code'        => ['nullable', 'string', 'max:32'],
            'gst_rate_percent'    => ['nullable', 'numeric', 'between:0,100'],
            'gst_effective_from'  => ['nullable', 'date'],
            'is_reverse_charge'   => ['nullable', 'boolean'],
        ];

        $data                      = $request->validate($rules);

        // Enforce type validity for selected group (prevents mismatch)
        $selectedGroup = AccountGroup::query()
            ->where('company_id', (int) $account->company_id)
            ->where('id', (int) $data['account_group_id'])
            ->first();

        if (! $selectedGroup) {
            return back()->withErrors(['account_group_id' => 'Invalid account group.'])->withInput();
        }

        $allowedTypes = $this->allowedTypesForGroup($selectedGroup, $ledgerTypes);
        if (! in_array((string) $data['type'], $allowedTypes, true)) {
            return back()
                ->withErrors(['type' => 'Selected Type is not allowed for the chosen Group.'])
                ->withInput();
        }
$data['is_active'] = (int) $request->boolean('is_active');
        $data['is_gst_applicable'] = $request->boolean('is_gst_applicable', false);

        if ($data['is_gst_applicable'] && ($data['gst_rate_percent'] === null)) {
            return back()
                ->withErrors(['gst_rate_percent' => 'GST rate is required when GST is applicable on this ledger.'])
                ->withInput();
        }

        
        $mode = (string) config('accounting.ledger_code_mode', 'manual');

        // Ledger codes are system-managed in numeric_auto mode (and for any numeric ledger code).
        // Even if a user tampers with the request, we keep code immutable.
        if ($mode === 'numeric_auto' || preg_match('/^\d+$/', (string) $account->code)) {
            $incomingCode = array_key_exists('code', $data) ? trim((string) $data['code']) : (string) $account->code;

            if ($incomingCode !== '' && $incomingCode !== (string) $account->code) {
                return back()
                    ->withErrors(['code' => 'Ledger code is auto-generated and cannot be changed.'])
                    ->withInput();
            }

            // Force preserve code (avoid accidental null when field is disabled)
            $data['code'] = $account->code;
        }

$hasVouchers  = VoucherLine::where('account_id', $account->id)->exists();
        $isPartyLedger = $account->related_model_type === Party::class && $account->related_model_id;
        $isSystem      = (bool) $account->is_system;

        // If vouchers exist, block changing OB fields.
            // if ($hasVouchers) {
            //     $incomingObAmount = $data['opening_balance'] ?? null;
            //     $incomingObType   = $data['opening_balance_type'] ?? null;
            //     $incomingObDate   = $data['opening_balance_date'] ?? null;

            //     $currentObAmount = $account->opening_balance;
            //     $currentObType   = $account->opening_balance_type;
            //     $currentObDate   = $account->opening_balance_date ? $account->opening_balance_date->format('Y-m-d') : null;

            //     if (
            //         (string) $incomingObAmount !== (string) $currentObAmount
            //         || (string) $incomingObType !== (string) $currentObType
            //         || (string) $incomingObDate !== (string) $currentObDate
            //     ) {
            //         return back()
            //             ->withErrors([
            //                 'opening_balance' => 'Opening balance fields cannot be changed after vouchers exist for this ledger. ' .
            //                     'Please use a journal voucher to adjust balances.',
            //             ])
            //             ->withInput();
            //     }
            // }


            if ($hasVouchers) {
    $incomingObAmount = $data['opening_balance'] ?? null;
    $incomingObType   = $data['opening_balance_type'] ?? null;
    $incomingObDate   = $data['opening_balance_date'] ?? null;

    $currentObAmount = $account->opening_balance;
    $currentObType   = $account->opening_balance_type;
    $currentObDate   = $account->opening_balance_date ? $account->opening_balance_date->format('Y-m-d') : null;

    // Optional: log a notice if balances are different
    if (
        (string) $incomingObAmount !== (string) $currentObAmount
        || (string) $incomingObType !== (string) $currentObType
        || (string) $incomingObDate !== (string) $currentObDate
    ) {
        // No error, just allow update
        // You can log or handle audit here if needed
        // Example:
        // Log::info("Opening balance changed for account {$account->id}");
    }
}

// Proceed to update the account normally
$account->update([
    'opening_balance' => $data['opening_balance'] ?? $account->opening_balance,
    'opening_balance_type' => $data['opening_balance_type'] ?? $account->opening_balance_type,
    'opening_balance_date' => $data['opening_balance_date'] ?? $account->opening_balance_date,
]);
        if ($isSystem) {
            $blocked = [];

            if (($data['code'] ?? $account->code) !== $account->code) {
                $blocked[] = 'Code';
            }
            if (($data['account_group_id'] ?? $account->account_group_id) != $account->account_group_id) {
                $blocked[] = 'Group';
            }
            if (($data['type'] ?? $account->type) !== $account->type) {
                $blocked[] = 'Type';
            }
            if (array_key_exists('is_active', $data) && $data['is_active'] == false && $account->is_active) {
                $blocked[] = 'Active status';
            }

            if ($blocked) {
                return back()
                    ->withErrors([
                        'name' => 'This is a system account. You cannot change: ' . implode(', ', $blocked) . '.',
                    ])
                    ->withInput();
            }
        }

        if ($isPartyLedger) {
            // For Party-linked ledgers, keep identity fields controlled from Party master.
            $accountData = Arr::only($data, [
                'account_group_id',
                'type',
                'opening_balance',
                'opening_balance_type',
                'opening_balance_date',
                'credit_limit',
                'is_active',
                'is_gst_applicable',
            ]);
        } else {
            $accountData = Arr::only($data, [
                'account_group_id',
                'name',
                'code',
                'type',
                'opening_balance',
                'opening_balance_type',
                'opening_balance_date',
                'gstin',
                'pan',
                'credit_limit',
                'credit_days',
                'is_active',
                'is_gst_applicable',
            ]);
        }

        $account->update($accountData);

        $this->accountGstRateRecorder->syncForAccount(
            $account,
            $data['is_gst_applicable'] ? ($data['gst_rate_percent'] ?? null) : null,
            $data['gst_effective_from'] ?? null,
            $data['hsn_sac_code'] ?? null,
            (bool) ($data['is_reverse_charge'] ?? false),
        );

        return redirect()
            ->route('accounting.accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    public function destroy(Account $account)
    {
        // Do not allow deleting system accounts at all.
        if ($account->is_system) {
            return redirect()
                ->route('accounting.accounts.index')
                ->with('error', 'This is a system account and cannot be deleted.');
        }

        // Do not allow deleting ledgers which have been used in vouchers.
        $hasVouchers = VoucherLine::where('account_id', $account->id)->exists();

        if ($hasVouchers) {
            return redirect()
                ->route('accounting.accounts.index')
                ->with('error', 'Account has voucher entries and cannot be deleted. Mark it inactive instead.');
        }

        $account->delete();

        return redirect()
            ->route('accounting.accounts.index')
            ->with('success', 'Account deleted successfully.');
    }
}
