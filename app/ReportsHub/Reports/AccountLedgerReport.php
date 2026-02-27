<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountLedgerReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'accounts-ledger';
    }

    public function name(): string
    {
        return 'Account Ledger';
    }

    public function module(): string
    {
        return 'Accounts';
    }

    public function description(): ?string
    {
        return 'Voucher lines for a selected account (ledger). Select an Account to view entries.';
    }

    public function rules(): array
    {
        return [
            'account_id' => ['nullable','integer','exists:accounts,id'],
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'voucher_type' => ['nullable','string','max:30'],
            'project_id' => ['nullable','integer','exists:projects,id'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $accounts = DB::table('accounts')
            ->where('company_id', $this->companyId())
            ->orderBy('name')
            ->limit(1000)
            ->get(['id','code','name']);

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

        return [
            [
                'name'=>'account_id','label'=>'Account','type'=>'select','col'=>6,
                'options'=>collect($accounts)->map(fn($a)=>['value'=>$a->id,'label'=>trim(($a->code?$a->code.' - ':'').$a->name)])->all(),
            ],
            ['name'=>'from_date','label'=>'From Date','type'=>'date','col'=>2],
            ['name'=>'to_date','label'=>'To Date','type'=>'date','col'=>2],
            [
                'name'=>'voucher_type','label'=>'Type','type'=>'select','col'=>2,
                'options'=>collect($typeOptions)->map(fn($t)=>['value'=>$t,'label'=>strtoupper($t)])->all(),
            ],
            [
                'name'=>'project_id','label'=>'Project','type'=>'select','col'=>4,
                'options'=>collect($projects)->map(fn($p)=>['value'=>$p->id,'label'=>trim(($p->code?$p->code.' - ':'').$p->name)])->all(),
            ],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'Voucher No / Narration / Ref'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Date','value'=>'voucher_date','w'=>'9%'],
            ['label'=>'Voucher No','value'=>'voucher_no','w'=>'14%'],
            ['label'=>'Type','value'=>fn($r)=>strtoupper((string)$r->voucher_type),'w'=>'10%'],
            ['label'=>'Project','value'=>fn($r)=>trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')),'w'=>'22%'],
            ['label'=>'Narration','value'=>'narration','w'=>'30%'],
            [
                'label'=>'Dr','align'=>'right','w'=>'8%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->debit??0):number_format((float)($r->debit??0),2),
            ],
            [
                'label'=>'Cr','align'=>'right','w'=>'8%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->credit??0):number_format((float)($r->credit??0),2),
            ],
            ['label'=>'Ref','value'=>'reference_number','w'=>'12%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'voucher_date','direction'=>'asc'];
    }

    public function query(array $filters): QueryBuilder
    {
        if (empty($filters['account_id'])) {
            // Avoid accidentally loading the entire voucher_lines table.
            return DB::table('voucher_lines')->whereRaw('1=0');
        }

        $q = DB::table('voucher_lines as vl')
            ->join('vouchers as v','v.id','=','vl.voucher_id')
            ->leftJoin('projects as p','p.id','=','v.project_id')
            ->where('v.company_id', $this->companyId())
            ->where('vl.account_id', $filters['account_id'])
            ->select([
                'vl.id',
                'v.voucher_date',
                'v.voucher_no',
                'v.voucher_type',
                'v.reference as reference_number',
                'v.narration',
                'vl.debit',
                'vl.credit',
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
            ->selectRaw('COALESCE(SUM(debit),0) as dr, COALESCE(SUM(credit),0) as cr')
            ->first();

        return [
            ['label'=>'Total Dr','value'=>number_format((float)($row->dr ?? 0),2)],
            ['label'=>'Total Cr','value'=>number_format((float)($row->cr ?? 0),2)],
        ];
    }
}
