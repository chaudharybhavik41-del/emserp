<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientRaBillRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'billing-client-ra-bills';
    }

    public function name(): string
    {
        return 'Client RA Bill Register';
    }

    public function module(): string
    {
        return 'Billing';
    }

    public function description(): ?string
    {
        return 'Client running account bills (RA) with GST and receivable totals.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'project_id' => ['nullable','integer','exists:projects,id'],
            'client_id' => ['nullable','integer','exists:parties,id'],
            'revenue_type' => ['nullable','string','max:30'],
            'status' => ['nullable','string','max:30'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);
        $clients = DB::table('parties')->where('is_client',1)->orderBy('name')->limit(500)->get(['id','name']);

        $statusOptions = DB::table('client_ra_bills')
            ->where('company_id', $this->companyId())
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();

        $revTypes = [
            ['value'=>'fabrication','label'=>'FABRICATION'],
            ['value'=>'erection','label'=>'ERECTION'],
            ['value'=>'supply','label'=>'SUPPLY'],
            ['value'=>'service','label'=>'SERVICE'],
            ['value'=>'other','label'=>'OTHER'],
        ];

        return [
            ['name'=>'from_date','label'=>'From Date','type'=>'date','col'=>2],
            ['name'=>'to_date','label'=>'To Date','type'=>'date','col'=>2],
            [
                'name'=>'project_id','label'=>'Project','type'=>'select','col'=>3,
                'options'=>collect($projects)->map(fn($p)=>['value'=>$p->id,'label'=>trim(($p->code?$p->code.' - ':'').$p->name)])->all(),
            ],
            [
                'name'=>'client_id','label'=>'Client','type'=>'select','col'=>3,
                'options'=>collect($clients)->map(fn($c)=>['value'=>$c->id,'label'=>$c->name])->all(),
            ],
            ['name'=>'revenue_type','label'=>'Type','type'=>'select','col'=>2,'options'=>$revTypes],
            [
                'name'=>'status','label'=>'Status','type'=>'select','col'=>2,
                'options'=>collect($statusOptions)->map(fn($s)=>['value'=>$s,'label'=>strtoupper($s)])->all(),
            ],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'RA No / Invoice No / Contract'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'RA No','value'=>'ra_number','w'=>'12%'],
            ['label'=>'Invoice','value'=>'invoice_number','w'=>'12%'],
            ['label'=>'Bill Date','value'=>'bill_date','w'=>'9%'],
            ['label'=>'Project','value'=>fn($r)=>trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')),'w'=>'20%'],
            ['label'=>'Client','value'=>'client_name','w'=>'18%'],
            ['label'=>'Type','value'=>fn($r)=>strtoupper((string)$r->revenue_type),'w'=>'8%'],
            [
                'label'=>'Current','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->current_amount??0):number_format((float)($r->current_amount??0),2),
            ],
            [
                'label'=>'GST','align'=>'right','w'=>'8%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->total_gst??0):number_format((float)($r->total_gst??0),2),
            ],
            [
                'label'=>'Total','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->total_amount??0):number_format((float)($r->total_amount??0),2),
            ],
            [
                'label'=>'Receivable','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->receivable_amount??0):number_format((float)($r->receivable_amount??0),2),
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
        $q = DB::table('client_ra_bills as ra')
            ->leftJoin('projects as p','p.id','=','ra.project_id')
            ->leftJoin('parties as c','c.id','=','ra.client_id')
            ->where('ra.company_id', $this->companyId())
            ->select([
                'ra.id',
                'ra.ra_number',
                'ra.invoice_number',
                'ra.bill_date',
                'ra.revenue_type',
                'ra.current_amount',
                'ra.total_gst',
                'ra.total_amount',
                'ra.receivable_amount',
                'ra.status',
                'ra.contract_number',
                'p.code as project_code',
                'p.name as project_name',
                'c.name as client_name',
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('ra.bill_date','>=',$filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('ra.bill_date','<=',$filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('ra.project_id', $filters['project_id']);
        }
        if (!empty($filters['client_id'])) {
            $q->where('ra.client_id', $filters['client_id']);
        }
        if (!empty($filters['revenue_type'])) {
            $q->where('ra.revenue_type', $filters['revenue_type']);
        }
        if (!empty($filters['status'])) {
            $q->where('ra.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function($sub) use($term){
                $sub->where('ra.ra_number','like',"%{$term}%")
                    ->orWhere('ra.invoice_number','like',"%{$term}%")
                    ->orWhere('ra.contract_number','like',"%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(current_amount),0) as cur, COALESCE(SUM(total_gst),0) as gst, COALESCE(SUM(total_amount),0) as tot, COALESCE(SUM(receivable_amount),0) as rec')
            ->first();

        return [
            ['label'=>'RA Bills','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Current','value'=>number_format((float)($row->cur ?? 0),2)],
            ['label'=>'GST','value'=>number_format((float)($row->gst ?? 0),2)],
            ['label'=>'Total','value'=>number_format((float)($row->tot ?? 0),2)],
            ['label'=>'Receivable','value'=>number_format((float)($row->rec ?? 0),2)],
        ];
    }
}
