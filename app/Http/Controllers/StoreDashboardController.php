<?php

namespace App\Http\Controllers;

use App\Models\GatePass;
use App\Models\MaterialReceipt;
use App\Models\StoreIssue;
use App\Models\StoreReturn;
use App\Models\StoreRequisition;
use App\Models\StoreStockAdjustment;
use App\Models\StoreStockItem;
use Illuminate\Contracts\View\View;

class StoreDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        // Anyone who can see store stock can see this dashboard
        $this->middleware('permission:store.stock.view');
    }

    public function index(): View
    {
        $today = now()->toDateString();

        $stats = [
            'grn_today'             => MaterialReceipt::whereDate('receipt_date', $today)->count(),
            'grn_qc_pending'        => MaterialReceipt::where('status', 'qc_pending')->count(),
            'requisitions_open'     => StoreRequisition::whereIn('status', ['requested', 'approved'])->count(),
            'issues_today'          => StoreIssue::whereDate('issue_date', $today)->count(),
            'returns_today'         => StoreReturn::whereDate('return_date', $today)->count(),
            'gatepasses_open'       => GatePass::whereIn('status', ['out', 'partially_returned'])->count(),
            'stock_items'           => StoreStockItem::count(),
            'stock_weight_kg'       => (float) StoreStockItem::sum('weight_kg_available'),
            'remnants_count'        => StoreStockItem::where('is_remnant', true)->count(),
            'adjustments_this_month' => StoreStockAdjustment::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        $recentGrns = MaterialReceipt::with(['project', 'supplier'])
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentRequisitions = StoreRequisition::with(['project', 'contractor'])
            ->latest('requisition_date')
            ->limit(5)
            ->get();

        $recentIssues = StoreIssue::with(['project', 'contractor'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentReturns = StoreReturn::with(['project', 'contractor'])
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $openGatePasses = GatePass::with(['project', 'contractor'])
            ->whereIn('status', ['out', 'partially_returned'])
            ->orderByDesc('gatepass_date')
            ->limit(5)
            ->get();

        return view('store.dashboard', compact(
            'stats',
            'recentGrns',
            'recentRequisitions',
            'recentIssues',
            'recentReturns',
            'openGatePasses'
        ));
    }
}
