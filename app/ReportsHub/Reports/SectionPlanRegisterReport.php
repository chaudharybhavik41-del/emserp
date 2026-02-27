<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SectionPlanRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'production-section-plan-register';
    }

    public function name(): string
    {
        return 'Section Plan Register';
    }

    public function module(): string
    {
        return 'Production';
    }

    public function description(): ?string
    {
        return 'Section plans with quantity/weight totals.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'bom_id' => ['nullable', 'integer', 'exists:boms,id'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'grade' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);
        $boms = DB::table('boms')->orderBy('bom_number')->limit(500)->get(['id', 'bom_number']);
        $items = DB::table('items')->orderBy('name')->limit(800)->get(['id', 'code', 'name']);

        $gradeOptions = DB::table('section_plans')
            ->select('grade')
            ->distinct()
            ->orderBy('grade')
            ->pluck('grade')
            ->filter()
            ->values()
            ->all();

        return [
            ['name' => 'from_date', 'label' => 'Created From', 'type' => 'date', 'col' => 2],
            ['name' => 'to_date', 'label' => 'Created To', 'type' => 'date', 'col' => 2],
            [
                'name' => 'project_id', 'label' => 'Project', 'type' => 'select', 'col' => 3,
                'options' => collect($projects)->map(fn ($p) => [
                    'value' => $p->id,
                    'label' => trim(($p->code ? $p->code . ' - ' : '') . $p->name),
                ])->all(),
            ],
            [
                'name' => 'bom_id', 'label' => 'BOM', 'type' => 'select', 'col' => 3,
                'options' => collect($boms)->map(fn ($b) => ['value' => $b->id, 'label' => $b->bom_number])->all(),
            ],
            [
                'name' => 'item_id', 'label' => 'Item', 'type' => 'select', 'col' => 3,
                'options' => collect($items)->map(fn ($i) => ['value' => $i->id, 'label' => trim(($i->code ? $i->code . ' - ' : '') . $i->name)])->all(),
            ],
            [
                'name' => 'grade', 'label' => 'Grade', 'type' => 'select', 'col' => 2,
                'options' => collect($gradeOptions)->map(fn ($g) => ['value' => $g, 'label' => $g])->all(),
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'col' => 4, 'placeholder' => 'Name / Profile'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'ID', 'value' => 'id', 'w' => '6%'],
            ['label' => 'Name', 'value' => 'name', 'w' => '16%'],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '20%'],
            ['label' => 'BOM', 'value' => 'bom_number', 'w' => '10%'],
            ['label' => 'Item', 'value' => fn ($r) => trim(($r->item_code ? $r->item_code . ' - ' : '') . ($r->item_name ?? '')), 'w' => '18%'],
            ['label' => 'Profile', 'value' => 'section_profile', 'w' => '10%'],
            ['label' => 'Grade', 'value' => 'grade', 'w' => '8%'],
            ['label' => 'Qty', 'align' => 'right', 'value' => fn ($r) => (int) ($r->quantity ?? 0), 'w' => '6%'],
            [
                'label' => 'Wt (kg)', 'align' => 'right', 'w' => '6%',
                'value' => fn ($r, $forExport) => $forExport ? (float) ($r->total_weight ?? 0) : number_format((float) ($r->total_weight ?? 0), 3),
            ],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'id', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('section_plans as sp')
            ->leftJoin('projects as p', 'p.id', '=', 'sp.project_id')
            ->leftJoin('boms as b', 'b.id', '=', 'sp.bom_id')
            ->leftJoin('items as i', 'i.id', '=', 'sp.item_id')
            ->select([
                'sp.id',
                'sp.name',
                'sp.section_profile',
                'sp.grade',
                'sp.quantity',
                'sp.total_weight',
                'sp.created_at',
                'p.code as project_code',
                'p.name as project_name',
                'b.bom_number',
                'i.code as item_code',
                'i.name as item_name',
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('sp.created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('sp.created_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('sp.project_id', $filters['project_id']);
        }
        if (!empty($filters['bom_id'])) {
            $q->where('sp.bom_id', $filters['bom_id']);
        }
        if (!empty($filters['item_id'])) {
            $q->where('sp.item_id', $filters['item_id']);
        }
        if (!empty($filters['grade'])) {
            $q->where('sp.grade', $filters['grade']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('sp.name', 'like', "%{$term}%")
                    ->orWhere('sp.section_profile', 'like', "%{$term}%")
                    ->orWhere('i.code', 'like', "%{$term}%")
                    ->orWhere('i.name', 'like', "%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(quantity),0) as qty, COALESCE(SUM(total_weight),0) as wt')
            ->first();

        return [
            ['label' => 'Plans', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Total Qty', 'value' => (int) ($row->qty ?? 0)],
            ['label' => 'Total Wt (kg)', 'value' => number_format((float) ($row->wt ?? 0), 3)],
        ];
    }
}
