<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEV-9: Project Cost Sheet Report Controller
 * 
 * Per Development Plan v1.2:
 * - Validate project-wise costing using data from Store Issues + RA Bills
 * - For each project:
 *   - Material & Consumables: from Store Issue postings (Dr Project WIP – Material/Consumables)
 *   - Subcontractor: from Subcontractor RA postings
 *   - (Internal labour/machine from DPR will be Phase 2)
 * - Simple cost sheet by project with totals and basic break-up
 */
class ProjectCostSheetController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.reports.view')->only(['index', 'show', 'export']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) Config::get('accounting.default_company_id', 1);
    }

    /**
     * Display project cost sheet summary (list of all projects with costs)
     */
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $asOfDate = $request->date('as_of_date') ?: now();

        // Get active projects in a schema-safe way:
        // - newer schemas may have projects.is_active
        // - legacy/current schema uses projects.status
        $projectsQuery = Project::query();
        if (Schema::hasColumn('projects', 'is_active')) {
            $projectsQuery->where('is_active', true);
        } elseif (Schema::hasColumn('projects', 'status')) {
            $projectsQuery->where('status', 'active');
        }

        $projects = $projectsQuery
            ->orderBy('name')
            ->get();

        // Get cost summary for each project
        $projectCosts = [];
        foreach ($projects as $project) {
            $costs = $this->getProjectCostSummary($project->id, $companyId, $asOfDate);
            $projectCosts[$project->id] = [
                'project'     => $project,
                'costs'       => $costs,
                'total_cost'  => array_sum($costs),
            ];
        }

        // Sort by total cost descending
        uasort($projectCosts, fn($a, $b) => $b['total_cost'] <=> $a['total_cost']);

        // Grand totals
        $grandTotals = [
            'material'      => 0,
            'consumables'   => 0,
            'subcontractor' => 0,
            'other_direct'  => 0,
            'total'         => 0,
        ];

        foreach ($projectCosts as $data) {
            $grandTotals['material']      += $data['costs']['material'] ?? 0;
            $grandTotals['consumables']   += $data['costs']['consumables'] ?? 0;
            $grandTotals['subcontractor'] += $data['costs']['subcontractor'] ?? 0;
            $grandTotals['other_direct']  += $data['costs']['other_direct'] ?? 0;
            $grandTotals['total']         += $data['total_cost'];
        }

        return view('accounting.reports.project_cost_sheet_index', [
            'companyId'    => $companyId,
            'asOfDate'     => $asOfDate,
            'projectCosts' => $projectCosts,
            'grandTotals'  => $grandTotals,
        ]);
    }

    /**
     * Display detailed cost sheet for a single project
     */
    public function show(Request $request, Project $project)
    {
        $companyId = $this->defaultCompanyId();
        $asOfDate = $request->date('as_of_date') ?: now();
        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to') ?: $asOfDate;

        // Get detailed cost breakdown
        $costDetails = $this->getDetailedProjectCosts($project->id, $companyId, $dateFrom, $dateTo);

        // Get cost summary
        $costSummary = $this->getProjectCostSummary($project->id, $companyId, $dateTo);

        // Get Store Issue transactions
        $storeIssueVouchers = $this->getStoreIssueVouchers($project->id, $companyId, $dateFrom, $dateTo);

        // Get Subcontractor RA vouchers
        $subcontractorVouchers = $this->getSubcontractorVouchers($project->id, $companyId, $dateFrom, $dateTo);

        // Get Purchase vouchers linked to project
        $purchaseVouchers = $this->getPurchaseVouchers($project->id, $companyId, $dateFrom, $dateTo);

        // Monthly breakdown
        $monthlyBreakdown = $this->getMonthlyBreakdown($project->id, $companyId, $dateFrom, $dateTo);

        return view('accounting.reports.project_cost_sheet_detail', [
            'project'              => $project,
            'companyId'            => $companyId,
            'asOfDate'             => $dateTo,
            'dateFrom'             => $dateFrom,
            'dateTo'               => $dateTo,
            'costSummary'          => $costSummary,
            'costDetails'          => $costDetails,
            'storeIssueVouchers'   => $storeIssueVouchers,
            'subcontractorVouchers'=> $subcontractorVouchers,
            'purchaseVouchers'     => $purchaseVouchers,
            'monthlyBreakdown'     => $monthlyBreakdown,
            'totalCost'            => array_sum($costSummary),
        ]);
    }

    /**
     * Export project cost sheet to Excel
     */
    public function export(Request $request, Project $project)
    {
        $companyId = $this->defaultCompanyId();
        $dateTo = $request->date('date_to') ?: now();
        $dateFrom = $request->date('date_from');

        $costDetails = $this->getDetailedProjectCosts($project->id, $companyId, $dateFrom, $dateTo);
        $costSummary = $this->getProjectCostSummary($project->id, $companyId, $dateTo);

        // Generate CSV export (can be enhanced with Laravel Excel package)
        $filename = 'project_cost_sheet_' . $project->code . '_' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($project, $costSummary, $costDetails) {
            $file = fopen('php://output', 'w');

            // Header
            fputcsv($file, ['Project Cost Sheet']);
            fputcsv($file, ['Project:', $project->name . ' (' . $project->code . ')']);
            fputcsv($file, ['Generated:', now()->format('d-M-Y H:i')]);
            fputcsv($file, []);

            // Summary
            fputcsv($file, ['COST SUMMARY']);
            fputcsv($file, ['Category', 'Amount (₹)']);
            fputcsv($file, ['Material', number_format($costSummary['material'] ?? 0, 2)]);
            fputcsv($file, ['Consumables', number_format($costSummary['consumables'] ?? 0, 2)]);
            fputcsv($file, ['Subcontractor', number_format($costSummary['subcontractor'] ?? 0, 2)]);
            fputcsv($file, ['Other Direct Costs', number_format($costSummary['other_direct'] ?? 0, 2)]);
            fputcsv($file, ['TOTAL', number_format(array_sum($costSummary), 2)]);
            fputcsv($file, []);

            // Details
            fputcsv($file, ['COST DETAILS']);
            fputcsv($file, ['Date', 'Voucher No', 'Type', 'Description', 'Account', 'Amount (₹)']);

            foreach ($costDetails as $detail) {
                fputcsv($file, [
                    $detail['voucher_date'],
                    $detail['voucher_no'],
                    $detail['voucher_type'],
                    $detail['description'],
                    $detail['account_name'],
                    number_format($detail['amount'], 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get project cost summary by category
     * 
     * @return array{material: float, consumables: float, subcontractor: float, other_direct: float}
     */
    protected function getProjectCostSummary(int $projectId, int $companyId, $asOfDate): array
    {
        // Get configured account codes for each category
        $materialAccountCodes = Config::get('accounting.project_costing.material_account_codes', ['WIP-MATERIAL', 'INV-RM']);
        $consumableAccountCodes = Config::get('accounting.project_costing.consumable_account_codes', ['WIP-CONSUMABLES', 'CONSUMABLE-EXP']);
        $subcontractorAccountCodes = Config::get('accounting.project_costing.subcontractor_account_codes', ['WIP-SUBCON']);
        $otherDirectAccountCodes = Config::get('accounting.project_costing.other_direct_account_codes', ['WIP-OTHER']);

        $costs = [
            'material'      => 0,
            'consumables'   => 0,
            'subcontractor' => 0,
            'other_direct'  => 0,
        ];

        // Query voucher lines for this project
        $query = VoucherLine::query()
            ->join('vouchers as v', 'v.id', '=', 'voucher_lines.voucher_id')
            ->join('accounts as a', 'a.id', '=', 'voucher_lines.account_id')
            ->leftJoin('cost_centers as cc', 'cc.id', '=', 'voucher_lines.cost_center_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->where(function ($q) use ($projectId) {
                $q->where('v.project_id', $projectId)
                  ->orWhere('cc.project_id', $projectId);
            })
            ->whereDate('v.voucher_date', '<=', $asOfDate);

        // Material costs
        $costs['material'] = (clone $query)
            ->whereIn('a.code', $materialAccountCodes)
            ->sum(DB::raw('voucher_lines.debit - voucher_lines.credit'));

        // Consumable costs
        $costs['consumables'] = (clone $query)
            ->whereIn('a.code', $consumableAccountCodes)
            ->sum(DB::raw('voucher_lines.debit - voucher_lines.credit'));

        // Subcontractor costs
        $costs['subcontractor'] = (clone $query)
            ->whereIn('a.code', $subcontractorAccountCodes)
            ->sum(DB::raw('voucher_lines.debit - voucher_lines.credit'));

        // Other direct costs
        $costs['other_direct'] = (clone $query)
            ->whereIn('a.code', $otherDirectAccountCodes)
            ->sum(DB::raw('voucher_lines.debit - voucher_lines.credit'));

        // Round all values
        foreach ($costs as $key => $value) {
            $costs[$key] = round((float) $value, 2);
        }

        return $costs;
    }

    /**
     * Get detailed project cost transactions
     */
    protected function getDetailedProjectCosts(int $projectId, int $companyId, $dateFrom, $dateTo): array
    {
        $query = VoucherLine::query()
            ->select([
                'voucher_lines.*',
                'v.voucher_no',
                'v.voucher_date',
                'v.voucher_type',
                'v.narration',
                'a.name as account_name',
                'a.code as account_code',
            ])
            ->join('vouchers as v', 'v.id', '=', 'voucher_lines.voucher_id')
            ->join('accounts as a', 'a.id', '=', 'voucher_lines.account_id')
            ->leftJoin('cost_centers as cc', 'cc.id', '=', 'voucher_lines.cost_center_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->where(function ($q) use ($projectId) {
                $q->where('v.project_id', $projectId)
                  ->orWhere('cc.project_id', $projectId);
            })
            ->where('voucher_lines.debit', '>', 0) // Only debit entries (costs)
            ->orderBy('v.voucher_date')
            ->orderBy('v.id');

        if ($dateFrom) {
            $query->whereDate('v.voucher_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('v.voucher_date', '<=', $dateTo);
        }

        $results = $query->get();

        return $results->map(function ($line) {
            return [
                'voucher_id'   => $line->voucher_id,
                'voucher_no'   => $line->voucher_no,
                'voucher_date' => $line->voucher_date,
                'voucher_type' => $line->voucher_type,
                'description'  => $line->description ?: $line->narration,
                'account_id'   => $line->account_id,
                'account_name' => $line->account_name,
                'account_code' => $line->account_code,
                'amount'       => (float) $line->debit,
            ];
        })->toArray();
    }

    /**
     * Get store issue vouchers for project
     */
    protected function getStoreIssueVouchers(int $projectId, int $companyId, $dateFrom, $dateTo): array
    {
        $query = Voucher::query()
            ->with('lines.account', 'lines.costCenter')
            ->where('company_id', $companyId)
            ->where('voucher_type', 'store_issue')
            ->where('status', 'posted')
            ->where(function ($q) use ($projectId) {
                $q->where('project_id', $projectId)
                  ->orWhereHas('lines.costCenter', function ($qc) use ($projectId) {
                      $qc->where('project_id', $projectId);
                  });
            })
            ->orderBy('voucher_date');

        if ($dateFrom) {
            $query->whereDate('voucher_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('voucher_date', '<=', $dateTo);
        }

        return $query->get()->toArray();
    }

    /**
     * Get subcontractor RA vouchers for project
     */
    protected function getSubcontractorVouchers(int $projectId, int $companyId, $dateFrom, $dateTo): array
    {
        $query = Voucher::query()
            ->with('lines.account', 'lines.costCenter')
            ->where('company_id', $companyId)
            ->where('voucher_type', 'subcontractor_ra')
            ->where('status', 'posted')
            ->where(function ($q) use ($projectId) {
                $q->where('project_id', $projectId)
                  ->orWhereHas('lines.costCenter', function ($qc) use ($projectId) {
                      $qc->where('project_id', $projectId);
                  });
            })
            ->orderBy('voucher_date');

        if ($dateFrom) {
            $query->whereDate('voucher_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('voucher_date', '<=', $dateTo);
        }

        return $query->get()->toArray();
    }

    /**
     * Get purchase vouchers linked to project
     */
    protected function getPurchaseVouchers(int $projectId, int $companyId, $dateFrom, $dateTo): array
    {
        $query = Voucher::query()
            ->with('lines.account', 'lines.costCenter')
            ->where('company_id', $companyId)
            ->where('voucher_type', 'purchase')
            ->where('status', 'posted')
            ->where(function ($q) use ($projectId) {
                $q->where('project_id', $projectId)
                  ->orWhereHas('lines.costCenter', function ($qc) use ($projectId) {
                      $qc->where('project_id', $projectId);
                  });
            })
            ->orderBy('voucher_date');

        if ($dateFrom) {
            $query->whereDate('voucher_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('voucher_date', '<=', $dateTo);
        }

        return $query->get()->toArray();
    }

    /**
     * Get monthly cost breakdown for project
     */
    protected function getMonthlyBreakdown(int $projectId, int $companyId, $dateFrom, $dateTo): array
    {
        $query = VoucherLine::query()
            ->select([
                DB::raw("DATE_FORMAT(v.voucher_date, '%Y-%m') as month"),
                DB::raw('SUM(voucher_lines.debit) as total_debit'),
            ])
            ->join('vouchers as v', 'v.id', '=', 'voucher_lines.voucher_id')
            ->leftJoin('cost_centers as cc', 'cc.id', '=', 'voucher_lines.cost_center_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->where(function ($q) use ($projectId) {
                $q->where('v.project_id', $projectId)
                  ->orWhere('cc.project_id', $projectId);
            })
            ->where('voucher_lines.debit', '>', 0)
            ->groupBy(DB::raw("DATE_FORMAT(v.voucher_date, '%Y-%m')"))
            ->orderBy('month');

        if ($dateFrom) {
            $query->whereDate('v.voucher_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('v.voucher_date', '<=', $dateTo);
        }

        return $query->get()->map(function ($row) {
            return [
                'month'  => $row->month,
                'amount' => round((float) $row->total_debit, 2),
            ];
        })->toArray();
    }
}


