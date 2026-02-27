<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\StoreStockItem;
use App\Models\Production\ProductionAssembly;
use App\Models\Production\ProductionPiece;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * WP-12: Traceability Search (Project scoped)
 *
 * Search by plate_number / heat_number / mtc_number / piece_number / assembly_mark
 * and show lineage: Stock -> Pieces -> Assemblies -> DPR history.
 */
class ProductionTraceabilitySearchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.traceability.view')->only(['index']);
    }

    public function index(Request $request, Project $project): View
    {
        $q = trim((string) $request->get('q', ''));

        $selected = [
            'stock_id' => $request->integer('stock_id'),
            'piece_id' => $request->integer('piece_id'),
            'assembly_id' => $request->integer('assembly_id'),
        ];

        // -------------------------------
        // Search candidates
        // -------------------------------
        $stockMatches = collect();
        $pieceMatches = collect();
        $assemblyMatches = collect();

        if ($q !== '') {
            $stockMatches = $this->searchStock($project->id, $q);
            $pieceMatches = $this->searchPieces($project->id, $q);
            $assemblyMatches = $this->searchAssemblies($project->id, $q);
        }

        // Auto-pick selection if not explicitly chosen
        if (! $selected['stock_id'] && ! $selected['piece_id'] && ! $selected['assembly_id']) {
            if ($stockMatches->isNotEmpty()) {
                $selected['stock_id'] = (int) $stockMatches->first()->id;
            } elseif ($pieceMatches->isNotEmpty()) {
                $selected['piece_id'] = (int) $pieceMatches->first()->id;
            } elseif ($assemblyMatches->isNotEmpty()) {
                $selected['assembly_id'] = (int) $assemblyMatches->first()->id;
            }
        }

        // -------------------------------
        // Build details
        // -------------------------------
        $detail = [
            'mode' => null,
            'stock' => null,
            'piece' => null,
            'assembly' => null,
            'pieces' => collect(),
            'remnants' => collect(),
            'assemblies' => collect(),
            'dpr_timeline' => collect(),
        ];

        if ($selected['piece_id']) {
            $detail['mode'] = 'piece';
            $detail['piece'] = ProductionPiece::query()
                ->with('motherStock')
                ->where('project_id', $project->id)
                ->find($selected['piece_id']);

            if ($detail['piece']) {
                $selected['stock_id'] = $detail['piece']->mother_stock_item_id ? (int) $detail['piece']->mother_stock_item_id : null;
            }
        }

        if ($selected['assembly_id']) {
            $detail['mode'] = 'assembly';
            $detail['assembly'] = ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->find($selected['assembly_id']);
        }

        if ($selected['stock_id']) {
            $detail['mode'] = $detail['mode'] ?: 'stock';
            $detail['stock'] = StoreStockItem::query()
                ->with(['item:id,code,name', 'project:id,code,name'])
                ->where('project_id', $project->id)
                ->where('id', $selected['stock_id'])
                ->first();
        }

        // Pieces derived from this stock (or selected piece)
        if ($detail['stock']) {
            $detail['pieces'] = $this->piecesFromStock($project->id, (int) $detail['stock']->id);
            $detail['remnants'] = $this->remnantsForStock((int) $detail['stock']->id);
            $detail['assemblies'] = $this->assembliesFromPieces($project->id, $detail['pieces']->pluck('id')->all());
            $detail['dpr_timeline'] = $this->timelineForStock($project->id, (int) $detail['stock']->id, $detail['pieces']->pluck('id')->all(), $detail['assemblies']->pluck('id')->all());
        } elseif ($detail['assembly']) {
            // If only assembly selected
            $detail['pieces'] = $this->piecesForAssembly($project->id, (int) $detail['assembly']->id);
            $detail['assemblies'] = collect([$detail['assembly']]);
            $detail['dpr_timeline'] = $this->timelineForAssembly($project->id, (int) $detail['assembly']->id);
        }

        return view('projects.production_traceability.index', [
            'project' => $project,
            'q' => $q,
            'stockMatches' => $stockMatches,
            'pieceMatches' => $pieceMatches,
            'assemblyMatches' => $assemblyMatches,
            'selected' => $selected,
            'detail' => $detail,
        ]);
    }

    // ------------------------------------------------------------
    // Search helpers
    // ------------------------------------------------------------

    protected function searchStock(int $projectId, string $q)
    {
        $q = trim($q);

        // Escape LIKE wildcards (% and _) so a literal search like `PZ36%` doesn't match everything.
        $escaped = addcslashes($q, "%_\\");
        $qLike = '%' . $escaped . '%';
        $id = ctype_digit($q) ? (int) $q : null;

        return StoreStockItem::query()
            ->where('project_id', $projectId)
            ->where(function ($w) use ($qLike, $id) {
                $w->where('plate_number', 'like', $qLike)
                  ->orWhere('heat_number', 'like', $qLike)
                  ->orWhere('mtc_number', 'like', $qLike)
                  ->orWhere('source_reference', 'like', $qLike);

                if ($id !== null) {
                    $w->orWhere('id', $id);
                }
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    protected function searchPieces(int $projectId, string $q)
    {
        $q = trim($q);

        // Escape LIKE wildcards (% and _) so a literal search doesn't explode result sets.
        $escaped = addcslashes($q, "%_\\");
        $qLike = '%' . $escaped . '%';
        $id = ctype_digit($q) ? (int) $q : null;

        return ProductionPiece::query()
            ->where('project_id', $projectId)
            ->where(function ($w) use ($qLike, $id) {
                $w->where('piece_number', 'like', $qLike)
                  ->orWhere('piece_tag', 'like', $qLike)
                  ->orWhere('plate_number', 'like', $qLike)
                  ->orWhere('heat_number', 'like', $qLike)
                  ->orWhere('mtc_number', 'like', $qLike);

                if ($id !== null) {
                    $w->orWhere('id', $id);
                }
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    protected function searchAssemblies(int $projectId, string $q)
    {
        $q = trim($q);

        // Escape LIKE wildcards (% and _) so a literal search doesn't explode result sets.
        $escaped = addcslashes($q, "%_\\");
        $qLike = '%' . $escaped . '%';
        $id = ctype_digit($q) ? (int) $q : null;

        return ProductionAssembly::query()
            ->where('project_id', $projectId)
            ->where(function ($w) use ($qLike, $id) {
                $w->where('assembly_mark', 'like', $qLike)
                  ->orWhere('assembly_type', 'like', $qLike);

                if ($id !== null) {
                    $w->orWhere('id', $id);
                }
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    // ------------------------------------------------------------
    // Lineage helpers
    // ------------------------------------------------------------

    protected function piecesFromStock(int $projectId, int $stockId)
    {
        return ProductionPiece::query()
            ->where('project_id', $projectId)
            ->where('mother_stock_item_id', $stockId)
            ->orderBy('piece_number')
            ->get();
    }

    protected function remnantsForStock(int $stockId)
    {
        // mother OR remnant stock references
        return DB::table('production_remnants as r')
            ->leftJoin('production_dpr_lines as dl', 'dl.id', '=', 'r.production_dpr_line_id')
            ->leftJoin('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
            ->leftJoin('production_activities as a', 'a.id', '=', 'd.production_activity_id')
            ->where(function ($w) use ($stockId) {
                $w->where('r.mother_stock_item_id', $stockId)
                  ->orWhere('r.remnant_stock_item_id', $stockId);
            })
            ->orderByDesc('r.id')
            ->select([
                'r.*',
                'd.id as dpr_id',
                'd.dpr_date as dpr_date',
                'a.name as activity_name',
            ])
            ->get();
    }

    protected function assembliesFromPieces(int $projectId, array $pieceIds)
    {
        if (empty($pieceIds)) {
            return collect();
        }

        return DB::table('production_assembly_components as ac')
            ->join('production_assemblies as asm', 'asm.id', '=', 'ac.production_assembly_id')
            ->where('asm.project_id', $projectId)
            ->whereIn('ac.production_piece_id', $pieceIds)
            ->select([
                'asm.id',
                'asm.assembly_mark',
                'asm.assembly_type',
                'asm.status',
                'asm.weight_kg',
                'asm.production_dpr_line_id',
                'asm.created_at',
            ])
            ->distinct()
            ->orderBy('asm.assembly_mark')
            ->get();
    }

    protected function piecesForAssembly(int $projectId, int $assemblyId)
    {
        return DB::table('production_assembly_components as ac')
            ->join('production_pieces as p', 'p.id', '=', 'ac.production_piece_id')
            ->where('p.project_id', $projectId)
            ->where('ac.production_assembly_id', $assemblyId)
            ->orderBy('p.piece_number')
            ->select('p.*')
            ->get();
    }

    protected function timelineForStock(int $projectId, int $stockId, array $pieceIds, array $assemblyIds)
    {
        $events = collect();

        // Cutting DPR lines that created pieces from this stock
        if (! empty($pieceIds)) {
            $cutEvents = DB::table('production_pieces as p')
                ->join('production_dpr_lines as dl', 'dl.id', '=', 'p.production_dpr_line_id')
                ->join('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
                ->join('production_activities as a', 'a.id', '=', 'd.production_activity_id')
                ->where('p.project_id', $projectId)
                ->where('p.mother_stock_item_id', $stockId)
                ->select([
                    DB::raw("'piece_created' as event_type"),
                    'd.dpr_date',
                    'd.id as dpr_id',
                    'a.name as activity_name',
                    'dl.id as dpr_line_id',
                    DB::raw('NULL as ref_no'),
                    'p.piece_number as ref_code',
                ])
                ->orderBy('d.dpr_date')
                ->get();

            $events = $events->concat($cutEvents);
        }

        // Remnant events
        $remEvents = DB::table('production_remnants as r')
            ->leftJoin('production_dpr_lines as dl', 'dl.id', '=', 'r.production_dpr_line_id')
            ->leftJoin('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
            ->leftJoin('production_activities as a', 'a.id', '=', 'd.production_activity_id')
            ->where('r.mother_stock_item_id', $stockId)
            ->select([
                DB::raw("'remnant' as event_type"),
                'd.dpr_date',
                'd.id as dpr_id',
                DB::raw("COALESCE(a.name,'') as activity_name"),
                'dl.id as dpr_line_id',
                'r.id as ref_no',
                DB::raw("CONCAT('Remnant#', r.id) as ref_code"),
            ])
            ->orderBy('d.dpr_date')
            ->get();

        $events = $events->concat($remEvents);

        // Assembly creation + subsequent assembly DPRs
        if (! empty($assemblyIds)) {
            $asmCreate = DB::table('production_assemblies as asm')
                ->leftJoin('production_dpr_lines as dl', 'dl.id', '=', 'asm.production_dpr_line_id')
                ->leftJoin('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
                ->leftJoin('production_activities as a', 'a.id', '=', 'd.production_activity_id')
                ->where('asm.project_id', $projectId)
                ->whereIn('asm.id', $assemblyIds)
                ->select([
                    DB::raw("'assembly_created' as event_type"),
                    'd.dpr_date',
                    'd.id as dpr_id',
                    DB::raw("COALESCE(a.name,'Fitup') as activity_name"),
                    'dl.id as dpr_line_id',
                    'asm.id as ref_no',
                    'asm.assembly_mark as ref_code',
                ])
                ->get();

            $events = $events->concat($asmCreate);

            $asmActs = DB::table('production_dpr_lines as dl')
                ->join('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
                ->join('production_activities as a', 'a.id', '=', 'd.production_activity_id')
                ->whereIn('dl.production_assembly_id', $assemblyIds)
                ->where('d.status', '!=', 'cancelled')
                ->select([
                    DB::raw("'assembly_activity' as event_type"),
                    'd.dpr_date',
                    'd.id as dpr_id',
                    'a.name as activity_name',
                    'dl.id as dpr_line_id',
                    'dl.production_assembly_id as ref_no',
                    DB::raw("CONCAT('Assembly#', dl.production_assembly_id) as ref_code"),
                ])
                ->get();

            $events = $events->concat($asmActs);
        }

        return $events
            ->filter(fn ($e) => $e->dpr_date !== null)
            ->sortBy(fn ($e) => (string) $e->dpr_date . '-' . (string) $e->dpr_id)
            ->values();
    }

    protected function timelineForAssembly(int $projectId, int $assemblyId)
    {
        return DB::table('production_dpr_lines as dl')
            ->join('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
            ->join('production_activities as a', 'a.id', '=', 'd.production_activity_id')
            ->where('d.status', '!=', 'cancelled')
            ->where(function ($w) use ($assemblyId) {
                $w->where('dl.production_assembly_id', $assemblyId)
                  ->orWhereExists(function ($q) use ($assemblyId) {
                      $q->select(DB::raw(1))
                        ->from('production_assemblies as asm')
                        ->whereColumn('asm.production_dpr_line_id', 'dl.id')
                        ->where('asm.id', $assemblyId);
                  });
            })
            ->select([
                'd.dpr_date',
                'd.id as dpr_id',
                'a.name as activity_name',
                'dl.id as dpr_line_id',
                'dl.qty',
                'dl.remarks',
            ])
            ->orderBy('d.dpr_date')
            ->orderBy('d.id')
            ->get();
    }
}
