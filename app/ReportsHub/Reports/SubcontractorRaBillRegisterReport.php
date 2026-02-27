<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubcontractorRaBillRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'billing-subcontractor-ra-bills';
    }

    public function name(): string
    {
        return 'Subcontractor RA Bill Register';
    }

    public function module(): string
    {
        return 'Billing';
    }

    public function description(): ?string
    {
        return 'Subcontractor RA bills with totals and status.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'project_id' => ['nullable','integer','exists:projects,id'],
            'subcontractor_id' => ['nullable','integer','exists:parties,id'],
            'status' => ['nullable','string','max:30'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);
        $subs = DB::table('parties')->where('is_contractor',1)->orderBy('name')->limit(500)->get(['id','name']);

        $statusOptions = DB::table('subcontractor_ra_bills')
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
                'name'=>'project_id','label'=>'Project','type'=>'select','col'=>3,
                'options'=>collect($projects)->map(fn($p)=>['value'=>$p->id,'label'=>trim(($p->code?$p->code.' - ':'').$p->name)])->all(),
            ],
            [
                'name'=>'subcontractor_id','label'=>'Subcontractor','type'=>'select','col'=>3,
                'options'=>collect($subs)->map(fn($s)=>['value'=>$s->id,'label'=>$s->name])->all(),
            ],
            [
                'name'=>'status','label'=>'Status','type'=>'select','col'=>2,
                'options'=>collect($statusOptions)->map(fn($s)=>['value'=>$s,'label'=>strtoupper($s)])->all(),
            ],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'RA No / Bill No'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'RA No','value'=>'ra_number','w'=>'14%'],
            ['label'=>'Bill No','value'=>'bill_number','w'=>'14%'],
            ['label'=>'Bill Date','value'=>'bill_date','w'=>'9%'],
            ['label'=>'Project','value'=>fn($r)=>trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')),'w'=>'22%'],
            ['label'=>'Subcontractor','value'=>'subcontractor_name','w'=>'20%'],
            [
                'label'=>'Current','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->current_amount??0):number_format((float)($r->current_amount??0),2),
            ],
            [
                'label'=>'Total','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->total_amount??0):number_format((float)($r->total_amount??0),2),
            ],
            ['label'=>'Status','value'=>fn($r)=>strtoupper((string)$r->status),'w'=>'8%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'bill_date','direction'=>'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('subcontractor_ra_bills as rb')
            ->leftJoin('projects as p','p.id','=','rb.project_id')
            ->leftJoin('parties as s','s.id','=','rb.subcontractor_id')
            ->where('rb.company_id', $this->companyId())
            ->select([
                'rb.id',
                'rb.ra_number',
                'rb.bill_number',
                'rb.bill_date',
                'rb.current_amount',
                'rb.total_amount',
                'rb.status',
                'p.code as project_code',
                'p.name as project_name',
                's.name as subcontractor_name',
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('rb.bill_date','>=',$filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('rb.bill_date','<=',$filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('rb.project_id', $filters['project_id']);
        }
        if (!empty($filters['subcontractor_id'])) {
            $q->where('rb.subcontractor_id', $filters['subcontractor_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('rb.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function($sub) use($term){
                $sub->where('rb.ra_number','like',"%{$term}%")
                    ->orWhere('rb.bill_number','like',"%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(current_amount),0) as cur, COALESCE(SUM(total_amount),0) as tot')
            ->first();

        return [
            ['label'=>'RA Bills','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Current','value'=>number_format((float)($row->cur ?? 0),2)],
            ['label'=>'Total','value'=>number_format((float)($row->tot ?? 0),2)],
        ];
    }
}
