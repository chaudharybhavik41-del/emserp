<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'accounts-voucher-register';
    }

    public function name(): string
    {
        return 'Voucher Register';
    }

    public function module(): string
    {
        return 'Accounts';
    }

    public function description(): ?string
    {
        return 'Accounting vouchers with type/status/project filters.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'voucher_type' => ['nullable','string','max:30'],
            'status' => ['nullable','string','max:30'],
            'project_id' => ['nullable','integer','exists:projects,id'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);

        $typeOptions = DB::table('vouchers')
            ->where('company_id', $this->companyId())
            ->select('voucher_type')
            ->distinct()
            ->orderBy('voucher_type')
            ->pluck('voucher_type')
            ->filter()
            ->values()
            ->all();

        $statusOptions = DB::table('vouchers')
            ->where('company_id', $this->companyId())
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();

        return [
            ['name'=>'from_date','label'=>'From Date','type'=>'date','col'=>2],
            ['name'=>'to_date','label'=>'To Date','type'=>'date','col'=>2],
            [
                'name'=>'voucher_type','label'=>'Type','type'=>'select','col'=>2,
                'options'=>collect($typeOptions)->map(fn($t)=>['value'=>$t,'label'=>strtoupper($t)])->all(),
            ],
            [
                'name'=>'status','label'=>'Status','type'=>'select','col'=>2,
                'options'=>collect($statusOptions)->map(fn($s)=>['value'=>$s,'label'=>strtoupper($s)])->all(),
            ],
            [
                'name'=>'project_id','label'=>'Project','type'=>'select','col'=>4,
                'options'=>collect($projects)->map(fn($p)=>['value'=>$p->id,'label'=>trim(($p->code?$p->code.' - ':'').$p->name)])->all(),
            ],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'Voucher No / Reference / Narration'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Voucher No','value'=>'voucher_no','w'=>'14%'],
            ['label'=>'Date','value'=>'voucher_date','w'=>'9%'],
            ['label'=>'Type','value'=>fn($r)=>strtoupper((string)$r->voucher_type),'w'=>'10%'],
            ['label'=>'Status','value'=>fn($r)=>strtoupper((string)$r->status),'w'=>'8%'],
            ['label'=>'Project','value'=>fn($r)=>trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')),'w'=>'22%'],
            ['label'=>'Reference','value'=>'reference_number','w'=>'14%'],
            [
                'label'=>'Amount','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->amount_base??0):number_format((float)($r->amount_base??0),2),
            ],
            ['label'=>'Narration','value'=>'narration','w'=>'25%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'voucher_date','direction'=>'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('vouchers as v')
            ->leftJoin('projects as p','p.id','=','v.project_id')
            ->where('v.company_id', $this->companyId())
            ->select([
                'v.id',
                'v.voucher_no',
                'v.voucher_date',
                'v.voucher_type',
                'v.status',
                'v.reference as reference_number',
                'v.narration',
                'v.amount_base',
                'p.code as project_code',
                'p.name as project_name',
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('v.voucher_date','>=',$filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('v.voucher_date','<=',$filters['to_date']);
        }
        if (!empty($filters['voucher_type'])) {
            $q->where('v.voucher_type', $filters['voucher_type']);
        }
        if (!empty($filters['status'])) {
            $q->where('v.status', $filters['status']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('v.project_id', $filters['project_id']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function($sub) use($term){
                $sub->where('v.voucher_no','like',"%{$term}%")
                    ->orWhere('v.reference','like',"%{$term}%")
                    ->orWhere('v.narration','like',"%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount_base),0) as tot')
            ->first();

        return [
            ['label'=>'Vouchers','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Total Amount','value'=>number_format((float)($row->tot ?? 0),2)],
        ];
    }
}
