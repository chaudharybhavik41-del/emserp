<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'hr-attendance-register';
    }

    public function name(): string
    {
        return 'Attendance Register';
    }

    public function module(): string
    {
        return 'HR';
    }

    public function description(): ?string
    {
        return 'Daily attendance entries with working/OT hours and late/early minutes.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'hr_employee_id' => ['nullable','integer','exists:hr_employees,id'],
            'department_id' => ['nullable','integer','exists:departments,id'],
            'status' => ['nullable','string','max:30'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $employees = DB::table('hr_employees')
            ->where('company_id', $this->companyId())
            ->orderBy('employee_code')
            ->limit(800)
            ->get(['id','employee_code','first_name','middle_name','last_name']);

        $depts = DB::table('departments')->orderBy('name')->get(['id','name']);

        $statusOptions = [
            ['value'=>'present','label'=>'PRESENT'],
            ['value'=>'absent','label'=>'ABSENT'],
            ['value'=>'half_day','label'=>'HALF DAY'],
            ['value'=>'weekly_off','label'=>'WEEKLY OFF'],
            ['value'=>'holiday','label'=>'HOLIDAY'],
            ['value'=>'leave','label'=>'LEAVE'],
            ['value'=>'on_duty','label'=>'ON DUTY'],
            ['value'=>'comp_off','label'=>'COMP OFF'],
            ['value'=>'late','label'=>'LATE'],
            ['value'=>'early_leaving','label'=>'EARLY LEAVING'],
            ['value'=>'late_and_early','label'=>'LATE & EARLY'],
        ];

        return [
            ['name'=>'from_date','label'=>'From Date','type'=>'date','col'=>2],
            ['name'=>'to_date','label'=>'To Date','type'=>'date','col'=>2],
            [
                'name'=>'hr_employee_id','label'=>'Employee','type'=>'select','col'=>4,
                'options'=>collect($employees)->map(function($e){
                    $name = trim(implode(' ', array_filter([$e->first_name, $e->middle_name, $e->last_name])));
                    return ['value'=>$e->id,'label'=>trim(($e->employee_code?$e->employee_code.' - ':'').$name)];
                })->all(),
            ],
            [
                'name'=>'department_id','label'=>'Department','type'=>'select','col'=>3,
                'options'=>collect($depts)->map(fn($d)=>['value'=>$d->id,'label'=>$d->name])->all(),
            ],
            ['name'=>'status','label'=>'Status','type'=>'select','col'=>3,'options'=>$statusOptions],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'Emp Code / Name'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Date','value'=>'attendance_date','w'=>'9%'],
            ['label'=>'Emp Code','value'=>'employee_code','w'=>'10%'],
            ['label'=>'Name','value'=>'full_name','w'=>'20%'],
            ['label'=>'Department','value'=>'department_name','w'=>'12%'],
            ['label'=>'Status','value'=>fn($r)=>strtoupper((string)$r->status),'w'=>'10%'],
            ['label'=>'First In','value'=>'first_in','w'=>'12%'],
            ['label'=>'Last Out','value'=>'last_out','w'=>'12%'],
            [
                'label'=>'Work Hrs','align'=>'right','w'=>'7%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->working_hours??0):number_format((float)($r->working_hours??0),2),
            ],
            [
                'label'=>'OT Hrs','align'=>'right','w'=>'6%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->ot_hours??0):number_format((float)($r->ot_hours??0),2),
            ],
            ['label'=>'Late (min)','align'=>'right','value'=>fn($r)=>(int)($r->late_minutes ?? 0),'w'=>'7%'],
            ['label'=>'Early (min)','align'=>'right','value'=>fn($r)=>(int)($r->early_leaving_minutes ?? 0),'w'=>'7%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'attendance_date','direction'=>'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('hr_attendances as a')
            ->join('hr_employees as e','e.id','=','a.hr_employee_id')
            ->leftJoin('departments as d','d.id','=','e.department_id')
            ->where('e.company_id', $this->companyId())
            ->select([
                'a.id',
                'a.attendance_date',
                'a.first_in',
                'a.last_out',
                'a.working_hours',
                'a.ot_hours',
                'a.late_minutes',
                'a.early_leaving_minutes',
                'a.status',
                'e.employee_code',
                DB::raw("TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)) as full_name"),
                'd.name as department_name',
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('a.attendance_date','>=',$filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('a.attendance_date','<=',$filters['to_date']);
        }
        if (!empty($filters['hr_employee_id'])) {
            $q->where('a.hr_employee_id', $filters['hr_employee_id']);
        }
        if (!empty($filters['department_id'])) {
            $q->where('e.department_id', $filters['department_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('a.status', $filters['status']);
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
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(working_hours),0) as wh, COALESCE(SUM(ot_hours),0) as oh, COALESCE(SUM(late_minutes),0) as lm, COALESCE(SUM(early_leaving_minutes),0) as em')
            ->first();

        return [
            ['label'=>'Days','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Work Hrs','value'=>number_format((float)($row->wh ?? 0),2)],
            ['label'=>'OT Hrs','value'=>number_format((float)($row->oh ?? 0),2)],
            ['label'=>'Late (min)','value'=>(int)($row->lm ?? 0)],
            ['label'=>'Early (min)','value'=>(int)($row->em ?? 0)],
        ];
    }
}
