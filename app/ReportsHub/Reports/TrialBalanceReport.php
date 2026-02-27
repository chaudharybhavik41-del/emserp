<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrialBalanceReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'accounts-trial-balance';
    }

    public function name(): string
    {
        return 'Trial Balance';
    }

    public function module(): string
    {
        return 'Accounts';
    }

    public function description(): ?string
    {
        return 'Trial balance summary using Opening + Period Dr/Cr to compute Closing Dr/Cr.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'project_id' => ['nullable','integer','exists:projects,id'],
            'only_nonzero' => ['nullable','in:1,0'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);

        return [
            ['name'=>'from_date','label'=>'From Date','type'=>'date','col'=>2],
            ['name'=>'to_date','label'=>'To Date','type'=>'date','col'=>2],
            [
                'name'=>'project_id','label'=>'Project','type'=>'select','col'=>4,
                'options'=>collect($projects)->map(fn($p)=>['value'=>$p->id,'label'=>trim(($p->code?$p->code.' - ':'').$p->name)])->all(),
            ],
            [
                'name'=>'only_nonzero','label'=>'Only Non-Zero','type'=>'select','col'=>3,
                'options'=>[
                    ['value'=>'1','label'=>'Yes'],
                    ['value'=>'0','label'=>'No'],
                ],
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Group', 'value'=>'group_name', 'w'=>'14%'],
            ['label'=>'Code', 'value'=>'code', 'w'=>'10%'],
            ['label'=>'Account', 'value'=>'name', 'w'=>'26%'],
            [
                'label'=>'Opening Dr', 'align'=>'right', 'w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->opening_dr??0):number_format((float)($r->opening_dr??0),2),
            ],
            [
                'label'=>'Opening Cr', 'align'=>'right', 'w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->opening_cr??0):number_format((float)($r->opening_cr??0),2),
            ],
            [
                'label'=>'Period Dr', 'align'=>'right', 'w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->period_dr??0):number_format((float)($r->period_dr??0),2),
            ],
            [
                'label'=>'Period Cr', 'align'=>'right', 'w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->period_cr??0):number_format((float)($r->period_cr??0),2),
            ],
            [
                'label'=>'Closing Dr', 'align'=>'right', 'w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->closing_dr??0):number_format((float)($r->closing_dr??0),2),
            ],
            [
                'label'=>'Closing Cr', 'align'=>'right', 'w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->closing_cr??0):number_format((float)($r->closing_cr??0),2),
            ],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'group_name','direction'=>'asc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $companyId = $this->companyId();
        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? null;
        $projectId = $filters['project_id'] ?? null;

        $lines = DB::table('voucher_lines as vl')
            ->join('vouchers as v','v.id','=','vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->when($from, fn($q) => $q->whereDate('v.voucher_date','>=',$from))
            ->when($to, fn($q) => $q->whereDate('v.voucher_date','<=',$to))
            ->when($projectId, fn($q) => $q->where('v.project_id',$projectId))
            ->groupBy('vl.account_id')
            ->selectRaw('vl.account_id, COALESCE(SUM(vl.debit),0) as period_dr, COALESCE(SUM(vl.credit),0) as period_cr');

        $q = DB::table('accounts as a')
            ->leftJoinSub($lines, 's', function($join){
                $join->on('s.account_id','=','a.id');
            })
            ->leftJoin('account_groups as g','g.id','=','a.account_group_id')
            ->where('a.company_id', $companyId)
            ->select([
                'a.id',
                'a.code',
                'a.name',
                'g.name as group_name',
                DB::raw("CASE WHEN a.opening_balance_type='dr' THEN COALESCE(a.opening_balance,0) ELSE 0 END as opening_dr"),
                DB::raw("CASE WHEN a.opening_balance_type='cr' THEN COALESCE(a.opening_balance,0) ELSE 0 END as opening_cr"),
                DB::raw('COALESCE(s.period_dr,0) as period_dr'),
                DB::raw('COALESCE(s.period_cr,0) as period_cr'),
                DB::raw("GREATEST((CASE WHEN a.opening_balance_type='dr' THEN COALESCE(a.opening_balance,0) ELSE -COALESCE(a.opening_balance,0) END + COALESCE(s.period_dr,0) - COALESCE(s.period_cr,0)), 0) as closing_dr"),
                DB::raw("ABS(LEAST((CASE WHEN a.opening_balance_type='dr' THEN COALESCE(a.opening_balance,0) ELSE -COALESCE(a.opening_balance,0) END + COALESCE(s.period_dr,0) - COALESCE(s.period_cr,0)), 0)) as closing_cr"),
            ]);

        if (isset($filters['only_nonzero']) && $filters['only_nonzero'] === '1') {
            $q->where(function($w){
                $w->whereRaw('COALESCE(a.opening_balance,0) <> 0')
                  ->orWhereRaw('COALESCE(s.period_dr,0) <> 0')
                  ->orWhereRaw('COALESCE(s.period_cr,0) <> 0');
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        // Wrap to safely sum computed columns
        $sub = DB::query()->fromSub($query, 't');
        $row = $sub->selectRaw('COUNT(*) as cnt, COALESCE(SUM(opening_dr),0) as odr, COALESCE(SUM(opening_cr),0) as ocr, COALESCE(SUM(period_dr),0) as pdr, COALESCE(SUM(period_cr),0) as pcr, COALESCE(SUM(closing_dr),0) as cdr, COALESCE(SUM(closing_cr),0) as ccr')->first();

        return [
            ['label'=>'Accounts', 'value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Opening Dr', 'value'=>number_format((float)($row->odr ?? 0),2)],
            ['label'=>'Opening Cr', 'value'=>number_format((float)($row->ocr ?? 0),2)],
            ['label'=>'Period Dr', 'value'=>number_format((float)($row->pdr ?? 0),2)],
            ['label'=>'Period Cr', 'value'=>number_format((float)($row->pcr ?? 0),2)],
            ['label'=>'Closing Dr', 'value'=>number_format((float)($row->cdr ?? 0),2)],
            ['label'=>'Closing Cr', 'value'=>number_format((float)($row->ccr ?? 0),2)],
        ];
    }
}
