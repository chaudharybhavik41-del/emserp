<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreStockOnHandReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'stores-stock-on-hand';
    }

    public function name(): string
    {
        return 'Store Stock On Hand';
    }

    public function module(): string
    {
        return 'Stores';
    }

    public function description(): ?string
    {
        return 'Current available stock by item/location/project.';
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'client_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'store_location_id' => ['nullable', 'integer', 'exists:store_locations,id'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);
        $clients = DB::table('parties')->where('is_client', 1)->orderBy('name')->limit(600)->get(['id', 'name']);
        $locations = DB::table('store_locations')->orderBy('name')->limit(200)->get(['id', 'name']);
        $items = DB::table('items')->orderBy('name')->limit(800)->get(['id', 'code', 'name']);

        $statusOptions = DB::table('store_stock_items')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();

        return [
            [
                'name' => 'project_id', 'label' => 'Project', 'type' => 'select', 'col' => 3,
                'options' => collect($projects)->map(fn ($p) => [
                    'value' => $p->id,
                    'label' => trim(($p->code ? $p->code . ' - ' : '') . $p->name),
                ])->all(),
            ],
            [
                'name' => 'store_location_id', 'label' => 'Location', 'type' => 'select', 'col' => 3,
                'options' => collect($locations)->map(fn ($l) => ['value' => $l->id, 'label' => $l->name])->all(),
            ],
            [
                'name' => 'client_party_id', 'label' => 'Client', 'type' => 'select', 'col' => 3,
                'options' => collect($clients)->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            ],
            [
                'name' => 'item_id', 'label' => 'Item', 'type' => 'select', 'col' => 3,
                'options' => collect($items)->map(fn ($i) => ['value' => $i->id, 'label' => trim(($i->code ? $i->code . ' - ' : '') . $i->name)])->all(),
            ],
            [
                'name' => 'status', 'label' => 'Status', 'type' => 'select', 'col' => 2,
                'options' => collect($statusOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'col' => 4, 'placeholder' => 'Ref / Item / Brand / Heat / MTC'],
        ];
    }

    public function columns(): array
    {
        return [
            [
                'label' => 'Item',
                'value' => fn ($r) => trim(($r->item_code ? $r->item_code . ' - ' : '') . ($r->item_name ?? '')),
                'w' => '34%',
            ],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '18%'],
            ['label' => 'Location', 'value' => 'location_name', 'w' => '12%'],
            ['label' => 'Ref', 'value' => 'stock_ref', 'w' => '18%'],
            ['label' => 'Avail Pcs', 'align' => 'right', 'value' => fn ($r) => (int) ($r->qty_avl ?? 0), 'w' => '8%'],
            [
                'label' => 'Avail Wt (kg)', 'align' => 'right', 'w' => '10%',
                'value' => fn ($r, $forExport) => $forExport ? (float) ($r->wt_avl ?? 0) : number_format((float) ($r->wt_avl ?? 0), 3),
            ],
            ['label' => 'Status', 'value' => fn ($r) => strtoupper((string) $r->status), 'w' => '8%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'item_name', 'direction' => 'asc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('store_stock_items as s')
            ->leftJoin('items as i', 'i.id', '=', 's.item_id')
            ->leftJoin('projects as p', 'p.id', '=', 's.project_id')
            ->leftJoin('store_locations as l', 'l.id', '=', 's.store_location_id')
            ->select([
                's.id',
                's.status',
                's.brand',
                's.plate_number',
                's.heat_number',
                's.mtc_number',
                's.source_reference',
                's.qty_pcs_available as qty_avl',
                's.weight_kg_available as wt_avl',
                'i.code as item_code',
                'i.name as item_name',
                'p.code as project_code',
                'p.name as project_name',
                'l.name as location_name',
                DB::raw('COALESCE(s.source_reference, s.plate_number, s.heat_number, s.mtc_number) as stock_ref'),
            ]);

        if (!empty($filters['project_id'])) {
            $q->where('s.project_id', $filters['project_id']);
        }
        if (!empty($filters['client_party_id'])) {
            $q->where('s.client_party_id', $filters['client_party_id']);
        }
        if (!empty($filters['store_location_id'])) {
            $q->where('s.store_location_id', $filters['store_location_id']);
        }
        if (!empty($filters['item_id'])) {
            $q->where('s.item_id', $filters['item_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('s.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('s.source_reference', 'like', "%{$term}%")
                    ->orWhere('s.plate_number', 'like', "%{$term}%")
                    ->orWhere('s.heat_number', 'like', "%{$term}%")
                    ->orWhere('s.mtc_number', 'like', "%{$term}%")
                    ->orWhere('s.brand', 'like', "%{$term}%")
                    ->orWhere('i.code', 'like', "%{$term}%")
                    ->orWhere('i.name', 'like', "%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(qty_avl),0) as pcs, COALESCE(SUM(wt_avl),0) as wt')
            ->first();

        return [
            ['label' => 'Stock Items', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Avail Pcs', 'value' => (int) ($row->pcs ?? 0)],
            ['label' => 'Avail Wt (kg)', 'value' => number_format((float) ($row->wt ?? 0), 3)],
        ];
    }
}
