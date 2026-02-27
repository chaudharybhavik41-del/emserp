<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesCreditNoteRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'sales-credit-note-register';
    }

    public function name(): string
    {
        return 'Sales Credit Note Register';
    }

    public function module(): string
    {
        return 'Sales';
    }

    public function description(): ?string
    {
        return 'Sales credit notes issued to clients.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'client_id' => ['nullable', 'integer', 'exists:parties,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);
        $clients = DB::table('parties')->where('is_client', 1)->orderBy('name')->limit(500)->get(['id', 'name']);

        $statusOptions = DB::table('sales_credit_notes')
            ->where('company_id', $this->companyId())
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
                'name' => 'client_id', 'label' => 'Client', 'type' => 'select', 'col' => 3,
                'options' => collect($clients)->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            ],
            [
                'name' => 'status', 'label' => 'Status', 'type' => 'select', 'col' => 2,
                'options' => collect($statusOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'col' => 4, 'placeholder' => 'CN No / Reference'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'CN No', 'value' => 'credit_note_number', 'w' => '14%'],
            ['label' => 'Date', 'value' => 'credit_note_date', 'w' => '10%'],
            ['label' => 'Client', 'value' => 'client_name', 'w' => '20%'],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '22%'],
            ['label' => 'Status', 'value' => fn ($r) => strtoupper((string) $r->status), 'w' => '10%'],
            ['label' => 'Amount', 'align' => 'right', 'w' => '12%', 'value' => fn ($r, $forExport) => $forExport ? (float) ($r->total_amount ?? 0) : number_format((float) ($r->total_amount ?? 0), 2)],
            ['label' => 'Reference', 'value' => 'reference', 'w' => '12%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'credit_note_date', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('sales_credit_notes as cn')
            ->leftJoin('parties as c', 'c.id', '=', 'cn.client_id')
            ->leftJoin('projects as p', 'p.id', '=', 'cn.project_id')
            ->where('cn.company_id', $this->companyId())
            ->select([
                'cn.id',
                'cn.credit_note_number',
                'cn.credit_note_date',
                'cn.status',
                'cn.total_amount',
                'cn.reference',
                'cn.remarks',
                'c.name as client_name',
                'p.code as project_code',
                'p.name as project_name',
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('cn.credit_note_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('cn.credit_note_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('cn.project_id', $filters['project_id']);
        }
        if (!empty($filters['client_id'])) {
            $q->where('cn.client_id', $filters['client_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('cn.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('cn.credit_note_number', 'like', "%{$term}%")
                    ->orWhere('cn.reference', 'like', "%{$term}%")
                    ->orWhere('cn.remarks', 'like', "%{$term}%")
                    ->orWhere('c.name', 'like', "%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as tot')
            ->first();

        return [
            ['label' => 'Credit Notes', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Total Amount', 'value' => number_format((float) ($row->tot ?? 0), 2)],
        ];
    }
}
