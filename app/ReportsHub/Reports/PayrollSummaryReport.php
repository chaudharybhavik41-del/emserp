<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollSummaryReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'hr-payroll-summary';
    }

    public function name(): string
    {
        return 'Payroll Summary';
    }

    public function module(): string
    {
        return 'HR';
    }

    public function description(): ?string
    {
        return 'Payroll for a selected period with gross/deductions/net/payable totals.';
    }

    public function rules(): array
    {
        return [
            'hr_payroll_period_id' => ['nullable','integer','exists:hr_payroll_periods,id'],
            'department_id' => ['nullable','integer','exists:departments,id'],
            'status' => ['nullable','string','max:30'],
            'is_hold' => ['nullable','in:1,0'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $periods = DB::table('hr_payroll_periods')
            ->where('company_id', $this->companyId())
            ->orderByDesc('start_date')
            ->limit(36)
            ->get(['id','period_name','start_date','end_date']);

        $depts = DB::table('departments')->orderBy('name')->get(['id','name']);

        $statusOptions = DB::table('hr_payrolls')
            ->select('status')->distinct()->orderBy('status')->pluck('status')->filter()->values()->all();

        return [
            [
                'name'=>'hr_payroll_period_id','label'=>'Period','type'=>'select','col'=>5,
                'options'=>collect($periods)->map(fn($p)=>['value'=>$p->id,'label'=>$p->period_name.' ('.$p->start_date.' to '.$p->end_date.')'])->all(),
            ],
            [
                'name'=>'department_id','label'=>'Department','type'=>'select','col'=>3,
                'options'=>collect($depts)->map(fn($d)=>['value'=>$d->id,'label'=>$d->name])->all(),
            ],
            [
                'name'=>'status','label'=>'Status','type'=>'select','col'=>2,
                'options'=>collect($statusOptions)->map(fn($s)=>['value'=>$s,'label'=>strtoupper($s)])->all(),
            ],
            [
                'name'=>'is_hold','label'=>'Hold','type'=>'select','col'=>2,
                'options'=>[
                    ['value'=>'1','label'=>'Yes'],
                    ['value'=>'0','label'=>'No'],
                ],
            ],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'Emp Code / Name'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Emp Code','value'=>'employee_code','w'=>'10%'],
            ['label'=>'Name','value'=>'full_name','w'=>'20%'],
            ['label'=>'Department','value'=>'department_name','w'=>'12%'],
            ['label'=>'Days Paid','align'=>'right','value'=>fn($r)=>(int)($r->days_paid ?? 0),'w'=>'7%'],
            [
                'label'=>'Gross','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->gross_salary??0):number_format((float)($r->gross_salary??0),2),
            ],
            [
                'label'=>'Deductions','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->total_deductions??0):number_format((float)($r->total_deductions??0),2),
            ],
            [
                'label'=>'Net','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->net_salary??0):number_format((float)($r->net_salary??0),2),
            ],
            [
                'label'=>'Payable','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->payable_amount??0):number_format((float)($r->payable_amount??0),2),
            ],
            ['label'=>'Status','value'=>fn($r)=>strtoupper((string)$r->status),'w'=>'8%'],
            ['label'=>'Pay Date','value'=>'payment_date','w'=>'9%'],
            ['label'=>'Ref','value'=>'payment_reference','w'=>'14%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'employee_code','direction'=>'asc'];
    }

    public function query(array $filters): QueryBuilder
    {
        if (empty($filters['hr_payroll_period_id'])) {
            // Avoid accidental full scan.
            return DB::table('hr_payrolls')->whereRaw('1=0');
        }

        $q = DB::table('hr_payrolls as pr')
            ->join('hr_payroll_periods as pe','pe.id','=','pr.hr_payroll_period_id')
            ->join('hr_employees as e','e.id','=','pr.hr_employee_id')
            ->leftJoin('departments as d','d.id','=','e.department_id')
            ->where('pe.company_id', $this->companyId())
            ->where('pr.hr_payroll_period_id', $filters['hr_payroll_period_id'])
            ->select([
                'pr.id',
                'pr.status',
                'pr.payment_reference',
                'pr.payment_date',
                'pr.is_hold',
                'pr.days_paid',
                'pr.gross_salary',
                'pr.total_deductions',
                'pr.net_salary',
                'pr.payable_amount',
                'e.employee_code',
                DB::raw("TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)) as full_name"),
                'd.name as department_name',
            ]);

        if (!empty($filters['department_id'])) {
            $q->where('e.department_id', $filters['department_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('pr.status', $filters['status']);
        }
        if (isset($filters['is_hold']) && $filters['is_hold'] !== '') {
            $q->where('pr.is_hold', (int)$filters['is_hold']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function($sub) use($term){
                $sub->where('e.employee_code','like',"%{$term}%")
                    ->orWhere(DB::raw("TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name))"),'like',"%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(gross_salary),0) as gross, COALESCE(SUM(total_deductions),0) as ded, COALESCE(SUM(net_salary),0) as net, COALESCE(SUM(payable_amount),0) as pay')
            ->first();

        return [
            ['label'=>'Employees','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Gross','value'=>number_format((float)($row->gross ?? 0),2)],
            ['label'=>'Deductions','value'=>number_format((float)($row->ded ?? 0),2)],
            ['label'=>'Net','value'=>number_format((float)($row->net ?? 0),2)],
            ['label'=>'Payable','value'=>number_format((float)($row->pay ?? 0),2)],
        ];
    }
}
