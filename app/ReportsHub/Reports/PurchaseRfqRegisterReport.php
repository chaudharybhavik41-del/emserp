<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRfqRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'purchase-rfq-register';
    }

    public function name(): string
    {
        return 'Purchase RFQ Register';
    }

    public function module(): string
    {
        return 'Purchase';
    }

    public function description(): ?string
    {
        return 'RFQs raised for indents with vendor/item counts.';
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
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);
        $depts = DB::table('departments')->orderBy('name')->limit(300)->get(['id', 'name']);
        $users = DB::table('users')->orderBy('name')->limit(400)->get(['id', 'name']);

        $statusOptions = DB::table('purchase_rfqs')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();

        return [
            ['name' => 'from_date', 'label' => 'RFQ From', 'type' => 'date', 'col' => 2],
            ['name' => 'to_date', 'label' => 'RFQ To', 'type' => 'date', 'col' => 2],
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
                'name' => 'created_by', 'label' => 'Created By', 'type' => 'select', 'col' => 3,
                'options' => collect($users)->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
            ],
            [
                'name' => 'status', 'label' => 'Status', 'type' => 'select', 'col' => 2,
                'options' => collect($statusOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'col' => 4, 'placeholder' => 'RFQ No / Indent / Remarks'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'RFQ No', 'value' => 'rfq_code', 'w' => '14%'],
            ['label' => 'RFQ Date', 'value' => 'rfq_date', 'w' => '9%'],
            ['label' => 'Due Date', 'value' => 'due_date', 'w' => '9%'],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '22%'],
            ['label' => 'Department', 'value' => 'department_name', 'w' => '12%'],
            ['label' => 'Indent', 'value' => 'indent_code', 'w' => '12%'],
            ['label' => 'Status', 'value' => fn ($r) => strtoupper((string) $r->status), 'w' => '8%'],
            ['label' => 'Vendors', 'align' => 'right', 'value' => fn ($r) => (int) ($r->vendor_count ?? 0), 'w' => '7%'],
            ['label' => 'Items', 'align' => 'right', 'value' => fn ($r) => (int) ($r->item_count ?? 0), 'w' => '7%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'rfq_date', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('purchase_rfqs as r')
            ->leftJoin('projects as p', 'p.id', '=', 'r.project_id')
            ->leftJoin('departments as d', 'd.id', '=', 'r.department_id')
            ->leftJoin('purchase_indents as pi', 'pi.id', '=', 'r.purchase_indent_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->select([
                'r.id',
                'r.code as rfq_code',
                'r.rfq_date',
                'r.due_date',
                'r.status',
                'r.remarks',
                'p.code as project_code',
                'p.name as project_name',
                'd.name as department_name',
                'pi.code as indent_code',
                'u.name as created_by_name',
                DB::raw('(select count(*) from purchase_rfq_vendors v where v.purchase_rfq_id = r.id) as vendor_count'),
                DB::raw('(select count(*) from purchase_rfq_items i where i.purchase_rfq_id = r.id) as item_count'),
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('r.rfq_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('r.rfq_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('r.project_id', $filters['project_id']);
        }
        if (!empty($filters['department_id'])) {
            $q->where('r.department_id', $filters['department_id']);
        }
        if (!empty($filters['created_by'])) {
            $q->where('r.created_by', $filters['created_by']);
        }
        if (!empty($filters['status'])) {
            $q->where('r.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('r.code', 'like', "%{$term}%")
                    ->orWhere('pi.code', 'like', "%{$term}%")
                    ->orWhere('r.remarks', 'like', "%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(vendor_count),0) as vendors, COALESCE(SUM(item_count),0) as items')
            ->first();

        return [
            ['label' => 'RFQs', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Total Vendors', 'value' => (int) ($row->vendors ?? 0)],
            ['label' => 'Total Items', 'value' => (int) ($row->items ?? 0)],
        ];
    }
}
