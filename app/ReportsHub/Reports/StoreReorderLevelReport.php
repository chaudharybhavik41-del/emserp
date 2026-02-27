<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreReorderLevelReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'stores-reorder-levels';
    }

    public function name(): string
    {
        return 'Reorder Level / Low Stock';
    }

    public function module(): string
    {
        return 'Stores';
    }

    public function description(): ?string
    {
        return 'Reorder levels with current stock comparison (supports “Only Low Stock”).';
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable','integer','exists:projects,id'],
            'item_id' => ['nullable','integer','exists:items,id'],
            'only_low' => ['nullable','in:1,0'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);
        $items = DB::table('items')->orderBy('name')->limit(800)->get(['id','code','name']);

        return [
            [
                'name'=>'project_id','label'=>'Project','type'=>'select','col'=>4,
                'options'=>collect($projects)->map(fn($p)=>['value'=>$p->id,'label'=>trim(($p->code?$p->code.' - ':'').$p->name)])->all(),
            ],
            [
                'name'=>'item_id','label'=>'Item','type'=>'select','col'=>5,
                'options'=>collect($items)->map(fn($i)=>['value'=>$i->id,'label'=>trim(($i->code?$i->code.' - ':'').$i->name)])->all(),
            ],
            [
                'name'=>'only_low','label'=>'Only Low','type'=>'select','col'=>2,
                'options'=>[
                    ['value'=>'1','label'=>'Yes'],
                    ['value'=>'0','label'=>'No'],
                ],
            ],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'Item / Code'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Item', 'value'=>fn($r)=>trim(($r->item_code?$r->item_code.' - ':'').$r->item_name), 'w'=>'34%'],
            ['label'=>'Project', 'value'=>fn($r)=>$r->project_id ? trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')) : 'ALL', 'w'=>'22%'],
            [
                'label'=>'Reorder Level','align'=>'right','w'=>'12%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->min_qty??0):number_format((float)($r->min_qty??0),3),
            ],
            [
                'label'=>'Current Qty','align'=>'right','w'=>'12%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->current_qty??0):number_format((float)($r->current_qty??0),3),
            ],
            [
                'label'=>'Shortage','align'=>'right','w'=>'12%',
                'value'=>function($r,$forExport){
                    $min = (float)($r->min_qty ?? 0);
                    $cur = (float)($r->current_qty ?? 0);
                    $short = max(0, $min - $cur);
                    return $forExport ? $short : number_format($short,3);
                },
            ],
            ['label'=>'Brand','value'=>'brand', 'w'=>'10%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'item_name','direction'=>'asc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('store_reorder_levels as rl')
            ->leftJoin('items as i','i.id','=','rl.item_id')
            ->leftJoin('projects as p','p.id','=','rl.project_id')
            ->select([
                'rl.id',
                'rl.project_id',
                'rl.item_id',
                'rl.brand',
                'rl.min_qty',
                'i.code as item_code',
                'i.name as item_name',
                'p.code as project_code',
                'p.name as project_name',
                DB::raw('(select COALESCE(SUM(CASE WHEN u.category=\'weight\' THEN s.weight_kg_available ELSE s.qty_pcs_available END),0) from store_stock_items s join items i2 on i2.id = s.item_id join uoms u on u.id = i2.uom_id where s.item_id = rl.item_id and (rl.project_id is null or s.project_id = rl.project_id)) as current_qty'),
            ]);

        if (!empty($filters['project_id'])) {
            $q->where('rl.project_id', $filters['project_id']);
        }
        if (!empty($filters['item_id'])) {
            $q->where('rl.item_id', $filters['item_id']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function($sub) use($term){
                $sub->where('i.name','like',"%{$term}%")
                    ->orWhere('i.code','like',"%{$term}%");
            });
        }
        if (isset($filters['only_low']) && $filters['only_low'] === '1') {
            // min_qty > current_qty
            $q->whereRaw('rl.min_qty > (select COALESCE(SUM(CASE WHEN u.category=\'weight\' THEN s.weight_kg_available ELSE s.qty_pcs_available END),0) from store_stock_items s join items i2 on i2.id = s.item_id join uoms u on u.id = i2.uom_id where s.item_id = rl.item_id and (rl.project_id is null or s.project_id = rl.project_id))');
        }

        return $q;
    }
}
