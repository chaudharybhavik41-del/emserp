<?php

namespace App\Services\Engineering;

use App\Enums\BomItemMaterialCategory;
use App\Models\Item;
use App\Services\SettingsService;

class BomKpiCalculator
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * Calculate KPI metrics for a BOM line.
     *
     * - Area: m²
     * - Cutting length: m
     * - Welding length: m (manual by default)
     *
     * $overrides may contain unit_* values; if provided they are used (and totals derived).
     */
    public function calculate(?string $materialCategory, array $dimensions, float $qty, ?Item $linkedItem, array $overrides = []): array
    {
        $qty = max(0.0, (float) $qty);

        $cat = (string) ($materialCategory ?? '');

        $unitArea = $this->numOrNull($overrides['unit_area_m2'] ?? null);
        $unitCut  = $this->numOrNull($overrides['unit_cut_length_m'] ?? null);
        $unitWeld = $this->numOrNull($overrides['unit_weld_length_m'] ?? null);

        // Auto-calculation for known categories (only when not overridden)
        if ($unitArea === null) {
            if ($cat === BomItemMaterialCategory::STEEL_PLATE->value) {
                $unitArea = $this->calcPlateAreaM2(
                    $this->dim($dimensions, 'width_mm'),
                    $this->dim($dimensions, 'length_mm'),
                    $this->dim($dimensions, 'thickness_mm')
                );
            } elseif ($cat === BomItemMaterialCategory::STEEL_SECTION->value) {
                $unitArea = $this->calcSectionAreaM2($this->dim($dimensions, 'length_mm'), $linkedItem);
            }
        }

        if ($unitCut === null) {
            if ($cat === BomItemMaterialCategory::STEEL_PLATE->value) {
                $unitCut = $this->calcPlateCutLengthM(
                    $this->dim($dimensions, 'width_mm'),
                    $this->dim($dimensions, 'length_mm')
                );
            }
        }

        // Welding is manual by default (no auto formula).

        return [
            'unit_area_m2'        => $unitArea,
            'total_area_m2'       => $unitArea !== null ? $unitArea * $qty : null,
            'unit_cut_length_m'   => $unitCut,
            'total_cut_length_m'  => $unitCut !== null ? $unitCut * $qty : null,
            'unit_weld_length_m'  => $unitWeld,
            'total_weld_length_m' => $unitWeld !== null ? $unitWeld * $qty : null,
        ];
    }

    private function dim(array $dimensions, string $key): ?float
    {
        if (! array_key_exists($key, $dimensions)) {
            return null;
        }

        $v = $dimensions[$key];
        if ($v === null || $v === '') {
            return null;
        }

        if (! is_numeric($v)) {
            return null;
        }

        $n = (float) $v;
        if ($n < 0) {
            return null;
        }

        return $n;
    }

    private function numOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        if (! is_numeric($v)) {
            return null;
        }

        $n = (float) $v;
        if ($n < 0) {
            return null;
        }

        return $n;
    }

    /**
     * Plate surface area calculation.
     *
     * Supported formula modes (settings group: engineering, key: plate_area_mode):
     * - one_side
     * - two_side
     * - two_side_plus_edges
     * - factor (multiplies one_side by plate_area_factor)
     */
    private function calcPlateAreaM2(?float $widthMm, ?float $lengthMm, ?float $thicknessMm): ?float
    {
        if (! $widthMm || ! $lengthMm) {
            return null;
        }

        $w = $widthMm / 1000.0;
        $l = $lengthMm / 1000.0;
        if ($w <= 0 || $l <= 0) {
            return null;
        }

        $t = ($thicknessMm ? ($thicknessMm / 1000.0) : 0.0);
        $face = $w * $l; // one side
        $perimeter = 2.0 * ($w + $l);

        $mode = (string) $this->settings->get('engineering', 'plate_area_mode', 'two_side');
        $mode = strtolower(trim($mode));

        return match ($mode) {
            'one_side' => $face,
            'two_side' => 2.0 * $face,
            'two_side_plus_edges' => (2.0 * $face) + ($perimeter * max(0.0, $t)),
            'factor' => (float) $this->settings->get('engineering', 'plate_area_factor', 2.0) * $face,
            default => 2.0 * $face,
        };
    }

    /**
     * Plate cutting length calculation (perimeter * factor).
     * Settings group: engineering, key: plate_cut_factor
     */
    private function calcPlateCutLengthM(?float $widthMm, ?float $lengthMm): ?float
    {
        if (! $widthMm || ! $lengthMm) {
            return null;
        }

        $w = $widthMm / 1000.0;
        $l = $lengthMm / 1000.0;
        if ($w <= 0 || $l <= 0) {
            return null;
        }

        $perimeter = 2.0 * ($w + $l);

        $factor = (float) $this->settings->get('engineering', 'plate_cut_factor', 1.0);
        if ($factor <= 0) {
            $factor = 1.0;
        }

        return $perimeter * $factor;
    }

    /**
     * Section surface area calculation (surface_area_per_meter * length_m).
     */
    private function calcSectionAreaM2(?float $lengthMm, ?Item $linkedItem): ?float
    {
        if (! $linkedItem) {
            return null;
        }

        $sapm = $linkedItem->surface_area_per_meter ?? null;
        if ($sapm === null || $sapm === '' || ! is_numeric($sapm)) {
            return null;
        }

        if (! $lengthMm || $lengthMm <= 0) {
            return null;
        }

        $lenM = $lengthMm / 1000.0;

        return (float) $sapm * $lenM;
    }
}
