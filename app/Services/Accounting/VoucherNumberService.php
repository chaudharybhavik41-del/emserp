<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherSeries;
use App\Models\Accounting\VoucherSeriesCounter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Centralised voucher number generator.
 *
 * Goals:
 * - One place to generate voucher numbers for ALL accounting modules.
 * - Safe under concurrency (uses DB row locks).
 * - Series prefixes are unique within a company (enforced at DB level).
 * - Supports both formats:
 *     - PREFIX-000001 (no FY)
 *     - PREFIX/2025-26/0001 (FY)
 */
class VoucherNumberService
{
    /**
     * Generate and reserve the next voucher number for a series.
     */
    public function next(string $seriesKey, int $companyId, Carbon|string $date): string
    {
        $date = $this->asCarbon($date);
        $series = $this->getOrCreateSeries($seriesKey, $companyId);

        if (! $series->is_active) {
            throw new RuntimeException('Voucher series is disabled: ' . $series->key);
        }

        $fyCode = $series->use_financial_year ? $this->financialYearCode($date) : 'NA';

        return DB::transaction(function () use ($companyId, $series, $fyCode) {
            // Lock series row first so that counter creation for a new FY is safe.
            $series = VoucherSeries::whereKey($series->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Lock counter row to make sequence generation safe in concurrent requests
            $counter = VoucherSeriesCounter::where('voucher_series_id', $series->id)
                ->where('fy_code', $fyCode)
                ->lockForUpdate()
                ->first();

            $maxUsed = $this->maxUsedSequence($companyId, $series, $fyCode);

            if (! $counter) {
                $counter = VoucherSeriesCounter::create([
                    'voucher_series_id' => $series->id,
                    'fy_code'           => $fyCode,
                    'next_number'       => max(1, $maxUsed + 1),
                ]);
            } elseif ((int) $counter->next_number <= (int) $maxUsed) {
                // If counters got out of sync (manual edits / old data), bump forward.
                $counter->next_number = $maxUsed + 1;
                $counter->save();
            }

            $seq = (int) $counter->next_number;
            $voucherNo = $this->formatVoucherNo($series, $fyCode, $seq);

            // Enforce global uniqueness across voucher types (company-wide)
            // even though DB unique index is (company_id, voucher_no, voucher_type).
            while (
                Voucher::where('company_id', $companyId)
                    ->where('voucher_no', $voucherNo)
                    ->exists()
            ) {
                $seq++;
                $voucherNo = $this->formatVoucherNo($series, $fyCode, $seq);
            }

            $counter->next_number = $seq + 1;
            $counter->save();

            return $voucherNo;
        });
    }

    /**
     * Read-only: show what the next voucher number would look like.
     * This does NOT reserve a number.
     */
    public function preview(string $seriesKey, int $companyId, Carbon|string $date): string
    {
        $date = $this->asCarbon($date);
        $series = $this->getOrCreateSeries($seriesKey, $companyId);
        $fyCode = $series->use_financial_year ? $this->financialYearCode($date) : 'NA';

        $counter = VoucherSeriesCounter::where('voucher_series_id', $series->id)
            ->where('fy_code', $fyCode)
            ->first();

        $maxUsed = $this->maxUsedSequence($companyId, $series, $fyCode);
        $next = $counter ? (int) $counter->next_number : ($maxUsed + 1);
        $next = max($next, $maxUsed + 1, 1);

        return $this->formatVoucherNo($series, $fyCode, $next);
    }

    public function getOrCreateSeries(string $seriesKey, int $companyId): VoucherSeries
    {
        $seriesKey = trim($seriesKey);
        if ($seriesKey === '') {
            throw new RuntimeException('Voucher series key is required.');
        }

        $series = VoucherSeries::where('company_id', $companyId)
            ->where('key', $seriesKey)
            ->first();

        if ($series) {
            return $series;
        }

        // Auto-create using config defaults (keeps system working even if admin hasn't opened settings yet)
        $prefix = (string) (Config::get('accounting.voucher_series.' . $seriesKey) ?: strtoupper(substr($seriesKey, 0, 10)));
        $prefix = trim($prefix);

        // Default format rules
        $noFyKeys = ['purchase', 'store_issue'];
        $noFy = in_array($seriesKey, $noFyKeys, true);

        // Ensure prefix is unique within company
        $exists = VoucherSeries::where('company_id', $companyId)
            ->where('prefix', $prefix)
            ->exists();

        if ($exists) {
            throw new RuntimeException(
                'Voucher series prefix already exists for this company. ' .
                'Please change prefix in Accounting → Voucher Series Settings. Prefix: ' . $prefix
            );
        }

        return VoucherSeries::create([
            'company_id'          => $companyId,
            'key'                 => $seriesKey,
            'name'                => ucwords(str_replace('_', ' ', $seriesKey)),
            'prefix'              => $prefix,
            'use_financial_year'  => ! $noFy,
            'separator'           => $noFy ? '-' : '/',
            'pad_length'          => $noFy ? 6 : 4,
            'is_active'           => true,
        ]);
    }

    /**
     * Compute the max-used numeric sequence from existing voucher_nos.
     */
    public function maxUsedSequence(int $companyId, VoucherSeries $series, string $fyCode): int
    {
        $likePrefix = $series->prefix . $series->separator;
        if ($series->use_financial_year) {
            $likePrefix .= $fyCode . $series->separator;
        }

        // Since sequence is zero-padded, ordering by voucher_no desc is safe.
        $last = Voucher::where('company_id', $companyId)
            ->where('voucher_no', 'like', $likePrefix . '%')
            ->orderByDesc('voucher_no')
            ->first();

        if (! $last) {
            return 0;
        }

        $seq = $this->parseTrailingNumber($last->voucher_no);
        return $seq ?? 0;
    }

    protected function formatVoucherNo(VoucherSeries $series, string $fyCode, int $seq): string
    {
        $parts = [$series->prefix];
        if ($series->use_financial_year) {
            $parts[] = $fyCode;
        }

        $parts[] = str_pad((string) $seq, (int) $series->pad_length, '0', STR_PAD_LEFT);

        return implode((string) $series->separator, $parts);
    }

    protected function parseTrailingNumber(string $voucherNo): ?int
    {
        if (preg_match('/(\d+)\s*$/', $voucherNo, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    protected function financialYearCode(Carbon $date): string
    {
        $startMonth = (int) Config::get('accounting.financial_year.start_month', 4);
        $fyStartYear = $date->month >= $startMonth ? $date->year : $date->year - 1;
        $fyEndYear = $fyStartYear + 1;

        return sprintf('%d-%02d', $fyStartYear, $fyEndYear % 100);
    }

    protected function asCarbon(Carbon|string $date): Carbon
    {
        return $date instanceof Carbon ? $date : Carbon::parse($date);
    }
}
