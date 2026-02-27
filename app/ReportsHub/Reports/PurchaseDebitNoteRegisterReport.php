<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseDebitNoteRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'purchase-debit-note-register';
    }

    public function name(): string
    {
        return 'Purchase Debit Note Register';
    }

    public function module(): string
    {
        return 'Purchase';
    }

    public function description(): ?string
    {
        return 'Debit notes raised on purchase bills with supplier/project and totals.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:parties,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);
        $suppliers = DB::table('parties')->where('is_supplier', 1)->orderBy('name')->limit(500)->get(['id', 'name']);

        $statusOptions = DB::table('purchase_debit_notes')
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
                'name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'col' => 3,
                'options' => collect($suppliers)->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            ],
            [
                'name' => 'status', 'label' => 'Status', 'type' => 'select', 'col' => 2,
                'options' => collect($statusOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'col' => 4, 'placeholder' => 'DN No / Bill No / Ref / Remarks'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'DN No', 'value' => 'note_number', 'w' => '12%'],
            ['label' => 'Date', 'value' => 'note_date', 'w' => '9%'],
            ['label' => 'Supplier', 'value' => 'supplier_name', 'w' => '16%'],
            ['label' => 'Bill No', 'value' => 'bill_number', 'w' => '12%'],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '22%'],
            ['label' => 'Status', 'value' => fn ($r) => strtoupper((string) $r->status), 'w' => '8%'],
            [
                'label' => 'Amount', 'align' => 'right', 'w' => '10%',
                'value' => fn ($r, $forExport) => $forExport ? (float) ($r->total_amount ?? 0) : number_format((float) ($r->total_amount ?? 0), 2),
            ],
            ['label' => 'Reference', 'value' => 'reference', 'w' => '11%'],
            ['label' => 'Remarks', 'value' => 'remarks', 'w' => '20%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'note_date', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('purchase_debit_notes as dn')
            ->leftJoin('parties as s', 's.id', '=', 'dn.supplier_id')
            ->leftJoin('purchase_bills as b', 'b.id', '=', 'dn.purchase_bill_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'b.purchase_order_id')
            ->leftJoin('projects as p', function ($join) {
                // Bills may not store project_id; derive from PO when present.
                $join->on('p.id', '=', DB::raw('COALESCE(b.project_id, po.project_id)'));
            })
            ->where('dn.company_id', $this->companyId())
            ->select([
                'dn.id',
                'dn.note_number',
                'dn.note_date',
                'dn.status',
                'dn.total_amount',
                'dn.reference',
                'dn.remarks',
                's.name as supplier_name',
                'b.bill_number',
                'p.code as project_code',
                'p.name as project_name',
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('dn.note_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('dn.note_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->whereRaw('COALESCE(b.project_id, po.project_id) = ?', [$filters['project_id']]);
        }
        if (!empty($filters['supplier_id'])) {
            $q->where('dn.supplier_id', $filters['supplier_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('dn.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('dn.note_number', 'like', "%{$term}%")
                    ->orWhere('b.bill_number', 'like', "%{$term}%")
                    ->orWhere('dn.reference', 'like', "%{$term}%")
                    ->orWhere('dn.remarks', 'like', "%{$term}%");
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
            ['label' => 'Debit Notes', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Total Amount', 'value' => number_format((float) ($row->tot ?? 0), 2)],
        ];
    }
}
