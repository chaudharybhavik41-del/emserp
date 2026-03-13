<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionWipItem;
use Illuminate\Http\Request;

class ProductionV2WipController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view|production.qc.perform');
    }

    public function index(Request $request, Project $project)
    {
        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));

        $rows = ProductionWipItem::query()
            ->where('project_id', $project->id)
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($sub) use ($q) {
                    $sub->where('piece_no', 'like', '%' . $q . '%')
                        ->orWhere('lot_no', 'like', '%' . $q . '%')
                        ->orWhere('plate_number', 'like', '%' . $q . '%')
                        ->orWhere('heat_number', 'like', '%' . $q . '%')
                        ->orWhere('mtc_number', 'like', '%' . $q . '%');
                });
            })
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->with(['partDefinition', 'uom'])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('production_v2.wip.index', [
            'project' => $project,
            'rows' => $rows,
            'q' => $q,
            'status' => $status,
        ]);
    }
}
