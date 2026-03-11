<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MachineCostRegisterReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.reports.view')->only(['index', 'export']);
    }

    public function index(Request $request)
    {
        $report = $this->buildReportData($request, includeMasters: true);

        return view('accounting.reports.machine_cost_register', $report);
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $this->buildReportData($request, includeMasters: false);
        $fromDate = $report['fromDate'];
        $toDate = $report['toDate'];

        $fileName = 'machine_cost_register_' . $fromDate->format('Ymd') . '_' . $toDate->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Machine Cost Register']);
            fputcsv($out, ['From Date', $report['fromDate']->toDateString()]);
            fputcsv($out, ['To Date', $report['toDate']->toDateString()]);
            fputcsv($out, ['Machine ID', $report['machineId'] ?: 'ALL']);
            fputcsv($out, ['Project ID', $report['projectId'] ?: 'ALL']);
            fputcsv($out, ['Transactions', $report['grandTransactions']]);
            fputcsv($out, ['Total Qty', number_format((float) $report['grandQty'], 3, '.', '')]);
            fputcsv($out, ['Total Amount', number_format((float) $report['grandAmount'], 2, '.', '')]);
            fputcsv($out, []);

            fputcsv($out, ['Summary by Source']);
            fputcsv($out, ['Source', 'Transactions', 'Qty', 'Amount']);
            foreach ($report['summaryBySource'] as $row) {
                fputcsv($out, [
                    $row['label'],
                    (int) $row['transactions'],
                    number_format((float) $row['total_qty'], 3, '.', ''),
                    number_format((float) $row['total_amount'], 2, '.', ''),
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Monthly Summary']);
            fputcsv($out, ['Month', 'Fuel Amount', 'Spare Amount', 'Total Amount', 'Transactions']);
            foreach ($report['monthlySummary'] as $row) {
                fputcsv($out, [
                    $row['month'],
                    number_format((float) $row['fuel_amount'], 2, '.', ''),
                    number_format((float) $row['spare_amount'], 2, '.', ''),
                    number_format((float) $row['total_amount'], 2, '.', ''),
                    (int) $row['transactions'],
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Transaction Register']);
            fputcsv($out, [
                'Date',
                'Source',
                'Transaction No',
                'Machine Code',
                'Machine Name',
                'Project Code',
                'Project Name',
                'Qty',
                'Amount',
                'Accounting Status',
                'Remarks',
            ]);

            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    (string) ($row['txn_date'] ?? ''),
                    (string) ($row['source_label'] ?? ''),
                    (string) ($row['txn_no'] ?? ''),
                    (string) ($row['machine_code'] ?? ''),
                    (string) ($row['machine_name'] ?? ''),
                    (string) ($row['project_code'] ?? ''),
                    (string) ($row['project_name'] ?? ''),
                    number_format((float) ($row['qty'] ?? 0), 3, '.', ''),
                    number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                    (string) ($row['accounting_status'] ?? ''),
                    (string) ($row['remarks'] ?? ''),
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function buildReportData(Request $request, bool $includeMasters = true): array
    {
        $fromDate = $request->date('from_date') ?: now()->startOfMonth();
        $toDate = $request->date('to_date') ?: now();
        $machineId = $request->integer('machine_id') ?: null;
        $projectId = $request->integer('project_id') ?: null;

        $machines = $includeMasters
            ? Machine::query()->orderBy('code')->orderBy('name')->get(['id', 'code', 'name'])
            : collect();

        $projects = $includeMasters
            ? Project::query()->orderBy('code')->orderBy('name')->get(['id', 'code', 'name'])
            : collect();

        $hasFuelIssueTables = Schema::hasTable('fuel_issues');
        $hasSpareIssueTables = Schema::hasTable('store_issues')
            && Schema::hasTable('store_issue_lines')
            && Schema::hasColumn('store_issues', 'issue_purpose');

        $fuelRows = $this->fetchFuelRows($fromDate, $toDate, $machineId, $projectId);
        $spareRows = $this->fetchSpareRows($fromDate, $toDate, $machineId, $projectId);

        $rows = $fuelRows
            ->merge($spareRows)
            ->sortBy(function (array $row) {
                return sprintf(
                    '%s|%02d|%08d',
                    (string) ($row['txn_date'] ?? ''),
                    (int) ($row['source_sort'] ?? 99),
                    (int) ($row['source_id'] ?? 0)
                );
            })
            ->values();

        $summaryBySource = $rows
            ->groupBy('source')
            ->map(function (Collection $group, string $source) {
                $first = (array) ($group->first() ?? []);

                return [
                    'source' => $source,
                    'label' => (string) ($first['source_label'] ?? $source),
                    'transactions' => $group->count(),
                    'total_qty' => round((float) $group->sum('qty'), 3),
                    'total_amount' => round((float) $group->sum('amount'), 2),
                ];
            })
            ->sortBy('label')
            ->values();

        $monthlySummary = $rows
            ->groupBy(function (array $row) {
                return Carbon::parse((string) $row['txn_date'])->format('Y-m');
            })
            ->map(function (Collection $group, string $monthKey) {
                return [
                    'month' => Carbon::createFromFormat('Y-m', $monthKey)->format('M Y'),
                    'month_key' => $monthKey,
                    'fuel_amount' => round((float) $group->where('source', 'fuel_issue')->sum('amount'), 2),
                    'spare_amount' => round((float) $group->where('source', 'spare_issue')->sum('amount'), 2),
                    'total_amount' => round((float) $group->sum('amount'), 2),
                    'transactions' => $group->count(),
                ];
            })
            ->sortBy('month_key')
            ->values();

        return [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'machineId' => $machineId,
            'projectId' => $projectId,
            'machines' => $machines,
            'projects' => $projects,
            'rows' => $rows,
            'summaryBySource' => $summaryBySource,
            'monthlySummary' => $monthlySummary,
            'grandTransactions' => $rows->count(),
            'grandQty' => round((float) $rows->sum('qty'), 3),
            'grandAmount' => round((float) $rows->sum('amount'), 2),
            'hasFuelIssueTables' => $hasFuelIssueTables,
            'hasSpareIssueTables' => $hasSpareIssueTables,
        ];
    }

    protected function fetchFuelRows(Carbon $fromDate, Carbon $toDate, ?int $machineId, ?int $projectId): Collection
    {
        if (! Schema::hasTable('fuel_issues')) {
            return collect();
        }

        $query = DB::table('fuel_issues as fi')
            ->leftJoin('machines as m', 'm.id', '=', 'fi.machine_id')
            ->leftJoin('projects as p', 'p.id', '=', 'fi.project_id')
            ->where('fi.status', 'posted')
            ->whereIn('fi.accounting_status', ['posted', 'not_required'])
            ->whereDate('fi.issue_date', '>=', $fromDate->toDateString())
            ->whereDate('fi.issue_date', '<=', $toDate->toDateString())
            ->orderBy('fi.issue_date')
            ->orderBy('fi.id');

        if ($machineId) {
            $query->where('fi.machine_id', $machineId);
        }

        if ($projectId) {
            $query->where('fi.project_id', $projectId);
        }

        return $query
            ->get([
                'fi.id',
                'fi.issue_number',
                'fi.issue_date',
                'fi.machine_id',
                'fi.project_id',
                'fi.qty',
                'fi.amount',
                'fi.remarks',
                'fi.accounting_status',
                'm.code as machine_code',
                'm.name as machine_name',
                'p.code as project_code',
                'p.name as project_name',
            ])
            ->map(function ($row) {
                return [
                    'source' => 'fuel_issue',
                    'source_label' => 'Fuel Issue',
                    'source_sort' => 1,
                    'source_id' => (int) $row->id,
                    'txn_date' => (string) $row->issue_date,
                    'txn_no' => (string) ($row->issue_number ?: ('FUEL-' . $row->id)),
                    'machine_id' => (int) ($row->machine_id ?? 0) ?: null,
                    'machine_code' => (string) ($row->machine_code ?? ''),
                    'machine_name' => (string) ($row->machine_name ?? ''),
                    'project_id' => (int) ($row->project_id ?? 0) ?: null,
                    'project_code' => (string) ($row->project_code ?? ''),
                    'project_name' => (string) ($row->project_name ?? ''),
                    'qty' => round((float) ($row->qty ?? 0), 3),
                    'amount' => round((float) ($row->amount ?? 0), 2),
                    'accounting_status' => (string) ($row->accounting_status ?? ''),
                    'remarks' => (string) ($row->remarks ?? ''),
                ];
            })
            ->values();
    }

    protected function fetchSpareRows(Carbon $fromDate, Carbon $toDate, ?int $machineId, ?int $projectId): Collection
    {
        if (
            ! Schema::hasTable('store_issues')
            || ! Schema::hasTable('store_issue_lines')
            || ! Schema::hasTable('store_stock_items')
            || ! Schema::hasColumn('store_issues', 'issue_purpose')
        ) {
            return collect();
        }

        $issueQuery = DB::table('store_issues as si')
            ->leftJoin('machines as m', 'm.id', '=', 'si.machine_id')
            ->leftJoin('projects as p', 'p.id', '=', 'si.project_id')
            ->where('si.issue_purpose', 'machine_spare')
            ->where('si.status', 'posted')
            ->whereIn('si.accounting_status', ['posted', 'not_required'])
            ->whereDate('si.issue_date', '>=', $fromDate->toDateString())
            ->whereDate('si.issue_date', '<=', $toDate->toDateString())
            ->orderBy('si.issue_date')
            ->orderBy('si.id');

        if ($machineId) {
            $issueQuery->where('si.machine_id', $machineId);
        }

        if ($projectId) {
            $issueQuery->where('si.project_id', $projectId);
        }

        $issues = $issueQuery->get([
            'si.id',
            'si.issue_number',
            'si.issue_date',
            'si.machine_id',
            'si.project_id',
            'si.remarks',
            'si.accounting_status',
            'm.code as machine_code',
            'm.name as machine_name',
            'p.code as project_code',
            'p.name as project_name',
        ]);

        if ($issues->isEmpty()) {
            return collect();
        }

        $issueIds = $issues->pluck('id')->map(fn ($id) => (int) $id)->values();

        $lines = DB::table('store_issue_lines as sil')
            ->join('store_stock_items as ssi', 'ssi.id', '=', 'sil.store_stock_item_id')
            ->whereIn('sil.store_issue_id', $issueIds->all())
            ->get([
                'sil.store_issue_id',
                'sil.issued_qty_pcs',
                'sil.issued_weight_kg',
                'ssi.material_receipt_line_id',
                'ssi.opening_unit_rate',
            ]);

        $mrLineIds = $lines->pluck('material_receipt_line_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $purchaseLinesByMrLineId = [];
        $mrBaseQtyById = [];

        if ($mrLineIds->isNotEmpty() && Schema::hasTable('material_receipt_lines') && Schema::hasTable('purchase_bill_lines')) {
            $purchaseLinesByMrLineId = DB::table('purchase_bill_lines')
                ->join('purchase_bills as pb', 'pb.id', '=', 'purchase_bill_lines.purchase_bill_id')
                ->where('pb.status', 'posted')
                ->whereDate('pb.bill_date', '<=', $toDate->toDateString())
                ->whereIn('purchase_bill_lines.material_receipt_line_id', $mrLineIds->all())
                ->orderBy('pb.bill_date')
                ->orderBy('pb.id')
                ->get([
                    'purchase_bill_lines.material_receipt_line_id',
                    'purchase_bill_lines.basic_amount',
                    'pb.bill_date',
                ])
                ->groupBy('material_receipt_line_id');

            $mrBaseQtyById = DB::table('material_receipt_lines')
                ->whereIn('id', $mrLineIds->all())
                ->get(['id', 'received_weight_kg', 'qty_pcs'])
                ->mapWithKeys(function ($row) {
                    $weight = (float) ($row->received_weight_kg ?? 0);
                    $pcs = (float) ($row->qty_pcs ?? 0);
                    $baseQty = $weight > 0 ? $weight : $pcs;

                    return [(int) $row->id => $baseQty];
                })
                ->all();
        }

        $totalsByIssue = [];
        foreach ($lines as $line) {
            $issueId = (int) ($line->store_issue_id ?? 0);
            if ($issueId <= 0) {
                continue;
            }

            $qty = (float) ($line->issued_weight_kg ?? 0);
            if ($qty <= 0) {
                $qty = (float) ($line->issued_qty_pcs ?? 0);
            }

            if ($qty <= 0) {
                continue;
            }

            $rate = 0.0;
            $mrLineId = (int) ($line->material_receipt_line_id ?? 0);
            if ($mrLineId > 0) {
                $issueDate = $issues->firstWhere('id', $issueId)?->issue_date;
                $effectiveDate = $issueDate ? Carbon::parse((string) $issueDate)->toDateString() : null;
                $totalBasic = (float) collect($purchaseLinesByMrLineId[$mrLineId] ?? [])
                    ->filter(function ($purchaseLine) use ($effectiveDate) {
                        if (! $effectiveDate) {
                            return true;
                        }

                        return (string) $purchaseLine->bill_date <= $effectiveDate;
                    })
                    ->sum(fn ($purchaseLine) => (float) ($purchaseLine->basic_amount ?? 0));
                $baseQty = (float) ($mrBaseQtyById[$mrLineId] ?? 0);
                if ($totalBasic > 0 && $baseQty > 0) {
                    $rate = $totalBasic / $baseQty;
                }
            }

            if ($rate <= 0) {
                $rate = (float) ($line->opening_unit_rate ?? 0);
            }

            $amount = $rate > 0 ? round($rate * $qty, 2) : 0.0;

            if (! isset($totalsByIssue[$issueId])) {
                $totalsByIssue[$issueId] = ['qty' => 0.0, 'amount' => 0.0];
            }
            $totalsByIssue[$issueId]['qty'] += $qty;
            $totalsByIssue[$issueId]['amount'] += $amount;
        }

        return $issues->map(function ($issue) use ($totalsByIssue) {
            $issueId = (int) $issue->id;
            $totals = $totalsByIssue[$issueId] ?? ['qty' => 0.0, 'amount' => 0.0];

            return [
                'source' => 'spare_issue',
                'source_label' => 'Spare Issue',
                'source_sort' => 2,
                'source_id' => $issueId,
                'txn_date' => (string) $issue->issue_date,
                'txn_no' => (string) ($issue->issue_number ?: ('ISS-' . $issueId)),
                'machine_id' => (int) ($issue->machine_id ?? 0) ?: null,
                'machine_code' => (string) ($issue->machine_code ?? ''),
                'machine_name' => (string) ($issue->machine_name ?? ''),
                'project_id' => (int) ($issue->project_id ?? 0) ?: null,
                'project_code' => (string) ($issue->project_code ?? ''),
                'project_name' => (string) ($issue->project_name ?? ''),
                'qty' => round((float) $totals['qty'], 3),
                'amount' => round((float) $totals['amount'], 2),
                'accounting_status' => (string) ($issue->accounting_status ?? ''),
                'remarks' => (string) ($issue->remarks ?? ''),
            ];
        })->values();
    }
}
