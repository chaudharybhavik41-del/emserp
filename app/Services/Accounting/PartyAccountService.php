<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use App\Models\Party;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Party Account Service
 *
 * Handles automatic creation and synchronization of ledger accounts
 * for parties (suppliers, clients, subcontractors).
 *
 * NOTE (Phase 0 Stabilization):
 * The DB schema links accounts to parties using polymorphic columns:
 *   accounts.related_model_type / accounts.related_model_id
 * (NOT accounts.party_id).
 */
class PartyAccountService
{
    /**
     * Get or create a ledger account for a party.
     */
    public function syncAccountForParty(Party $party, ?int $companyId = null): ?Account
    {
        $companyId = $this->resolveCompanyId($companyId);

        // Party must have at least one accounting-relevant role
        $desiredType = $this->determineAccountType($party);
        if (! $desiredType) {
            return null;
        }

        $desiredGroup = $this->resolveAccountGroup($party, $companyId);
        if (! $desiredGroup) {
            return null;
        }

        // Find existing Party-linked account for this company
        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('related_model_type', Party::class)
            ->where('related_model_id', $party->id)
            ->first();

        if ($account) {
            $dirty = false;

            // Always keep identity in sync
            if ($account->name !== $party->name) {
                $account->name = $party->name;
                $dirty = true;
            }

            if ((bool) $account->is_active !== (bool) $party->is_active) {
                $account->is_active = (bool) $party->is_active;
                $dirty = true;
            }

            // Keep tax identifiers in sync (safe – fields already exist on accounts)
            if (($account->gstin ?? null) !== ($party->gstin ?? null)) {
                $account->gstin = $party->gstin;
                $dirty = true;
            }

            if (($account->pan ?? null) !== ($party->pan ?? null)) {
                $account->pan = $party->pan;
                $dirty = true;
            }

            // Keep classification in sync
            if ($account->type !== $desiredType) {
                $account->type = $desiredType;
                $dirty = true;
            }

            if ((int) $account->account_group_id !== (int) $desiredGroup->id) {
                $account->account_group_id = $desiredGroup->id;
                $dirty = true;
            }

            // Heal any legacy / bad data
            if ($account->related_model_type !== Party::class || (int) $account->related_model_id !== (int) $party->id) {
                $account->related_model_type = Party::class;
                $account->related_model_id = $party->id;
                $dirty = true;
            }

            if ($dirty) {
                $account->save();
            }

            return $account;
        }

        // Create new Party ledger
        // NOTE (Phase 5b): with a DB-level unique constraint on
        // (company_id, related_model_type, related_model_id), parallel requests
        // can race. If we lose the race, re-fetch the created account.
        try {
            return $this->createAccountForParty($party, $companyId, $desiredGroup, $desiredType);
        } catch (QueryException $e) {
            $msg = $e->getMessage();

            if (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'accounts_company_related_model_unique')) {
                return Account::query()
                    ->where('company_id', $companyId)
                    ->where('related_model_type', Party::class)
                    ->where('related_model_id', $party->id)
                    ->first();
            }

            throw $e;
        }
    }

    /**
     * Create a new ledger account for a party.
     */
    protected function createAccountForParty(Party $party, int $companyId, AccountGroup $group, string $type): ?Account
    {
        return DB::transaction(function () use ($party, $companyId, $group, $type) {
            $mode = (string) Config::get('accounting.ledger_code_mode', 'manual');

            // Keep party ledgers consistent with numeric ledger codes when enabled.
            if ($mode === 'numeric_auto') {
                $code = app(AccountCodeGeneratorService::class)->nextCode(
                    companyId: $companyId,
                    accountGroupId: (int) $group->id
                );
            } else {
                $code = $this->generateAccountCode($party, $companyId);
            }

            return Account::create([
                'company_id'            => $companyId,
                'account_group_id'      => $group->id,
                'code'                  => $code,
                'name'                  => $party->name,
                'type'                  => $type, // debtor / creditor
                'related_model_type'    => Party::class,
                'related_model_id'      => $party->id,
                'is_active'             => (bool) $party->is_active,
                'is_system'             => false,
                'opening_balance'       => 0,
                'opening_balance_type'  => $this->defaultOpeningBalanceType($type),
                'gstin'                 => $party->gstin,
                'pan'                   => $party->pan,
            ]);
        });
    }

    /**
     * Company is still effectively single-company in current app, but posting services
     * should pass the bill's company_id so ledgers are created under the same company.
     */
    protected function resolveCompanyId(?int $companyId): int
    {
        $companyId = (int) ($companyId ?: Config::get('accounting.default_company_id', 1));
        return $companyId > 0 ? $companyId : 1;
    }

    /**
     * Determine the ledger type based on party classification.
     */
    protected function determineAccountType(Party $party): ?string
    {
        // Priority: Client > Contractor > Supplier
        if ($party->is_client) {
            return 'debtor';
        }

        if ($party->is_contractor || $party->is_supplier) {
            return 'creditor';
        }

        return null;
    }

    /**
     * Resolve the correct account group for this party.
     */
    protected function resolveAccountGroup(Party $party, int $companyId): ?AccountGroup
    {
        $groupCode = $this->determineAccountGroupCode($party);
        if (! $groupCode) {
            return null;
        }

        $group = AccountGroup::query()
            ->where('company_id', $companyId)
            ->where('code', $groupCode)
            ->first();

        if ($group) {
            return $group;
        }

        return $this->findFallbackGroup($companyId, $party);
    }

    /**
     * Determine the account group code based on party type.
     *
     * Supports both:
     *   - accounting.party.* (older draft key)
     *   - accounting.default_groups.* (current config)
     */
    protected function determineAccountGroupCode(Party $party): ?string
    {
        if ($party->is_client) {
            return (string) (
                Config::get('accounting.party.debtor_group_code')
                ?? Config::get('accounting.default_groups.sundry_debtors')
                ?? 'SUNDRY_DEBTORS'
            );
        }

        if ($party->is_contractor || $party->is_supplier) {
            return (string) (
                Config::get('accounting.party.creditor_group_code')
                ?? Config::get('accounting.default_groups.sundry_creditors')
                ?? 'SUNDRY_CREDITORS'
            );
        }

        return null;
    }

    /**
     * Fallback group resolution if configured codes are missing.
     */
    protected function findFallbackGroup(int $companyId, Party $party): ?AccountGroup
    {
        $possibleCodes = $party->is_client
            ? ['SUNDRY_DEBTORS', 'DEBTORS', 'ACCOUNTS_RECEIVABLE']
            : ['SUNDRY_CREDITORS', 'CREDITORS', 'ACCOUNTS_PAYABLE'];

        foreach ($possibleCodes as $code) {
            $group = AccountGroup::query()
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();

            if ($group) {
                return $group;
            }
        }

        // Final fallback by nature (asset for debtor, liability for creditor)
        $nature = $party->is_client ? 'asset' : 'liability';

        return AccountGroup::query()
            ->where('company_id', $companyId)
            ->where('nature', $nature)
            ->where('is_primary', false)
            ->orderBy('id')
            ->first();
    }

    /**
     * Generate a unique account code for the party (unique per company).
     */
    protected function generateAccountCode(Party $party, int $companyId): string
    {
        $base = '';

        if (! empty($party->code)) {
            $base = strtoupper(trim((string) $party->code));
        } else {
            // Generate from name (safe fallback)
            $words = preg_split('/\s+/', trim((string) $party->name)) ?: [];
            $code = '';

            foreach ($words as $word) {
                $word = preg_replace('/[^A-Za-z0-9]/', '', (string) $word);
                if ($word === '') {
                    continue;
                }

                $code .= strtoupper(substr($word, 0, 3));

                if (strlen($code) >= 10) {
                    break;
                }
            }

            $base = $code !== '' ? $code : 'PARTY';
        }

        // Keep only safe chars for codes
        $base = $this->sanitizeAccountCode($base);
        $base = substr($base, 0, 50);

        $code = $base;
        $counter = 1;

        while (
            Account::query()
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->exists()
        ) {
            $suffix = '-' . $counter;
            $code = substr($base, 0, max(1, 50 - strlen($suffix))) . $suffix;
            $counter++;

            // Hard stop (extremely unlikely)
            if ($counter > 9999) {
                $code = $base . '-' . uniqid();
                $code = substr($this->sanitizeAccountCode($code), 0, 50);
                break;
            }
        }

        return $code;
    }

    protected function sanitizeAccountCode(string $code): string
    {
        $code = strtoupper($code);
        $code = str_replace(' ', '-', $code);
        $code = preg_replace('/[^A-Z0-9\-_]/', '', $code) ?? '';
        $code = trim($code, '-_');

        return $code !== '' ? $code : 'PARTY';
    }

    protected function defaultOpeningBalanceType(string $accountType): string
    {
        // Debtors typically have Dr balance, Creditors typically have Cr balance
        return $accountType === 'debtor' ? 'dr' : 'cr';
    }

    /**
     * Get the ledger account for a party (if exists).
     */
    public function getAccountForParty(Party $party, ?int $companyId = null): ?Account
    {
        $companyId = $this->resolveCompanyId($companyId);

        return Account::query()
            ->where('company_id', $companyId)
            ->where('related_model_type', Party::class)
            ->where('related_model_id', $party->id)
            ->first();
    }

    /**
     * Update party account when party details change.
     *
     * (Kept for backward compatibility with existing calls.)
     */
    public function updateAccountFromParty(Party $party, ?int $companyId = null): void
    {
        $this->syncAccountForParty($party, $companyId);
    }

    /**
     * Get all party accounts (for reporting).
     */
    public function getAllPartyAccounts(?string $partyType = null, ?int $companyId = null): Collection
    {
        $companyId = $this->resolveCompanyId($companyId);

        $query = Account::query()
            ->where('company_id', $companyId)
            ->where('related_model_type', Party::class)
            ->whereNotNull('related_model_id')
            ->with('relatedModel');

        if ($partyType) {
            $query->whereHasMorph('relatedModel', [Party::class], function ($q) use ($partyType) {
                match ($partyType) {
                    'supplier'   => $q->where('is_supplier', true),
                    'client'     => $q->where('is_client', true),
                    'contractor' => $q->where('is_contractor', true),
                    default      => null,
                };
            });
        }

        return $query->orderBy('name')->get();
    }
}


