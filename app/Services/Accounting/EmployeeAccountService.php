<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use App\Models\Hr\HrEmployee;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class EmployeeAccountService
{
    public function syncAccountForEmployee(HrEmployee $employee, ?int $companyId = null): ?Account
    {
        if (! $this->syncEnabled()) {
            return null;
        }

        $companyId = $this->resolveCompanyId($employee, $companyId);
        $group = $this->resolveAccountGroup($companyId);
        if (! $group) {
            return null;
        }

        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('related_model_type', HrEmployee::class)
            ->where('related_model_id', $employee->id)
            ->first();

        if ($account) {
            $dirty = false;
            $desiredName = $this->desiredAccountName($employee);
            $desiredType = 'ledger';

            if ($account->name !== $desiredName) {
                $account->name = $desiredName;
                $dirty = true;
            }

            if ((bool) $account->is_active !== (bool) $employee->is_active) {
                $account->is_active = (bool) $employee->is_active;
                $dirty = true;
            }

            if ((string) $account->type !== $desiredType) {
                $account->type = $desiredType;
                $dirty = true;
            }

            if ((int) $account->account_group_id !== (int) $group->id) {
                $account->account_group_id = $group->id;
                $dirty = true;
            }

            $desiredPan = $employee->pan_number ?: null;
            if (($account->pan ?? null) !== $desiredPan) {
                $account->pan = $desiredPan;
                $dirty = true;
            }

            if ($account->related_model_type !== HrEmployee::class || (int) $account->related_model_id !== (int) $employee->id) {
                $account->related_model_type = HrEmployee::class;
                $account->related_model_id = $employee->id;
                $dirty = true;
            }

            if ($dirty) {
                $account->save();
            }

            return $account;
        }

        try {
            return $this->createAccountForEmployee($employee, $companyId, $group);
        } catch (QueryException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'accounts_company_related_model_unique')) {
                return Account::query()
                    ->where('company_id', $companyId)
                    ->where('related_model_type', HrEmployee::class)
                    ->where('related_model_id', $employee->id)
                    ->first();
            }

            throw $e;
        }
    }

    protected function createAccountForEmployee(HrEmployee $employee, int $companyId, AccountGroup $group): Account
    {
        return DB::transaction(function () use ($employee, $companyId, $group) {
            $mode = (string) Config::get('accounting.ledger_code_mode', 'manual');
            $code = $mode === 'numeric_auto'
                ? app(AccountCodeGeneratorService::class)->nextCode(
                    companyId: $companyId,
                    accountGroupId: (int) $group->id
                )
                : $this->generateAccountCode($employee, $companyId);

            return Account::create([
                'company_id' => $companyId,
                'account_group_id' => $group->id,
                'code' => $code,
                'name' => $this->desiredAccountName($employee),
                'type' => 'ledger',
                'related_model_type' => HrEmployee::class,
                'related_model_id' => $employee->id,
                'is_active' => (bool) $employee->is_active,
                'is_system' => false,
                'opening_balance' => 0,
                'opening_balance_type' => 'dr',
                'pan' => $employee->pan_number ?: null,
            ]);
        });
    }

    protected function resolveCompanyId(HrEmployee $employee, ?int $companyId = null): int
    {
        $resolved = (int) ($companyId ?: $employee->company_id ?: Config::get('accounting.default_company_id', 1));

        return $resolved > 0 ? $resolved : 1;
    }

    protected function resolveAccountGroup(int $companyId): ?AccountGroup
    {
        $configuredCode = trim((string) Config::get('accounting.hr.employee_ledger_group_code', 'LOANS_ADVANCES'));
        $candidateCodes = array_values(array_filter([
            $configuredCode,
            'LOANS_ADVANCES',
            'CURRENT_ASSETS',
            'CURRENT_LIABILITIES',
        ]));

        foreach ($candidateCodes as $code) {
            $group = AccountGroup::query()
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();

            if ($group) {
                return $group;
            }
        }

        return AccountGroup::query()
            ->where('company_id', $companyId)
            ->whereIn('nature', ['asset', 'liability'])
            ->orderBy('id')
            ->first();
    }

    protected function desiredAccountName(HrEmployee $employee): string
    {
        return trim(($employee->employee_code ?: ('EMP-' . $employee->id)) . ' - ' . $employee->full_name);
    }

    protected function generateAccountCode(HrEmployee $employee, int $companyId): string
    {
        $base = strtoupper(trim((string) ($employee->employee_code ?: ('EMP' . $employee->id))));
        $base = preg_replace('/[^A-Z0-9]+/', '-', $base ?? '') ?: ('EMP-' . $employee->id);
        $candidate = 'EMP-' . $base;

        if (! Account::query()->where('company_id', $companyId)->where('code', $candidate)->exists()) {
            return $candidate;
        }

        $suffix = 2;
        while (Account::query()->where('company_id', $companyId)->where('code', $candidate . '-' . $suffix)->exists()) {
            $suffix++;
        }

        return $candidate . '-' . $suffix;
    }

    protected function syncEnabled(): bool
    {
        return filter_var(
            Config::get('accounting.hr.enable_employee_ledger_sync', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
