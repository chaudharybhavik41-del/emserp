<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'hr-employee-register';
    }

    public function name(): string
    {
        return 'Employee Register';
    }

    public function module(): string
    {
        return 'HR';
    }

    public function description(): ?string
    {
        return 'Employee master list with department/designation/category and contact info.';
    }

    public function rules(): array
    {
        return [
            'department_id' => ['nullable','integer','exists:departments,id'],
            'hr_designation_id' => ['nullable','integer','exists:hr_designations,id'],
            'employment_type' => ['nullable','string','max:30'],
            'status' => ['nullable','string','max:30'],
            'is_active' => ['nullable','in:1,0'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $depts = DB::table('departments')->orderBy('name')->get(['id','name']);
        $desigs = DB::table('hr_designations')->orderBy('name')->get(['id','name']);

        $employmentTypes = DB::table('hr_employees')
            ->where('company_id', $this->companyId())
            ->select('employment_type')->distinct()->orderBy('employment_type')->pluck('employment_type')->filter()->values()->all();

        $statuses = DB::table('hr_employees')
            ->where('company_id', $this->companyId())
            ->select('status')->distinct()->orderBy('status')->pluck('status')->filter()->values()->all();

        return [
            [
                'name'=>'department_id','label'=>'Department','type'=>'select','col'=>3,
                'options'=>collect($depts)->map(fn($d)=>['value'=>$d->id,'label'=>$d->name])->all(),
            ],
            [
                'name'=>'hr_designation_id','label'=>'Designation','type'=>'select','col'=>3,
                'options'=>collect($desigs)->map(fn($d)=>['value'=>$d->id,'label'=>$d->name])->all(),
            ],
            [
                'name'=>'employment_type','label'=>'Employment','type'=>'select','col'=>2,
                'options'=>collect($employmentTypes)->map(fn($t)=>['value'=>$t,'label'=>strtoupper($t)])->all(),
            ],
            [
                'name'=>'status','label'=>'Status','type'=>'select','col'=>2,
                'options'=>collect($statuses)->map(fn($s)=>['value'=>$s,'label'=>strtoupper($s)])->all(),
            ],
            [
                'name'=>'is_active','label'=>'Active','type'=>'select','col'=>2,
                'options'=>[
                    ['value'=>'1','label'=>'Yes'],
                    ['value'=>'0','label'=>'No'],
                ],
            ],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'Code / Name / Mobile'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Emp Code','value'=>'employee_code','w'=>'12%'],
            ['label'=>'Name','value'=>'full_name','w'=>'22%'],
            ['label'=>'Department','value'=>'department_name','w'=>'14%'],
            ['label'=>'Designation','value'=>'designation_name','w'=>'14%'],
            ['label'=>'Employment','value'=>fn($r)=>strtoupper((string)$r->employment_type),'w'=>'10%'],
            ['label'=>'Category','value'=>fn($r)=>strtoupper((string)$r->employee_category),'w'=>'10%'],
            ['label'=>'Join Date','value'=>'date_of_joining','w'=>'9%'],
            ['label'=>'Mobile','value'=>'personal_mobile','w'=>'10%'],
            ['label'=>'Status','value'=>fn($r)=>strtoupper((string)$r->status),'w'=>'8%'],
            ['label'=>'Active','value'=>fn($r)=>((int)$r->is_active===1?'YES':'NO'),'w'=>'7%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'employee_code','direction'=>'asc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('hr_employees as e')
            ->leftJoin('departments as d','d.id','=','e.department_id')
            ->leftJoin('hr_designations as des','des.id','=','e.hr_designation_id')
            ->where('e.company_id', $this->companyId())
            ->select([
                'e.id',
                'e.employee_code',
                DB::raw("TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)) as full_name"),
                'e.personal_mobile',
                'e.date_of_joining',
                'e.employment_type',
                'e.employee_category',
                'e.status',
                'e.is_active',
                'd.name as department_name',
                'des.name as designation_name',
            ]);

        if (!empty($filters['department_id'])) {
            $q->where('e.department_id', $filters['department_id']);
        }
        if (!empty($filters['hr_designation_id'])) {
            $q->where('e.hr_designation_id', $filters['hr_designation_id']);
        }
        if (!empty($filters['employment_type'])) {
            $q->where('e.employment_type', $filters['employment_type']);
        }
        if (!empty($filters['status'])) {
            $q->where('e.status', $filters['status']);
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $q->where('e.is_active', (int)$filters['is_active']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function($sub) use($term){
                $sub->where('e.employee_code','like',"%{$term}%")
                    ->orWhere(DB::raw("TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name))"),'like',"%{$term}%")
                    ->orWhere('e.personal_mobile','like',"%{$term}%");
            });
        }

        return $q;
    }
}
