<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartyMasterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'masters-parties';
    }

    public function name(): string
    {
        return 'Party Master';
    }

    public function module(): string
    {
        return 'Masters';
    }

    public function description(): ?string
    {
        return 'List of Parties (Clients / Suppliers / Contractors) with GST/PAN and contact details.';
    }

    public function rules(): array
    {
        return [
            'role' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'in:1,0'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        return [
            [
                'name' => 'role',
                'label' => 'Role',
                'type' => 'select',
                'col' => 3,
                'options' => [
                    ['value' => 'client', 'label' => 'Client'],
                    ['value' => 'supplier', 'label' => 'Supplier'],
                    ['value' => 'contractor', 'label' => 'Contractor'],
                ],
            ],
            [
                'name' => 'is_active',
                'label' => 'Active',
                'type' => 'select',
                'col' => 2,
                'options' => [
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ],
            ],
            [
                'name' => 'q',
                'label' => 'Search',
                'type' => 'text',
                'col' => 4,
                'placeholder' => 'Name / GSTIN / PAN / Phone',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'Code', 'value' => 'code', 'w' => '10%'],
            ['label' => 'Name', 'value' => 'name', 'w' => '28%'],
            ['label' => 'Role', 'value' => 'role_label', 'w' => '10%'],
            ['label' => 'GSTIN', 'value' => 'gstin', 'w' => '14%'],
            ['label' => 'PAN', 'value' => 'pan', 'w' => '10%'],
            ['label' => 'Phone', 'value' => 'primary_phone', 'w' => '10%'],
            ['label' => 'Email', 'value' => 'primary_email', 'w' => '18%'],
            ['label' => 'City', 'value' => 'city', 'w' => '10%'],
            ['label' => 'State', 'value' => 'state', 'w' => '10%'],
            ['label' => 'Active', 'value' => fn ($r) => ((int)$r->is_active === 1) ? 'YES' : 'NO', 'w' => '7%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'name', 'direction' => 'asc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('parties as p')
            ->select([
                'p.id',
                'p.code',
                'p.name',
                'p.gstin',
                'p.pan',
                'p.primary_phone',
                'p.primary_email',
                'p.city',
                'p.state',
                'p.is_supplier',
                'p.is_contractor',
                'p.is_client',
                'p.is_active',
            ]);

        if (!empty($filters['role'])) {
            $role = $filters['role'];
            if ($role === 'client') {
                $q->where('p.is_client', 1);
            } elseif ($role === 'supplier') {
                $q->where('p.is_supplier', 1);
            } elseif ($role === 'contractor') {
                $q->where('p.is_contractor', 1);
            }
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $q->where('p.is_active', (int) $filters['is_active']);
        }

        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('p.name', 'like', "%{$term}%")
                    ->orWhere('p.code', 'like', "%{$term}%")
                    ->orWhere('p.gstin', 'like', "%{$term}%")
                    ->orWhere('p.pan', 'like', "%{$term}%")
                    ->orWhere('p.primary_phone', 'like', "%{$term}%");
            });
        }

        // Add computed role label via selectRaw to keep export simple
        $q->addSelect(DB::raw("TRIM(CONCAT(IF(p.is_client=1,'CLIENT ',''),IF(p.is_supplier=1,'SUPPLIER ',''),IF(p.is_contractor=1,'CONTRACTOR ',''))) as role_label"));

        return $q;
    }
}
