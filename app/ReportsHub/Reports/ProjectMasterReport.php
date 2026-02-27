<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectMasterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'masters-projects';
    }

    public function name(): string
    {
        return 'Project Master';
    }

    public function module(): string
    {
        return 'Masters';
    }

    public function description(): ?string
    {
        return 'List of Projects with client, dates and status.';
    }

    public function rules(): array
    {
        return [
            'client_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $clients = DB::table('parties')
            ->where('is_client', 1)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name']);

        $statusOptions = DB::table('projects')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();

        return [
            [
                'name' => 'client_party_id',
                'label' => 'Client',
                'type' => 'select',
                'col' => 4,
                'options' => collect($clients)->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            ],
            [
                'name' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'col' => 2,
                'options' => collect($statusOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            [
                'name' => 'q',
                'label' => 'Search',
                'type' => 'text',
                'col' => 4,
                'placeholder' => 'Code / Name / Site',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'Code', 'value' => 'code', 'w' => '10%'],
            ['label' => 'Name', 'value' => 'name', 'w' => '28%'],
            ['label' => 'Client', 'value' => 'client_name', 'w' => '22%'],
            ['label' => 'Start', 'value' => 'start_date', 'w' => '9%'],
            ['label' => 'End', 'value' => 'end_date', 'w' => '9%'],
            ['label' => 'Site', 'value' => 'site_location', 'w' => '16%'],
            ['label' => 'Status', 'value' => fn ($r) => strtoupper((string)$r->status), 'w' => '8%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'name', 'direction' => 'asc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('projects as pr')
            ->leftJoin('parties as c', 'c.id', '=', 'pr.client_party_id')
            ->select([
                'pr.id',
                'pr.code',
                'pr.name',
                'pr.status',
                'pr.start_date',
                'pr.end_date',
                'pr.site_location',
                'c.name as client_name',
            ]);

        if (!empty($filters['client_party_id'])) {
            $q->where('pr.client_party_id', $filters['client_party_id']);
        }

        if (!empty($filters['status'])) {
            $q->where('pr.status', $filters['status']);
        }

        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('pr.code', 'like', "%{$term}%")
                    ->orWhere('pr.name', 'like', "%{$term}%")
                    ->orWhere('pr.site_location', 'like', "%{$term}%");
            });
        }

        return $q;
    }
}
