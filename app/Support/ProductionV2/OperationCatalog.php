<?php

namespace App\Support\ProductionV2;

use App\Models\ProductionV2\ProductionOperationMaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OperationCatalog
{
    private const DEFAULT_OPERATIONS = [
        [
            'code' => 'cutting',
            'name' => 'Cutting',
            'applies_to' => 'part',
            'entry_mode' => 'specialized',
            'entry_route' => 'projects.production-v2.cut-batches.create',
            'requires_machine' => true,
            'requires_qc' => false,
            'is_system' => true,
            'sort_order' => 10,
        ],
        [
            'code' => 'drilling',
            'name' => 'Drilling',
            'applies_to' => 'part',
            'entry_mode' => 'generic',
            'entry_route' => 'projects.production-v2.operation-events.create',
            'requires_machine' => true,
            'requires_qc' => false,
            'is_system' => true,
            'sort_order' => 20,
        ],
        [
            'code' => 'beveling',
            'name' => 'Beveling',
            'applies_to' => 'part',
            'entry_mode' => 'generic',
            'entry_route' => 'projects.production-v2.operation-events.create',
            'requires_machine' => true,
            'requires_qc' => false,
            'is_system' => true,
            'sort_order' => 30,
        ],
        [
            'code' => 'fitup',
            'name' => 'Fit-up',
            'applies_to' => 'assembly',
            'entry_mode' => 'specialized',
            'entry_route' => 'projects.production-v2.fitups.create',
            'requires_machine' => false,
            'requires_qc' => false,
            'is_system' => true,
            'sort_order' => 40,
        ],
        [
            'code' => 'welding',
            'name' => 'Welding',
            'applies_to' => 'assembly',
            'entry_mode' => 'specialized',
            'entry_route' => 'projects.production-v2.welding-events.create',
            'requires_machine' => true,
            'requires_qc' => false,
            'is_system' => true,
            'sort_order' => 50,
        ],
        [
            'code' => 'inspection',
            'name' => 'Inspection',
            'applies_to' => 'assembly',
            'entry_mode' => 'specialized',
            'entry_route' => 'projects.production-v2.inspection-events.create',
            'requires_machine' => false,
            'requires_qc' => true,
            'is_system' => true,
            'sort_order' => 60,
        ],
        [
            'code' => 'trial_assembly',
            'name' => 'Trial Assembly',
            'applies_to' => 'assembly',
            'entry_mode' => 'specialized',
            'entry_route' => 'projects.production-v2.trial-assemblies.create',
            'requires_machine' => false,
            'requires_qc' => true,
            'is_system' => true,
            'sort_order' => 70,
        ],
        [
            'code' => 'blasting',
            'name' => 'Blasting',
            'applies_to' => 'assembly',
            'entry_mode' => 'generic',
            'entry_route' => 'projects.production-v2.operation-events.create',
            'requires_machine' => true,
            'requires_qc' => false,
            'is_system' => true,
            'sort_order' => 80,
        ],
        [
            'code' => 'painting',
            'name' => 'Painting',
            'applies_to' => 'assembly',
            'entry_mode' => 'generic',
            'entry_route' => 'projects.production-v2.operation-events.create',
            'requires_machine' => false,
            'requires_qc' => false,
            'is_system' => true,
            'sort_order' => 90,
        ],
    ];

    public function ensureDefaults(): Collection
    {
        return collect(self::DEFAULT_OPERATIONS)->map(function (array $definition) {
            return ProductionOperationMaster::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition + ['remarks' => null, 'is_active' => true]
            );
        });
    }

    public function activeOptions(string $appliesTo): Collection
    {
        $this->ensureDefaults();

        return ProductionOperationMaster::query()
            ->where('applies_to', $appliesTo)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findByCode(string $code): ?ProductionOperationMaster
    {
        $this->ensureDefaults();

        return ProductionOperationMaster::query()
            ->where('code', Str::of($code)->lower()->value())
            ->first();
    }
}
