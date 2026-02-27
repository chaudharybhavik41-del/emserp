<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreRequisitionRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'stores-requisition-register';
    }

    public function name(): string
    {
        return 'Store Requisition Register';
    }

    public function module(): string
    {
        return 'Stores';
    }

    public function description(): ?string
    {
        return 'Material requisitions raised by projects/users with required vs issued quantity.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'project_id' => ['nullable','integer','exists:projects,id'],
            'requested_by_user_id' => ['nullable','integer','exists:users,id'],
            'status' => ['nullable','string','max:30'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);
        $users = DB::table('users')->orderBy('name')->limit(300)->get(['id','name']);

        $statusOptions = DB::table('store_requisitions')
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
                'name'=>'requested_by_user_id','label'=>'Requested By','type'=>'select','col'=>3,
                'options'=>collect($users)->map(fn($u)=>['value'=>$u->id,'label'=>$u->name])->all(),
            ],
            [
                'name'=>'status','label'=>'Status','type'=>'select','col'=>2,
                'options'=>collect($statusOptions)->map(fn($s)=>['value'=>$s,'label'=>strtoupper($s)])->all(),
            ],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'Req No / Remarks'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Req No','value'=>'requisition_number','w'=>'14%'],
            ['label'=>'Date','value'=>'requisition_date','w'=>'9%'],
            ['label'=>'Project','value'=>fn($r)=>trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')),'w'=>'24%'],
            ['label'=>'Requested By','value'=>'requested_by_name','w'=>'14%'],
            ['label'=>'Items','align'=>'right','w'=>'7%','value'=>fn($r)=>(int)($r->item_count ?? 0)],
            [
                'label'=>'Req Qty','align'=>'right','w'=>'9%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->required_qty ?? 0):number_format((float)($r->required_qty ?? 0),3),
            ],
            [
                'label'=>'Issued Qty','align'=>'right','w'=>'9%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->issued_qty ?? 0):number_format((float)($r->issued_qty ?? 0),3),
            ],
            ['label'=>'Status','value'=>fn($r)=>strtoupper((string)$r->status),'w'=>'8%'],
            ['label'=>'Remarks','value'=>'remarks','w'=>'20%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'requisition_date','direction'=>'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('store_requisitions as sr')
            ->leftJoin('projects as p','p.id','=','sr.project_id')
            ->leftJoin('users as u','u.id','=','sr.requested_by_user_id')
            ->select([
                'sr.id',
                'sr.requisition_number',
                'sr.requisition_date',
                'sr.status',
                'sr.remarks',
                'p.code as project_code',
                'p.name as project_name',
                'u.name as requested_by_name',
                DB::raw('(select count(*) from store_requisition_lines l where l.store_requisition_id = sr.id) as item_count'),
                DB::raw('(select COALESCE(SUM(l.required_qty),0) from store_requisition_lines l where l.store_requisition_id = sr.id) as required_qty'),
                DB::raw('(select COALESCE(SUM(l.issued_qty),0) from store_requisition_lines l where l.store_requisition_id = sr.id) as issued_qty'),
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('sr.requisition_date','>=',$filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('sr.requisition_date','<=',$filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('sr.project_id', $filters['project_id']);
        }
        if (!empty($filters['requested_by_user_id'])) {
            $q->where('sr.requested_by_user_id', $filters['requested_by_user_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('sr.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function($sub) use($term){
                $sub->where('sr.requisition_number','like',"%{$term}%")
                    ->orWhere('sr.remarks','like',"%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(item_count),0) as items, COALESCE(SUM(required_qty),0) as rq, COALESCE(SUM(issued_qty),0) as iq')
            ->first();

        return [
            ['label'=>'Requisitions','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Items','value'=>(int)($row->items ?? 0)],
            ['label'=>'Req Qty','value'=>number_format((float)($row->rq ?? 0),3)],
            ['label'=>'Issued Qty','value'=>number_format((float)($row->iq ?? 0),3)],
        ];
    }
}
