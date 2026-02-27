<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Voucher;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DayBookReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.reports.view')->only(['index']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    /**
     * Day Book: list of vouchers in date range.
     */
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $toDate   = $request->date('to_date') ?: now();
        $fromDate = $request->date('from_date') ?: $toDate->copy()->startOfMonth();

        $type      = $request->get('voucher_type');
        $projectId = $request->integer('project_id') ?: null;
        $export    = $request->get('export');

        $projects = Project::orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $query = Voucher::with(['lines.account', 'project'])
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereDate('voucher_date', '>=', $fromDate->toDateString())
            ->whereDate('voucher_date', '<=', $toDate->toDateString());

        if ($type) {
            $query->where('voucher_type', $type);
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $vouchers = $query
            ->orderBy('voucher_date')
            ->orderBy('id')
            ->get();

        $voucherTypes = Voucher::where('company_id', $companyId)
            ->select('voucher_type')
            ->distinct()
            ->orderBy('voucher_type')
            ->pluck('voucher_type')
            ->toArray();

        // Quick validation: count of unbalanced vouchers within the same filters
        $unbalancedCountQuery = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereDate('v.voucher_date', '>=', $fromDate->toDateString())
            ->whereDate('v.voucher_date', '<=', $toDate->toDateString());

        if ($type) {
            $unbalancedCountQuery->where('v.voucher_type', $type);
        }

        if ($projectId) {
            $unbalancedCountQuery->where('v.project_id', $projectId);
        }

        $unbalancedCount = (int) $unbalancedCountQuery
            ->select('v.id')
            ->groupBy('v.id')
            ->havingRaw('ABS(COALESCE(SUM(vl.debit),0) - COALESCE(SUM(vl.credit),0)) >= 0.01')
            ->get()
            ->count();

        if ($export === 'csv') {
            return $this->exportCsv(
                companyId: $companyId,
                fromDate: $fromDate,
                toDate: $toDate,
                vouchers: $vouchers,
                type: $type,
                projectId: $projectId,
                projects: $projects,
            );
        }

        return view('accounting.reports.day_book', [
            'companyId'        => $companyId,
            'fromDate'         => $fromDate,
            'toDate'           => $toDate,
            'projects'         => $projects,
            'projectId'        => $projectId,
            'vouchers'         => $vouchers,
            'voucherTypes'     => $voucherTypes,
            'type'             => $type,
            'unbalancedCount'  => $unbalancedCount,
        ]);
    }

    protected function exportCsv(
        int $companyId,
        $fromDate,
        $toDate,
        $vouchers,
        ?string $type,
        ?int $projectId,
        $projects,
    ): StreamedResponse {
        $projectLabel = '';
        if ($projectId) {
            $p = $projects->firstWhere('id', $projectId);
            $projectLabel = $p ? ($p->code . ' - ' . $p->name) : ('#' . $projectId);
        }

        $fileName = 'day_book_' . $fromDate->format('Y-m-d') . '_to_' . $toDate->format('Y-m-d') . ($type ? ('_' . $type) : '') . ($projectId ? ('_project_' . $projectId) : '') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $columns = [
            'Company ID',
            'Project',
            'From Date',
            'To Date',
            'Voucher Date',
            'Voucher No',
            'Voucher Type',
            'Reference',
            'Narration',
            'Debit Total',
            'Credit Total',
            'Difference (Dr - Cr)',
        ];

        $callback = function () use ($columns, $companyId, $projectLabel, $fromDate, $toDate, $vouchers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($vouchers as $v) {
                $debitTotal  = (float) $v->lines->sum('debit');
                $creditTotal = (float) $v->lines->sum('credit');
                $diff        = $debitTotal - $creditTotal;

                fputcsv($handle, [
                    $companyId,
                    $projectLabel,
                    $fromDate->toDateString(),
                    $toDate->toDateString(),
                    optional($v->voucher_date)->toDateString(),
                    $v->voucher_no,
                    $v->voucher_type,
                    $v->reference,
                    $v->narration,
                    number_format($debitTotal, 2, '.', ''),
                    number_format($creditTotal, 2, '.', ''),
                    number_format($diff, 2, '.', ''),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
