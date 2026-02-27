<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreIssueRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'stores-issue-register';
    }

    public function name(): string
    {
        return 'Store Issue Register';
    }

    public function module(): string
    {
        return 'Stores';
    }

    public function description(): ?string
    {
        return 'Stock issues (outgoing) with totals by pcs/weight.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'store_location_id' => ['nullable', 'integer', 'exists:store_locations,id'],
            'issue_type' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);
        $locations = DB::table('store_locations')->orderBy('name')->limit(200)->get(['id', 'name']);

        $typeOptions = DB::table('store_issues')
            ->select('issue_type')
            ->distinct()
            ->orderBy('issue_type')
            ->pluck('issue_type')
            ->filter()
            ->values()
            ->all();

        $statusOptions = DB::table('store_issues')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();

        return [
            ['name' => 'from_date', 'label' => 'From Date', 'type' => 'date', 'col' => 2],
            ['name' => 'to_date', 'label' => 'To Date', 'type' => 'date', 'col' => 2],
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
                'name' => 'issue_type', 'label' => 'Issue Type', 'type' => 'select', 'col' => 2,
                'options' => collect($typeOptions)->map(fn ($t) => ['value' => $t, 'label' => strtoupper($t)])->all(),
            ],
            [
                'name' => 'status', 'label' => 'Status', 'type' => 'select', 'col' => 2,
                'options' => collect($statusOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'col' => 4, 'placeholder' => 'Issue No / Req No / Contractor / Remarks'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'Issue No', 'value' => 'issue_number', 'w' => '12%'],
            ['label' => 'Date', 'value' => 'issue_date', 'w' => '9%'],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '22%'],
            ['label' => 'Req No', 'value' => 'requisition_number', 'w' => '11%'],
            ['label' => 'Type', 'value' => fn ($r) => strtoupper((string) $r->issue_type), 'w' => '9%'],
            ['label' => 'Issued To', 'value' => 'issued_to_display', 'w' => '16%'],
            ['label' => 'Location', 'value' => 'location_name', 'w' => '10%'],
            ['label' => 'Status', 'value' => fn ($r) => strtoupper((string) $r->status), 'w' => '8%'],
            ['label' => 'Pcs', 'align' => 'right', 'value' => fn ($r) => (int) ($r->total_pcs ?? 0), 'w' => '6%'],
            [
                'label' => 'Wt (kg)', 'align' => 'right', 'w' => '7%',
                'value' => fn ($r, $forExport) => $forExport ? (float) ($r->total_wt ?? 0) : number_format((float) ($r->total_wt ?? 0), 3),
            ],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'issue_date', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('store_issues as si')
            ->leftJoin('store_requisitions as sr', 'sr.id', '=', 'si.store_requisition_id')
            ->leftJoin('projects as p', 'p.id', '=', 'si.project_id')
            ->leftJoin('store_locations as l', 'l.id', '=', 'si.store_location_id')
            ->leftJoin('users as u', 'u.id', '=', 'si.issued_to_user_id')
            ->leftJoin('parties as c', 'c.id', '=', 'si.contractor_party_id')
            ->select([
                'si.id',
                'si.issue_number',
                'si.issue_date',
                'si.issue_type',
                'si.status',
                'si.remarks',
                'sr.requisition_number',
                'p.code as project_code',
                'p.name as project_name',
                'l.name as location_name',
                DB::raw("TRIM(COALESCE(u.name, c.name, si.contractor_person_name, '')) as issued_to_display"),
                DB::raw('(select COALESCE(SUM(il.issued_qty_pcs),0) from store_issue_lines il where il.store_issue_id = si.id) as total_pcs'),
                DB::raw('(select COALESCE(SUM(il.issued_weight_kg),0) from store_issue_lines il where il.store_issue_id = si.id) as total_wt'),
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('si.issue_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('si.issue_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('si.project_id', $filters['project_id']);
        }
        if (!empty($filters['store_location_id'])) {
            $q->where('si.store_location_id', $filters['store_location_id']);
        }
        if (!empty($filters['issue_type'])) {
            $q->where('si.issue_type', $filters['issue_type']);
        }
        if (!empty($filters['status'])) {
            $q->where('si.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('si.issue_number', 'like', "%{$term}%")
                    ->orWhere('sr.requisition_number', 'like', "%{$term}%")
                    ->orWhere('c.name', 'like', "%{$term}%")
                    ->orWhere('si.contractor_person_name', 'like', "%{$term}%")
                    ->orWhere('si.remarks', 'like', "%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_pcs),0) as pcs, COALESCE(SUM(total_wt),0) as wt')
            ->first();

        return [
            ['label' => 'Issues', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Total Pcs', 'value' => (int) ($row->pcs ?? 0)],
            ['label' => 'Total Wt (kg)', 'value' => number_format((float) ($row->wt ?? 0), 3)],
        ];
    }
}
