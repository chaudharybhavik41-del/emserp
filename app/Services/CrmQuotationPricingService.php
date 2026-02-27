<?php

namespace App\Services;

class CrmQuotationPricingService
{
    public const BASIS_PER_UNIT = 'per_unit';
    public const BASIS_LUMPSUM  = 'lumpsum';
    public const BASIS_PERCENT  = 'percent';

    /**
     * Normalize and sanitize components coming from UI/JSON.
     *
     * Expected format:
     *  [
     *    ['name' => 'Fabrication labour', 'basis' => 'per_unit', 'rate' => 12.5, 'code' => 'FAB_LAB'],
     *    ...
     *  ]
     */
    public function normalizeComponents(array $components): array
    {
        $out = [];

        foreach ($components as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name  = trim((string) ($row['name'] ?? $row['component_name'] ?? ''));
            $code  = trim((string) ($row['code'] ?? $row['component_code'] ?? ''));
            $basis = trim((string) ($row['basis'] ?? self::BASIS_PER_UNIT));
            $rate  = (float) ($row['rate'] ?? 0);

            if ($name === '' && $code !== '') {
                $name = $code;
            }

            if ($name === '') {
                continue;
            }

            if (! in_array($basis, [self::BASIS_PER_UNIT, self::BASIS_LUMPSUM, self::BASIS_PERCENT], true)) {
                $basis = self::BASIS_PER_UNIT;
            }

            $out[] = [
                'code'  => $code !== '' ? $code : null,
                'name'  => $name,
                'basis' => $basis,
                'rate'  => max(0, $rate),
            ];
        }

        return array_values($out);
    }

    /**
     * Calculate pricing from breakup + profit.
     *
     * Returns:
     *  [
     *    'quantity' => float,
     *    'direct_cost_unit' => float,
     *    'profit_percent' => float,
     *    'profit_unit' => float,
     *    'sell_unit_price' => float,
     *    'line_total' => float,
     *    'components' => [
     *        ['name','basis','rate','unit_cost','total_cost'],
     *        ...
     *    ],
     *  ]
     */
    public function calculate(float $quantity, array $components, float $profitPercent = 0): array
    {
        $quantity = max(0, $quantity);
        $profitPercent = max(0, $profitPercent);

        $components = $this->normalizeComponents($components);

        $nonPercent = [];
        $percent    = [];

        foreach ($components as $row) {
            if (($row['basis'] ?? self::BASIS_PER_UNIT) === self::BASIS_PERCENT) {
                $percent[] = $row;
            } else {
                $nonPercent[] = $row;
            }
        }

        $rowsOut = [];

        // 1) Base direct cost (excluding percent rows)
        $baseUnit = 0.0;

        foreach ($nonPercent as $row) {
            $basis = $row['basis'];
            $rate  = (float) $row['rate'];

            $unitCost = 0.0;
            $totalCost = 0.0;

            if ($basis === self::BASIS_LUMPSUM) {
                // Convert lumpsum to unit cost. If quantity is 0 (rate-only), keep denominator as 1 to avoid /0.
                $den = $quantity > 0 ? $quantity : 1;
                $unitCost = $rate / $den;

                // In rate-only, there is no meaningful "total cost", so keep it 0.
                $totalCost = $quantity > 0 ? $rate : 0.0;
            } else {
                // per_unit
                $unitCost = $rate;
                $totalCost = $quantity > 0 ? ($rate * $quantity) : 0.0;
            }

            $baseUnit += $unitCost;

            $rowsOut[] = [
                'code'       => $row['code'] ?? null,
                'name'       => $row['name'],
                'basis'      => $basis,
                'rate'       => $rate,
                'unit_cost'  => $unitCost,
                'total_cost' => $totalCost,
            ];
        }

        // 2) Percent rows (% of base direct cost)
        $percentUnitTotal = 0.0;

        foreach ($percent as $row) {
            $pct = (float) $row['rate'];
            $unitCost = ($baseUnit * $pct) / 100.0;
            $percentUnitTotal += $unitCost;

            $rowsOut[] = [
                'code'       => $row['code'] ?? null,
                'name'       => $row['name'],
                'basis'      => self::BASIS_PERCENT,
                'rate'       => $pct,
                'unit_cost'  => $unitCost,
                'total_cost' => $quantity > 0 ? ($unitCost * $quantity) : 0.0,
            ];
        }

        $directUnit = $baseUnit + $percentUnitTotal;

        $profitUnit = ($directUnit * $profitPercent) / 100.0;
        $sellUnit   = $directUnit + $profitUnit;

        $lineTotal = $quantity > 0 ? ($sellUnit * $quantity) : 0.0;

        return [
            'quantity'          => $quantity,
            'direct_cost_unit'  => $directUnit,
            'profit_percent'    => $profitPercent,
            'profit_unit'       => $profitUnit,
            'sell_unit_price'   => $sellUnit,
            'line_total'        => $lineTotal,
            'components'        => $rowsOut,
        ];
    }
}
