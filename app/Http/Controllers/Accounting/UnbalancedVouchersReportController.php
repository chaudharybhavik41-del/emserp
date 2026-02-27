<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Voucher;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnbalancedVouchersReportController extends Controller
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
     * Validation report: lists vouchers where total debit != total credit.
     *
     * This is important for parallel-run validation with Tally and to catch
     * any manual voucher mistakes.
     */
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $toDate    = $request->date('to_date') ?: now();
        $fromDate  = $request->date('from_date') ?: $toDate->copy()->startOfMonth();
        $type      = $request->get('voucher_type');
        $projectId = $request->integer('project_id') ?: null;

        $baseQuery = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereDate('v.voucher_date', '>=', $fromDate->toDateString())
            ->whereDate('v.voucher_date', '<=', $toDate->toDateString());

        if ($type) {
            $baseQuery->where('v.voucher_type', $type);
        }

        if ($projectId) {
            $baseQuery->where('v.project_id', $projectId);
        }

        $rows = (clone $baseQuery)
            ->selectRaw(
                "v.id, v.voucher_no, v.voucher_type, v.voucher_date, v.reference, v.narration, v.project_id, "
                . "COALESCE(SUM(vl.debit),0) as debit_total, COALESCE(SUM(vl.credit),0) as credit_total, "
                . "(COALESCE(SUM(vl.debit),0) - COALESCE(SUM(vl.credit),0)) as diff"
            )
            ->groupBy('v.id', 'v.voucher_no', 'v.voucher_type', 'v.voucher_date', 'v.reference', 'v.narration', 'v.project_id')
            ->havingRaw('ABS((COALESCE(SUM(vl.debit),0) - COALESCE(SUM(vl.credit),0))) >= 0.01')
            ->orderBy('v.voucher_date')
            ->orderBy('v.id')
            ->get();

        // Filters
        $voucherTypes = Voucher::where('company_id', $companyId)
            ->select('voucher_type')
            ->distinct()
            ->orderBy('voucher_type')
            ->pluck('voucher_type')
            ->all();

        $projects = Project::orderBy('code')
            ->get(['id', 'code', 'name']);

        $projectMap = $projects->keyBy('id');

        // Export
        if ($request->get('export') === 'csv') {
            return $this->exportCsv($rows, $projectMap, $fromDate->toDateString(), $toDate->toDateString());
        }

        return view('accounting.reports.unbalanced_vouchers', [
            'companyId'    => $companyId,
            'fromDate'     => $fromDate,
            'toDate'       => $toDate,
            'type'         => $type,
            'projectId'    => $projectId,
            'voucherTypes' => $voucherTypes,
            'projects'     => $projects,
            'rows'         => $rows,
        ]);
    }

    protected function exportCsv($rows, $projectMap, string $from, string $to): StreamedResponse
    {
        $fileName = 'unbalanced_vouchers_' . $from . '_to_' . $to . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $columns = [
            'Date',
            'Voucher No',
            'Type',
            'Project',
            'Reference',
            'Narration',
            'Debit Total',
            'Credit Total',
            'Difference (Dr - Cr)',
        ];

        $callback = function () use ($rows, $projectMap, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($rows as $r) {
                $proj = $r->project_id ? ($projectMap[$r->project_id]->code ?? ('#' . $r->project_id)) : '';

                fputcsv($handle, [
                    $r->voucher_date,
                    $r->voucher_no,
                    strtoupper($r->voucher_type),
                    $proj,
                    $r->reference,
                    $r->narration,
                    number_format((float) $r->debit_total, 2, '.', ''),
                    number_format((float) $r->credit_total, 2, '.', ''),
                    number_format((float) $r->diff, 2, '.', ''),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
