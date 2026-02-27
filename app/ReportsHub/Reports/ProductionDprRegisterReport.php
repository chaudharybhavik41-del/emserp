<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionDprRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'production-dpr-register';
    }

    public function name(): string
    {
        return 'Production DPR Register';
    }

    public function module(): string
    {
        return 'Production';
    }

    public function description(): ?string
    {
        return 'Daily production reports (DPR) by plan/activity with output/scrap totals.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'production_activity_id' => ['nullable', 'integer', 'exists:production_activities,id'],
            'contractor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'supervisor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'shift' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);
        $activities = DB::table('production_activities')->orderBy('name')->limit(300)->get(['id', 'name']);
        $contractors = DB::table('parties')->where('is_contractor', 1)->orderBy('name')->limit(500)->get(['id', 'name']);
        $users = DB::table('users')->orderBy('name')->limit(400)->get(['id', 'name']);

        $shiftOptions = DB::table('production_dprs')
            ->select('shift')
            ->distinct()
            ->orderBy('shift')
            ->pluck('shift')
            ->filter()
            ->values()
            ->all();

        return [
            ['name' => 'from_date', 'label' => 'DPR From', 'type' => 'date', 'col' => 2],
            ['name' => 'to_date', 'label' => 'DPR To', 'type' => 'date', 'col' => 2],
            [
                'name' => 'project_id', 'label' => 'Project', 'type' => 'select', 'col' => 3,
                'options' => collect($projects)->map(fn ($p) => [
                    'value' => $p->id,
                    'label' => trim(($p->code ? $p->code . ' - ' : '') . $p->name),
                ])->all(),
            ],
            [
                'name' => 'production_activity_id', 'label' => 'Activity', 'type' => 'select', 'col' => 3,
                'options' => collect($activities)->map(fn ($a) => ['value' => $a->id, 'label' => $a->name])->all(),
            ],
            [
                'name' => 'contractor_party_id', 'label' => 'Contractor', 'type' => 'select', 'col' => 3,
                'options' => collect($contractors)->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            ],
            [
                'name' => 'supervisor_user_id', 'label' => 'Supervisor', 'type' => 'select', 'col' => 3,
                'options' => collect($users)->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
            ],
            [
                'name' => 'shift', 'label' => 'Shift', 'type' => 'select', 'col' => 2,
                'options' => collect($shiftOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'col' => 4, 'placeholder' => 'Plan No'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'DPR ID', 'value' => 'id', 'w' => '7%'],
            ['label' => 'Date', 'value' => 'dpr_date', 'w' => '9%'],
            ['label' => 'Shift', 'value' => fn ($r) => strtoupper((string) $r->shift), 'w' => '7%'],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '20%'],
            ['label' => 'Plan No', 'value' => 'plan_number', 'w' => '12%'],
            ['label' => 'Activity', 'value' => 'activity_name', 'w' => '12%'],
            ['label' => 'Contractor', 'value' => 'contractor_name', 'w' => '12%'],
            ['label' => 'Supervisor', 'value' => 'supervisor_name', 'w' => '10%'],
            ['label' => 'Team', 'align' => 'right', 'value' => fn ($r) => (int) ($r->team_size ?? 0), 'w' => '6%'],
            ['label' => 'Out Pcs', 'align' => 'right', 'value' => fn ($r) => (int) ($r->output_qty_pcs ?? 0), 'w' => '7%'],
            [
                'label' => 'Out Wt', 'align' => 'right', 'w' => '7%',
                'value' => fn ($r, $forExport) => $forExport ? (float) ($r->output_weight_kg ?? 0) : number_format((float) ($r->output_weight_kg ?? 0), 3),
            ],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'dpr_date', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('production_dprs as d')
            ->leftJoin('production_plans as pp', 'pp.id', '=', 'd.production_plan_id')
            ->leftJoin('projects as p', 'p.id', '=', 'pp.project_id')
            ->leftJoin('production_activities as a', 'a.id', '=', 'd.production_activity_id')
            ->leftJoin('parties as c', 'c.id', '=', 'd.contractor_party_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.supervisor_user_id')
            ->select([
                'd.id',
                'd.dpr_date',
                'd.shift',
                'd.team_size',
                'd.output_qty_pcs',
                'd.output_weight_kg',
                'd.scrap_qty_pcs',
                'd.scrap_weight_kg',
                'pp.plan_number',
                'p.code as project_code',
                'p.name as project_name',
                'a.name as activity_name',
                'c.name as contractor_name',
                'u.name as supervisor_name',
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('d.dpr_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('d.dpr_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('pp.project_id', $filters['project_id']);
        }
        if (!empty($filters['production_activity_id'])) {
            $q->where('d.production_activity_id', $filters['production_activity_id']);
        }
        if (!empty($filters['contractor_party_id'])) {
            $q->where('d.contractor_party_id', $filters['contractor_party_id']);
        }
        if (!empty($filters['supervisor_user_id'])) {
            $q->where('d.supervisor_user_id', $filters['supervisor_user_id']);
        }
        if (!empty($filters['shift'])) {
            $q->where('d.shift', $filters['shift']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where('pp.plan_number', 'like', "%{$term}%");
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(output_qty_pcs),0) as out_pcs, COALESCE(SUM(output_weight_kg),0) as out_wt, COALESCE(SUM(scrap_qty_pcs),0) as sc_pcs, COALESCE(SUM(scrap_weight_kg),0) as sc_wt')
            ->first();

        return [
            ['label' => 'DPRs', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Output Pcs', 'value' => (int) ($row->out_pcs ?? 0)],
            ['label' => 'Output Wt (kg)', 'value' => number_format((float) ($row->out_wt ?? 0), 3)],
            ['label' => 'Scrap Pcs', 'value' => (int) ($row->sc_pcs ?? 0)],
            ['label' => 'Scrap Wt (kg)', 'value' => number_format((float) ($row->sc_wt ?? 0), 3)],
        ];
    }
}
