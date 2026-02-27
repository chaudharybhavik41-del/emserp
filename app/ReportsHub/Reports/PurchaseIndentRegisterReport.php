<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseIndentRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'purchase-indent-register';
    }

    public function name(): string
    {
        return 'Purchase Indent Register';
    }

    public function module(): string
    {
        return 'Purchase';
    }

    public function description(): ?string
    {
        return 'Purchase indents with item counts and required-by dates.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'procurement_status' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);
        $depts = DB::table('departments')->orderBy('name')->limit(300)->get(['id', 'name']);
        $users = DB::table('users')->orderBy('name')->limit(400)->get(['id', 'name']);

        $statusOptions = DB::table('purchase_indents')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();

        $procOptions = DB::table('purchase_indents')
            ->select('procurement_status')
            ->distinct()
            ->orderBy('procurement_status')
            ->pluck('procurement_status')
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
                'name' => 'department_id', 'label' => 'Department', 'type' => 'select', 'col' => 3,
                'options' => collect($depts)->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all(),
            ],
            [
                'name' => 'created_by', 'label' => 'Requested By', 'type' => 'select', 'col' => 3,
                'options' => collect($users)->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
            ],
            [
                'name' => 'status', 'label' => 'Status', 'type' => 'select', 'col' => 2,
                'options' => collect($statusOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            [
                'name' => 'procurement_status', 'label' => 'Procurement', 'type' => 'select', 'col' => 3,
                'options' => collect($procOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'col' => 4, 'placeholder' => 'Indent No / Remarks'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'Indent No', 'value' => 'indent_code', 'w' => '14%'],
            ['label' => 'Created', 'value' => 'created_at', 'w' => '12%'],
            ['label' => 'Required By', 'value' => 'required_by_date', 'w' => '10%'],
            ['label' => 'Department', 'value' => 'department_name', 'w' => '12%'],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '24%'],
            ['label' => 'Requested By', 'value' => 'created_by_name', 'w' => '14%'],
            ['label' => 'Status', 'value' => fn ($r) => strtoupper((string) $r->status), 'w' => '8%'],
            ['label' => 'Proc.', 'value' => fn ($r) => strtoupper((string) $r->procurement_status), 'w' => '8%'],
            ['label' => 'Items', 'align' => 'right', 'value' => fn ($r) => (int) ($r->item_count ?? 0), 'w' => '6%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'created_at', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('purchase_indents as pi')
            ->leftJoin('projects as p', 'p.id', '=', 'pi.project_id')
            ->leftJoin('departments as d', 'd.id', '=', 'pi.department_id')
            ->leftJoin('users as u', 'u.id', '=', 'pi.created_by')
            ->select([
                'pi.id',
                'pi.code as indent_code',
                'pi.required_by_date',
                'pi.status',
                'pi.procurement_status',
                'pi.remarks',
                'pi.created_at',
                'p.code as project_code',
                'p.name as project_name',
                'd.name as department_name',
                'u.name as created_by_name',
                DB::raw('(select count(*) from purchase_indent_items i where i.purchase_indent_id = pi.id) as item_count'),
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('pi.created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('pi.created_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('pi.project_id', $filters['project_id']);
        }
        if (!empty($filters['department_id'])) {
            $q->where('pi.department_id', $filters['department_id']);
        }
        if (!empty($filters['created_by'])) {
            $q->where('pi.created_by', $filters['created_by']);
        }
        if (!empty($filters['status'])) {
            $q->where('pi.status', $filters['status']);
        }
        if (!empty($filters['procurement_status'])) {
            $q->where('pi.procurement_status', $filters['procurement_status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('pi.code', 'like', "%{$term}%")
                    ->orWhere('pi.remarks', 'like', "%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(item_count),0) as items')
            ->first();

        return [
            ['label' => 'Indents', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Items', 'value' => (int) ($row->items ?? 0)],
        ];
    }
}
