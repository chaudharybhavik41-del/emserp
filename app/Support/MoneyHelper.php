<?php

namespace App\Support;

/**
 * Money helper for consistent rupee/paise conversions.
 *
 * Why this exists:
 * - DB uses DECIMAL(?,2) for amounts.
 * - PHP floats can introduce tiny rounding errors.
 * - We convert to integer paise for math, and convert back for display/storage.
 */
class MoneyHelper
{
    /**
     * Convert a rupee amount into integer paise (minor units).
     *
     * @param mixed $amount
     */
    public static function toPaise($amount): int
    {
        if ($amount === null) {
            return 0;
        }

        // Common safe cases
        if (is_int($amount)) {
            // Treat int input as rupees.
            return $amount * 100;
        }

        if (is_bool($amount)) {
            return $amount ? 100 : 0;
        }

        $str = trim((string) $amount);

        if ($str === '') {
            return 0;
        }

        // Support accounting-style negative numbers: (123.45)
        $negative = false;
        if (str_starts_with($str, '(') && str_ends_with($str, ')')) {
            $negative = true;
            $str = trim(substr($str, 1, -1));
        }

        // Strip common currency symbols/text and thousand separators.
        $str = str_replace(['₹', 'INR', 'Rs.', 'Rs', ',', ' '], '', $str);

        if ($str === '' || $str === '.' || $str === '-') {
            return 0;
        }

        if (str_starts_with($str, '+')) {
            $str = substr($str, 1);
        }

        if (str_starts_with($str, '-')) {
            $negative = ! $negative;
            $str = substr($str, 1);
        }

        // Fast-path parse for normal decimal strings.
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $str, $m) === 1) {
            $intPart = ltrim($m[1], '0');
            $intPart = $intPart === '' ? '0' : $intPart;

            $frac = $m[2] ?? '';
            $frac = preg_replace('/\D/', '', $frac) ?? '';

            // Round to 2 decimals HALF UP using the 3rd digit.
            // pad right so we always have at least 3 digits.
            $frac = $frac . '000';
            $d1 = (int) $frac[0];
            $d2 = (int) $frac[1];
            $d3 = (int) $frac[2];

            $cents = ($d1 * 10) + $d2;
            if ($d3 >= 5) {
                $cents += 1;
            }

            // Handle carry from 99 -> 100.
            $rupees = (int) $intPart;
            if ($cents >= 100) {
                $rupees += 1;
                $cents -= 100;
            }

            $paise = ($rupees * 100) + $cents;
            return $negative ? -$paise : $paise;
        }

        // Fallback: let PHP handle unusual numeric formats (e.g. exponent).
        $f = (float) $amount;
        $paise = (int) round($f * 100, 0, PHP_ROUND_HALF_UP);
        return $negative ? -abs($paise) : $paise;
    }

    /**
     * Convert paise (minor units) into a plain 2-decimal rupee string.
     *
     * IMPORTANT: This returns a string WITHOUT thousand separators.
     * That makes it safe for DB decimal fields and CSV exports.
     *
     * @param mixed $paise
     */
    public static function fromPaise($paise): string
    {
        $p = self::normalizePaise($paise);
        $neg = $p < 0;
        $p = abs($p);

        $rupees = intdiv($p, 100);
        $cents  = $p % 100;

        $out = $rupees . '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
        return $neg ? '-' . $out : $out;
    }

    /**
     * Round any rupee amount to 2 decimals and return a plain 2-decimal string.
     *
     * @param mixed $amount
     */
    public static function round2($amount): string
    {
        return self::fromPaise(self::toPaise($amount));
    }

    /**
     * Split an integer paise amount into two integer parts that sum to the original.
     *
     * Example:
     *  - 3   -> [1, 2]
     *  - 4   -> [2, 2]
     *  - -3  -> [-1, -2]
     *
     * This is used for intra-state GST splits (CGST + SGST) where the tax amount
     * may be an odd number of paise after rounding.
     */
    public static function splitTwo($paise): array
    {
        $p = self::normalizePaise($paise);

        // intdiv() rounds toward zero; the remainder is carried into the second part.
        $a = intdiv($p, 2);
        $b = $p - $a;

        return [$a, $b];
    }

    /**
     * @param mixed $paise
     */
    protected static function normalizePaise($paise): int
    {
        if ($paise === null || $paise === '') {
            return 0;
        }

        if (is_int($paise)) {
            return $paise;
        }

        if (is_bool($paise)) {
            return $paise ? 1 : 0;
        }

        // If someone passes a decimal string like "123.45" by mistake,
        // treat it as rupees and convert.
        if (is_string($paise) && str_contains($paise, '.')) {
            return self::toPaise($paise);
        }

        if (is_numeric($paise)) {
            return (int) $paise;
        }

        // Final fallback
        return 0;
    }
}
