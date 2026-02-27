<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountCodeSequence;
use App\Models\Accounting\AccountGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountCodeGeneratorService
{
    /**
     * Generate next numeric ledger code for a company + account group.
     *
     * Example:
     *   prefix 1110 + pad 3 => 1110001, 1110002 ...
     */
    public function nextCode(int $companyId, int $accountGroupId): string
    {
        /** @var AccountGroup|null $group */
        $group = AccountGroup::query()
            ->with('parent')
            ->where('company_id', $companyId)
            ->where('id', $accountGroupId)
            ->first();

        if (! $group) {
            throw ValidationException::withMessages([
                'account_group_id' => 'Account group not found for this company.',
            ]);
        }

        $seriesKey = $this->resolveSeriesKey($group);

        return DB::transaction(function () use ($companyId, $group, $seriesKey) {
            /** @var AccountCodeSequence|null $seq */
            $seq = AccountCodeSequence::query()
                ->where('company_id', $companyId)
                ->where('series_key', $seriesKey)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                // Create sequence on first use (auto-alloc prefix if needed)
                $default = $this->defaultSeriesConfig(
                    companyId: $companyId,
                    group: $group,
                    seriesKey: $seriesKey,
                    nature: (string) $group->nature
                );

                $seq = AccountCodeSequence::create([
                    'company_id'   => $companyId,
                    'series_key'   => $seriesKey,
                    'prefix'       => $default['prefix'],
                    'next_number'  => (int) ($default['start'] ?? 1),
                    'pad_width'    => (int) ($default['pad'] ?? 3),
                ]);
            }

            $number = (int) $seq->next_number;

            $code = (string) $seq->prefix . str_pad((string) $number, (int) $seq->pad_width, '0', STR_PAD_LEFT);

            $seq->next_number = $number + 1;
            $seq->save();

            return $code;
        });
    }

    /**
     * Resolve series_key based on group code, else fall back to nature-based key.
     */
    protected function resolveSeriesKey(AccountGroup $group): string
    {
        $code = strtoupper(trim((string) ($group->code ?? '')));
        if ($code !== '') {
            return $code;
        }

        $nature = strtoupper(trim((string) ($group->nature ?? '')));
        if ($nature !== '') {
            return 'DEFAULT_' . $nature; // DEFAULT_ASSET, DEFAULT_LIABILITY, ...
        }

        return 'DEFAULT';
    }

    /**
     * Default series config from config/accounting.php:
     *  - accounting.ledger_code_prefix_by_series_key
     *  - accounting.ledger_code_prefix_by_nature
     *
     * Enhancements:
     *  - If the series key isn't configured, we AUTO-ALLOCATE a prefix based on the group's parent hierarchy,
     *    and store it in account_code_sequences when the first ledger is created.
     */
    protected function defaultSeriesConfig(int $companyId, AccountGroup $group, string $seriesKey, string $nature): array
    {
        $byKey    = (array) config('accounting.ledger_code_prefix_by_series_key', []);
        $byNature = (array) config('accounting.ledger_code_prefix_by_nature', []);

        // 1) Exact series mapping in config
        if (isset($byKey[$seriesKey]) && is_array($byKey[$seriesKey])) {
            return $byKey[$seriesKey];
        }

        // 2) Auto-allocate for custom/non-mapped groups (NOT for DEFAULT_* keys)
        if (! str_starts_with($seriesKey, 'DEFAULT_')) {
            $prefix = $this->allocateHierarchyPrefix($companyId, $group);
            return ['prefix' => $prefix, 'pad' => 3, 'start' => 1];
        }

        // 3) Nature fallback
        $natureKey = strtolower($nature);
        if ($natureKey !== '' && isset($byNature[$natureKey]) && is_array($byNature[$natureKey])) {
            return $byNature[$natureKey];
        }

        // 4) Last fallback
        return ['prefix' => '9999', 'pad' => 3, 'start' => 1];
    }

    /**
     * Allocate a unique 4-digit prefix under the parent hierarchy (Tally-style blocks).
     *
     * Rules:
     *  - Nature base is X000 (Asset=1000, Liability=2000, Equity=3000, Income=4000, Expense=5000)
     *  - If parent prefix is X000 -> children step 100 (1100, 1200, ...)
     *  - If parent prefix ends with 00 -> children step 10  (1110, 1120, ...)
     *  - If parent prefix ends with 0  -> children step 1   (1111, 1112, ...)
     *
     * The allocated prefix is stored only when the sequence row is created (caller does that).
     */
    protected function allocateHierarchyPrefix(int $companyId, AccountGroup $group): string
    {
        $byKey = (array) config('accounting.ledger_code_prefix_by_series_key', []);

        // Determine parent prefix (or nature base if parent missing)
        $parent = $group->parent;

        $natureDigit = $this->naturePrefixDigit((string) ($group->nature ?? ''));
        $natureBase  = $natureDigit * 1000; // e.g. 1 => 1000

        $parentPrefix = $natureBase;

        if ($parent) {
            $parentKey = strtoupper(trim((string) ($parent->code ?? '')));

            // Prefer config mapping for parent
            if ($parentKey !== '' && isset($byKey[$parentKey]['prefix'])) {
                $parentPrefix = (int) $byKey[$parentKey]['prefix'];
            } else {
                // Else, prefer any existing saved sequence for parent
                $parentSeq = AccountCodeSequence::query()
                    ->where('company_id', $companyId)
                    ->where('series_key', $parentKey)
                    ->first();

                if ($parentSeq && ctype_digit((string) $parentSeq->prefix)) {
                    $parentPrefix = (int) $parentSeq->prefix;
                } else {
                    // Else, treat parent itself as nature base
                    $parentNatureDigit = $this->naturePrefixDigit((string) ($parent->nature ?? ''));
                    $parentPrefix = $parentNatureDigit * 1000;
                }
            }
        }

        $step = $this->hierarchyStep($parentPrefix);
        $limit = $this->hierarchyLimit($parentPrefix, $step);

        // Collect used prefixes for siblings (config + sequences)
        $used = [];

        if ($parent) {
            $siblings = AccountGroup::query()
                ->where('company_id', $companyId)
                ->where('parent_id', $parent->id)
                ->pluck('code')
                ->map(fn ($c) => strtoupper(trim((string) $c)))
                ->filter()
                ->values()
                ->all();

            foreach ($siblings as $sibKey) {
                if (isset($byKey[$sibKey]['prefix'])) {
                    $p = (int) $byKey[$sibKey]['prefix'];
                    if ($p > 0) $used[] = $p;
                } else {
                    $sibSeq = AccountCodeSequence::query()
                        ->where('company_id', $companyId)
                        ->where('series_key', $sibKey)
                        ->first();

                    if ($sibSeq && ctype_digit((string) $sibSeq->prefix)) {
                        $p = (int) $sibSeq->prefix;
                        if ($p > 0) $used[] = $p;
                    }
                }
            }
        } else {
            // No parent: used = all prefixes in the same nature block (1000-1999 etc.)
            $used = AccountCodeSequence::query()
                ->where('company_id', $companyId)
                ->whereRaw('prefix REGEXP "^[0-9]+$"')
                ->pluck('prefix')
                ->map(fn ($p) => (int) $p)
                ->filter()
                ->all();
        }

        $used = array_values(array_unique($used));
        $maxUsed = 0;
        foreach ($used as $p) {
            // Only consider prefixes within the parent's block
            if ($p > $parentPrefix && $p < $limit && $p > $maxUsed) {
                $maxUsed = $p;
            }
        }

        $candidate = $maxUsed > 0 ? ($maxUsed + $step) : ($parentPrefix + $step);

        // Ensure candidate is not already used globally (company-level uniqueness)
        while ($candidate < $limit) {
            $exists = AccountCodeSequence::query()
                ->where('company_id', $companyId)
                ->where('prefix', (string) $candidate)
                ->exists();

            if (! $exists) {
                return str_pad((string) $candidate, 4, '0', STR_PAD_LEFT);
            }

            $candidate += $step;
        }

        throw ValidationException::withMessages([
            'account_group_id' => 'Unable to auto-allocate account code series for this group. The parent range is exhausted.',
        ]);
    }

    protected function hierarchyStep(int $parentPrefix): int
    {
        // Nature base like 1000/2000/3000...
        if ($parentPrefix % 1000 === 0) {
            return 100;
        }
        // Hundred-level like 1100/1200...
        if ($parentPrefix % 100 === 0) {
            return 10;
        }
        // Ten-level like 1110/1120...
        if ($parentPrefix % 10 === 0) {
            return 1;
        }
        return 1;
    }

    protected function hierarchyLimit(int $parentPrefix, int $step): int
    {
        // block boundary
        if ($step === 100) {
            return $parentPrefix + 1000; // 1000..1999 block
        }
        if ($step === 10) {
            return $parentPrefix + 100;  // 1100..1199 block
        }
        return $parentPrefix + 10;       // 1110..1119 block
    }

    protected function naturePrefixDigit(string $nature): int
    {
        return match (strtolower(trim($nature))) {
            'asset'     => 1,
            'liability' => 2,
            'equity'    => 3,
            'income'    => 4,
            'expense'   => 5,
            default     => 9,
        };
    }
}

