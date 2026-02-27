<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemMasterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'masters-items';
    }

    public function name(): string
    {
        return 'Item Master';
    }

    public function module(): string
    {
        return 'Masters';
    }

    public function description(): ?string
    {
        return 'Item catalogue with type/category/subcategory, UOM and tax/HSN.';
    }

    public function rules(): array
    {
        return [
            'material_type_id' => ['nullable', 'integer', 'exists:material_types,id'],
            'material_category_id' => ['nullable', 'integer', 'exists:material_categories,id'],
            'is_active' => ['nullable', 'in:1,0'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $types = DB::table('material_types')
            ->orderBy('name')
            ->get(['id', 'name']);

        $cats = DB::table('material_categories')
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            [
                'name' => 'material_type_id',
                'label' => 'Type',
                'type' => 'select',
                'col' => 3,
                'options' => collect($types)->map(fn ($t) => ['value' => $t->id, 'label' => $t->name])->all(),
            ],
            [
                'name' => 'material_category_id',
                'label' => 'Category',
                'type' => 'select',
                'col' => 3,
                'options' => collect($cats)->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
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
                'placeholder' => 'Code / Name / HSN',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'Code', 'value' => 'code', 'w' => '10%'],
            ['label' => 'Name', 'value' => 'name', 'w' => '30%'],
            ['label' => 'Type', 'value' => 'type_name', 'w' => '12%'],
            ['label' => 'Category', 'value' => 'category_name', 'w' => '14%'],
            ['label' => 'Subcategory', 'value' => 'subcategory_name', 'w' => '14%'],
            ['label' => 'UOM', 'value' => 'uom_name', 'w' => '6%'],
            ['label' => 'HSN', 'value' => 'hsn_code', 'w' => '8%'],
            [
                'label' => 'GST %',
                'align' => 'right',
                'w' => '6%',
                'value' => fn ($r, $forExport) => $forExport ? (float)($r->gst_rate_percent ?? 0) : rtrim(rtrim(number_format((float)($r->gst_rate_percent ?? 0), 2), '0'), '.'),
            ],
            ['label' => 'Active', 'value' => fn ($r) => ((int)$r->is_active === 1) ? 'YES' : 'NO', 'w' => '6%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'name', 'direction' => 'asc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('items as i')
            ->leftJoin('material_types as mt', 'mt.id', '=', 'i.material_type_id')
            ->leftJoin('material_categories as mc', 'mc.id', '=', 'i.material_category_id')
            ->leftJoin('material_subcategories as ms', 'ms.id', '=', 'i.material_subcategory_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'i.uom_id')
            ->select([
                'i.id',
                'i.code',
                'i.name',
                'i.hsn_code',
                'i.gst_rate_percent',
                'i.is_active',
                'mt.name as type_name',
                'mc.name as category_name',
                'ms.name as subcategory_name',
                'u.name as uom_name',
            ]);

        if (!empty($filters['material_type_id'])) {
            $q->where('i.material_type_id', $filters['material_type_id']);
        }
        if (!empty($filters['material_category_id'])) {
            $q->where('i.material_category_id', $filters['material_category_id']);
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $q->where('i.is_active', (int)$filters['is_active']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('i.code', 'like', "%{$term}%")
                    ->orWhere('i.name', 'like', "%{$term}%")
                    ->orWhere('i.hsn_code', 'like', "%{$term}%");
            });
        }

        return $q;
    }
}
