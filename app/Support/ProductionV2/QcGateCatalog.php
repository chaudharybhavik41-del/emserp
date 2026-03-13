<?php

namespace App\Support\ProductionV2;

class QcGateCatalog
{
    public const MODES = [
        'self_check' => 'Self Check',
        'internal_qc' => 'Internal QC Hold',
        'client_tpi' => 'Client / TPI Hold',
    ];

    public const TYPES = [
        'visual' => 'Visual',
        'dimensional' => 'Dimensional',
        'fitup' => 'Fit-up',
        'weld_visual' => 'Weld Visual',
        'dpt' => 'DPT',
        'mpt' => 'MPT',
        'ut' => 'UT',
        'rt' => 'RT',
        'surface_profile' => 'Surface Profile',
        'dft' => 'DFT',
        'final' => 'Final Clearance',
    ];

    public const RESULTS = [
        'passed' => 'Passed',
        'failed' => 'Failed',
        'hold' => 'Hold',
        'reoffer' => 'Re-offer',
    ];

    public function modeOptions(): array
    {
        return self::MODES;
    }

    public function typeOptions(): array
    {
        return self::TYPES;
    }

    public function resultOptions(): array
    {
        return self::RESULTS;
    }

    public function isPassed(?string $result): bool
    {
        return strtolower(trim((string) $result)) === 'passed';
    }
}
