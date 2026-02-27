<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\MoneyHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 5e (Hotfix)
 * GST Sales SAC/HSN Summary (Client RA Bills)
 *
 * NOTE:
 * client_ra_bill_lines currently does NOT store per-line GST amounts.
 * We allocate bill-level net + gst amounts proportionally to line current_amount.
 */
class GstHsnSalesSummaryReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.reports.view')
            ->only(['index', 'export']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    public function index(Request $request)
    {
        $companyId = (int) ($request->integer('company_id') ?: $this->defaultCompanyId());

        $fromDate = $request->date('from_date') ?: now()->startOfMonth();
        $toDate   = $request->date('to_date') ?: now();

        $fromDate = Carbon::parse($fromDate)->startOfDay();
        $toDate   = Carbon::parse($toDate)->endOfDay();

        $projectId = $request->integer('project_id') ?: null;

        $status = trim((string) $request->string('status', 'posted'));

        $projects = Project::query()->orderBy('name')->get();

        $missingTables = [];
        if (! Schema::hasTable('client_ra_bills')) {
            $missingTables[] = 'client_ra_bills';
        }
        if (! Schema::hasTable('client_ra_bill_lines')) {
            $missingTables[] = 'client_ra_bill_lines';
        }

        if (! empty($missingTables)) {
            return view('accounting.reports.gst_hsn_sales_summary', [
                'companyId'      => $companyId,
                'fromDate'       => $fromDate,
                'toDate'         => $toDate,
                'projectId'      => $projectId,
                'status'         => $status,
                'projects'       => $projects,
                'missingTables'  => $missingTables,
                'rows'           => collect(),
                'totals'         => $this->emptyTotals(),
            ]);
        }

        $rows = $this->buildRows($companyId, $fromDate, $toDate, $projectId, $status);
        $totals = $this->computeTotals($rows);

        return view('accounting.reports.gst_hsn_sales_summary', [
            'companyId'     => $companyId,
            'fromDate'      => $fromDate,
            'toDate'        => $toDate,
            'projectId'     => $projectId,
            'status'        => $status,
            'projects'      => $projects,
            'missingTables' => [],
            'rows'          => $rows,
            'totals'        => $totals,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $companyId = (int) ($request->integer('company_id') ?: $this->defaultCompanyId());

        $fromDate = $request->date('from_date') ?: now()->startOfMonth();
        $toDate   = $request->date('to_date') ?: now();

        $fromDate = Carbon::parse($fromDate)->startOfDay();
        $toDate   = Carbon::parse($toDate)->endOfDay();

        $projectId = $request->integer('project_id') ?: null;

        $status = trim((string) $request->string('status', 'posted'));

        $missingTables = [];
        if (! Schema::hasTable('client_ra_bills')) {
            $missingTables[] = 'client_ra_bills';
        }
        if (! Schema::hasTable('client_ra_bill_lines')) {
            $missingTables[] = 'client_ra_bill_lines';
        }

        $rows = collect();

        if (empty($missingTables)) {
            $rows = $this->buildRows($companyId, $fromDate, $toDate, $projectId, $status);
        }

        $fileName = 'gst_sales_sac_hsn_summary_' . $fromDate->format('Ymd') . '_' . $toDate->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows, $missingTables) {
            $out = fopen('php://output', 'w');

            if (! empty($missingTables)) {
                fputcsv($out, ['ERROR: Missing required tables: ' . implode(', ', $missingTables)]);
                fclose($out);
                return;
            }

            fputcsv($out, [
                'SAC/HSN',
                'GST Rate (%)',
                'Taxable Value',
                'CGST',
                'SGST',
                'IGST',
                'Total GST',
                'Gross Total',
            ]);

            foreach ($rows as $r) {
                $taxablePaise = MoneyHelper::toPaise($r->taxable ?? 0);
                $cgstPaise    = MoneyHelper::toPaise($r->cgst ?? 0);
                $sgstPaise    = MoneyHelper::toPaise($r->sgst ?? 0);
                $igstPaise    = MoneyHelper::toPaise($r->igst ?? 0);

                fputcsv($out, [
                    $r->hsn_sac,
                    $r->gst_rate,
                    MoneyHelper::fromPaise($taxablePaise),
                    MoneyHelper::fromPaise($cgstPaise),
                    MoneyHelper::fromPaise($sgstPaise),
                    MoneyHelper::fromPaise($igstPaise),
                    MoneyHelper::fromPaise($cgstPaise + $sgstPaise + $igstPaise),
                    MoneyHelper::fromPaise($taxablePaise + $cgstPaise + $sgstPaise + $igstPaise),
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function buildRows(int $companyId, Carbon $fromDate, Carbon $toDate, ?int $projectId, string $status)
    {
        // Subquery: total line amount per bill (for weighting)
        $lineTotals = DB::table('client_ra_bill_lines')
            ->selectRaw('client_ra_bill_id, SUM(current_amount) as lines_total')
            ->groupBy('client_ra_bill_id');

        $query = DB::table('client_ra_bill_lines as l')
            ->join('client_ra_bills as b', 'b.id', '=', 'l.client_ra_bill_id')
            ->leftJoinSub($lineTotals, 'lt', function ($join) {
                $join->on('lt.client_ra_bill_id', '=', 'b.id');
            })
            ->where('b.company_id', $companyId)
            ->whereDate('b.bill_date', '>=', $fromDate->toDateString())
            ->whereDate('b.bill_date', '<=', $toDate->toDateString());

        if ($projectId) {
            $query->where('b.project_id', $projectId);
        }

        if (in_array($status, ['draft', 'submitted', 'approved', 'posted', 'cancelled'], true)) {
            $query->where('b.status', $status);
        }

        // Weight for allocating bill-level net + gst amounts across lines
        $weight = "(CASE WHEN lt.lines_total > 0 THEN (l.current_amount / lt.lines_total) ELSE 0 END)";

        return $query
            ->selectRaw("COALESCE(NULLIF(l.sac_hsn_code,''), 'NA') as hsn_sac")
            ->selectRaw('ROUND((b.cgst_rate + b.sgst_rate + b.igst_rate), 2) as gst_rate')
            ->selectRaw('SUM(b.net_amount * ' . $weight . ') as taxable')
            ->selectRaw('SUM(b.cgst_amount * ' . $weight . ') as cgst')
            ->selectRaw('SUM(b.sgst_amount * ' . $weight . ') as sgst')
            ->selectRaw('SUM(b.igst_amount * ' . $weight . ') as igst')
            ->groupBy(DB::raw("COALESCE(NULLIF(l.sac_hsn_code,''), 'NA')"), DB::raw('ROUND((b.cgst_rate + b.sgst_rate + b.igst_rate), 2)'))
            ->orderBy('hsn_sac')
            ->orderBy('gst_rate')
            ->get();
    }

    protected function emptyTotals(): array
    {
        return [
            'taxable'     => 0,
            'cgst'        => 0,
            'sgst'        => 0,
            'igst'        => 0,
            'gst_total'   => 0,
            'gross_total' => 0,
            'rows'        => 0,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int,object> $rows
     */
    protected function computeTotals($rows): array
    {
        $totals = $this->emptyTotals();
        $totals['rows'] = $rows->count();

        foreach ($rows as $r) {
            $totals['taxable'] += MoneyHelper::toPaise($r->taxable ?? 0);
            $totals['cgst']    += MoneyHelper::toPaise($r->cgst ?? 0);
            $totals['sgst']    += MoneyHelper::toPaise($r->sgst ?? 0);
            $totals['igst']    += MoneyHelper::toPaise($r->igst ?? 0);
        }

        $totals['gst_total']   = $totals['cgst'] + $totals['sgst'] + $totals['igst'];
        $totals['gross_total'] = $totals['taxable'] + $totals['gst_total'];

        return $totals;
    }
}
