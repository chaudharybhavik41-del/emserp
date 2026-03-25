<?php

namespace App\Support\ProductionV2;

use App\Models\ProductionV2\ProductionBillSourceLink;
use App\Models\ProductionV2\ProductionBillingRate;
use App\Models\ProductionV2\ProductionCutBatch;
use App\Models\ProductionV2\ProductionFitup;
use App\Models\ProductionV2\ProductionOperationEvent;
use App\Models\ProductionV2\ProductionWeldingEvent;
use Illuminate\Support\Collection;

class BillingEngine
{
    public function collectRows(int $projectId, int $contractorId, string $from, string $to, Collection $rateCards): array
    {
        $rows = collect();
        $missingRates = collect();

        $sourceRates = $rateCards
            ->where('is_active', true)
            ->groupBy(fn (ProductionBillingRate $rate) => $rate->source_type . ':' . ($rate->operation_master_id ?: 0));

        $cutBatches = ProductionCutBatch::query()
            ->where('project_id', $projectId)
            ->where('contractor_party_id', $contractorId)
            ->where('status', 'approved')
            ->whereBetween('cut_date', [$from, $to])
            ->with('wipItems')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('production_v2_bill_source_links as links')
                    ->whereColumn('links.source_id', 'production_v2_cut_batches.id')
                    ->where('links.source_type', 'cut_batch');
            })
            ->get();

        foreach ($cutBatches as $batch) {
            $rateCard = $sourceRates->get('cut_batch:0')?->first();
            if (! $rateCard) {
                $missingRates->push('Cut Batch CB-' . $batch->id);
                continue;
            }

            $qty = match ($rateCard->qty_basis) {
                'output_weight_kg' => (float) $batch->wipItems->sum(fn ($row) => (float) ($row->weight_kg ?? 0)),
                'event_count' => 1.0,
                default => (float) $batch->wipItems->sum(fn ($row) => (float) ($row->qty ?? 0)),
            };

            if ($qty <= 0) {
                $missingRates->push('Cut Batch CB-' . $batch->id . ' qty unresolved');
                continue;
            }

            $rows->push([
                'rate_card' => $rateCard,
                'source_type' => 'cut_batch',
                'source_id' => $batch->id,
                'description' => $rateCard->description ?: 'Cut Batch',
                'qty' => $qty,
                'qty_uom_id' => $rateCard->rate_uom_id,
            ]);
        }

        $fitups = ProductionFitup::query()
            ->where('project_id', $projectId)
            ->where('contractor_party_id', $contractorId)
            ->where('status', 'approved')
            ->whereBetween('fitup_date', [$from, $to])
            ->with('assembly')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('production_v2_bill_source_links as links')
                    ->whereColumn('links.source_id', 'production_v2_fitups.id')
                    ->where('links.source_type', 'fitup');
            })
            ->get();

        foreach ($fitups as $fitup) {
            $rateCard = $sourceRates->get('fitup:0')?->first();
            if (! $rateCard) {
                $missingRates->push('Fit-up FU-' . $fitup->id);
                continue;
            }

            $qty = match ($rateCard->qty_basis) {
                'assembly_weight_kg' => (float) ($fitup->assembly?->planned_weight_kg ?? 0),
                'event_count' => 1.0,
                default => (float) ($fitup->assembly?->planned_qty ?? 0),
            };

            if ($qty <= 0) {
                $missingRates->push('Fit-up FU-' . $fitup->id . ' qty unresolved');
                continue;
            }

            $rows->push([
                'rate_card' => $rateCard,
                'source_type' => 'fitup',
                'source_id' => $fitup->id,
                'description' => $rateCard->description ?: 'Fit-up',
                'qty' => $qty,
                'qty_uom_id' => $rateCard->rate_uom_id,
            ]);
        }

        $weldingEvents = ProductionWeldingEvent::query()
            ->where('project_id', $projectId)
            ->where('contractor_party_id', $contractorId)
            ->where('status', 'approved')
            ->whereBetween('weld_date', [$from, $to])
            ->with('assembly')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('production_v2_bill_source_links as links')
                    ->whereColumn('links.source_id', 'production_v2_welding_events.id')
                    ->where('links.source_type', 'welding');
            })
            ->get();

        foreach ($weldingEvents as $event) {
            $rateCard = $sourceRates->get('welding:0')?->first();
            if (! $rateCard) {
                $missingRates->push('Welding WE-' . $event->id);
                continue;
            }

            $qty = match ($rateCard->qty_basis) {
                'assembly_weight_kg' => (float) ($event->assembly?->planned_weight_kg ?? 0),
                'event_count' => 1.0,
                default => (float) ($event->assembly?->planned_qty ?? 0),
            };

            if ($qty <= 0) {
                $missingRates->push('Welding WE-' . $event->id . ' qty unresolved');
                continue;
            }

            $rows->push([
                'rate_card' => $rateCard,
                'source_type' => 'welding',
                'source_id' => $event->id,
                'description' => $rateCard->description ?: 'Welding',
                'qty' => $qty,
                'qty_uom_id' => $rateCard->rate_uom_id,
            ]);
        }

        $operationEvents = ProductionOperationEvent::query()
            ->where('project_id', $projectId)
            ->where('contractor_party_id', $contractorId)
            ->where('status', 'approved')
            ->whereBetween('operation_date', [$from, $to])
            ->with(['operationMaster', 'uom'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('production_v2_bill_source_links as links')
                    ->whereColumn('links.source_id', 'production_v2_operation_events.id')
                    ->where('links.source_type', 'operation');
            })
            ->get();

        foreach ($operationEvents as $event) {
            $rateCard = $sourceRates->get('operation:' . (int) $event->operation_master_id)?->first();
            if (! $rateCard) {
                $missingRates->push('Operation OE-' . $event->id . ' (' . ($event->operationMaster?->name ?: 'Unknown') . ')');
                continue;
            }

            $qty = $rateCard->qty_basis === 'event_count'
                ? 1.0
                : (float) ($event->qty ?? 0);

            if ($qty <= 0) {
                $missingRates->push('Operation OE-' . $event->id . ' qty unresolved');
                continue;
            }

            $rows->push([
                'rate_card' => $rateCard,
                'source_type' => 'operation',
                'source_id' => $event->id,
                'description' => $rateCard->description ?: ($event->operationMaster?->name ?: 'Operation'),
                'qty' => $qty,
                'qty_uom_id' => $rateCard->qty_basis === 'event_qty'
                    ? ($event->uom_id ?: $rateCard->rate_uom_id)
                    : $rateCard->rate_uom_id,
            ]);
        }

        return [
            'rows' => $rows,
            'missing_rates' => $missingRates->unique()->values(),
        ];
    }

    public function releaseMappings(int $billId): void
    {
        ProductionBillSourceLink::query()->where('production_v2_bill_id', $billId)->delete();
    }
}
