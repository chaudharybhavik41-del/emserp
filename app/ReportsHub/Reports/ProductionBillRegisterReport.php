<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionBillRegisterReport extends BaseTabularReport
{
    protected function hasPaymentStatusColumn(): bool
    {
        return Schema::hasColumn('production_bills', 'payment_status');
    }

    protected function hasBillForMonthColumn(): bool
    {
        return Schema::hasColumn('production_bills', 'bill_for_month');
    }

    protected function hasSubtotalAmountColumn(): bool
    {
        return Schema::hasColumn('production_bills', 'subtotal_amount');
    }

    protected function hasNetPayableColumn(): bool
    {
        return Schema::hasColumn('production_bills', 'net_payable');
    }

    public function key(): string
    {
        return 'production-bill-register';
    }

    public function name(): string
    {
        return 'Production Bill Register';
    }

    public function module(): string
    {
        return 'Production';
    }

    public function description(): ?string
    {
        return 'Production/contractor bills with GST and payable totals.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'contractor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'gst_type' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:30'],
            'payment_status' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);
        $contractors = DB::table('parties')->where('is_contractor', 1)->orderBy('name')->limit(500)->get(['id', 'name']);

        $gstTypes = DB::table('production_bills')->select('gst_type')->distinct()->orderBy('gst_type')->pluck('gst_type')->filter()->values()->all();
        $statusOptions = DB::table('production_bills')->select('status')->distinct()->orderBy('status')->pluck('status')->filter()->values()->all();
        $paymentStatusOptions = [];
        if ($this->hasPaymentStatusColumn()) {
            $paymentStatusOptions = DB::table('production_bills')
                ->select('payment_status')
                ->distinct()
                ->orderBy('payment_status')
                ->pluck('payment_status')
                ->filter()
                ->values()
                ->all();
        }

        $filters = [
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
                'name' => 'contractor_party_id', 'label' => 'Contractor', 'type' => 'select', 'col' => 3,
                'options' => collect($contractors)->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            ],
            [
                'name' => 'gst_type', 'label' => 'GST Type', 'type' => 'select', 'col' => 2,
                'options' => collect($gstTypes)->map(fn ($t) => ['value' => $t, 'label' => strtoupper($t)])->all(),
            ],
            [
                'name' => 'status', 'label' => 'Status', 'type' => 'select', 'col' => 2,
                'options' => collect($statusOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'col' => 4, 'placeholder' => 'Bill No'],
        ];

        if ($this->hasPaymentStatusColumn()) {
            $filters[] = [
                'name' => 'payment_status', 'label' => 'Payment', 'type' => 'select', 'col' => 2,
                'options' => collect($paymentStatusOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ];
        }

        return $filters;
    }

    public function columns(): array
    {
        return [
            ['label' => 'Bill No', 'value' => 'bill_number', 'w' => '12%'],
            ['label' => 'Bill Date', 'value' => 'bill_date', 'w' => '9%'],
            [
                'label' => 'Month',
                'value' => function ($r) {
                    $month = (string) ($r->bill_for_month ?? '');
                    if ($month !== '') {
                        return $month;
                    }

                    $billDate = (string) ($r->bill_date ?? '');
                    if ($billDate === '') {
                        return '-';
                    }

                    try {
                        return Carbon::parse($billDate)->format('M Y');
                    } catch (\Throwable $e) {
                        return $billDate;
                    }
                },
                'w' => '8%',
            ],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '20%'],
            ['label' => 'Contractor', 'value' => 'contractor_name', 'w' => '16%'],
            ['label' => 'GST', 'value' => fn ($r) => strtoupper((string) $r->gst_type), 'w' => '6%'],
            ['label' => 'Rate', 'align' => 'right', 'value' => fn ($r, $forExport) => $forExport ? (float) ($r->gst_rate ?? 0) : number_format((float) ($r->gst_rate ?? 0), 2), 'w' => '6%'],
            ['label' => 'Subtotal', 'align' => 'right', 'value' => fn ($r, $forExport) => $forExport ? (float) ($r->subtotal_amount ?? 0) : number_format((float) ($r->subtotal_amount ?? 0), 2), 'w' => '8%'],
            ['label' => 'Tax', 'align' => 'right', 'value' => fn ($r, $forExport) => $forExport ? (float) ($r->tax_total ?? 0) : number_format((float) ($r->tax_total ?? 0), 2), 'w' => '7%'],
            ['label' => 'Grand', 'align' => 'right', 'value' => fn ($r, $forExport) => $forExport ? (float) ($r->grand_total ?? 0) : number_format((float) ($r->grand_total ?? 0), 2), 'w' => '8%'],
            ['label' => 'Net', 'align' => 'right', 'value' => fn ($r, $forExport) => $forExport ? (float) ($r->net_payable ?? 0) : number_format((float) ($r->net_payable ?? 0), 2), 'w' => '8%'],
            ['label' => 'Status', 'value' => fn ($r) => strtoupper((string) $r->status), 'w' => '6%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'bill_date', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $hasPaymentStatus = $this->hasPaymentStatusColumn();
        $hasBillForMonth = $this->hasBillForMonthColumn();
        $hasSubtotalAmount = $this->hasSubtotalAmountColumn();
        $hasNetPayable = $this->hasNetPayableColumn();

        $selects = [
            'b.id',
            'b.bill_number',
            'b.bill_date',
            'b.gst_type',
            'b.gst_rate',
            'b.tax_total',
            'b.grand_total',
            'b.status',
            'p.code as project_code',
            'p.name as project_name',
            'c.name as contractor_name',
        ];

        $selects[] = $hasBillForMonth ? 'b.bill_for_month' : DB::raw('NULL as bill_for_month');
        $selects[] = $hasSubtotalAmount ? 'b.subtotal_amount' : DB::raw('b.subtotal as subtotal_amount');
        $selects[] = $hasNetPayable ? 'b.net_payable' : DB::raw('b.grand_total as net_payable');
        $selects[] = $hasPaymentStatus ? 'b.payment_status' : DB::raw('NULL as payment_status');

        $q = DB::table('production_bills as b')
            ->leftJoin('projects as p', 'p.id', '=', 'b.project_id')
            ->leftJoin('parties as c', 'c.id', '=', 'b.contractor_party_id')
            ->select($selects);

        if (!empty($filters['from_date'])) {
            $q->whereDate('b.bill_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('b.bill_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('b.project_id', $filters['project_id']);
        }
        if (!empty($filters['contractor_party_id'])) {
            $q->where('b.contractor_party_id', $filters['contractor_party_id']);
        }
        if (!empty($filters['gst_type'])) {
            $q->where('b.gst_type', $filters['gst_type']);
        }
        if (!empty($filters['status'])) {
            $q->where('b.status', $filters['status']);
        }
        if (!empty($filters['payment_status']) && $hasPaymentStatus) {
            $q->where('b.payment_status', $filters['payment_status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where('b.bill_number', 'like', "%{$term}%");
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(subtotal_amount),0) as sub, COALESCE(SUM(tax_total),0) as tax, COALESCE(SUM(grand_total),0) as grd, COALESCE(SUM(net_payable),0) as net')
            ->first();

        return [
            ['label' => 'Bills', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Subtotal', 'value' => number_format((float) ($row->sub ?? 0), 2)],
            ['label' => 'Tax', 'value' => number_format((float) ($row->tax ?? 0), 2)],
            ['label' => 'Grand Total', 'value' => number_format((float) ($row->grd ?? 0), 2)],
            ['label' => 'Net Payable', 'value' => number_format((float) ($row->net ?? 0), 2)],
        ];
    }
}
