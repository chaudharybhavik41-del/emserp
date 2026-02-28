<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\VoucherLine;
use App\Models\FixedAsset;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class MachineExpenseLedgerReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.reports.view')->only(['index']);
    }

    public function index(Request $request)
    {
        $companyId = (int) (Config::get('accounting.default_company_id', 1));

        $toDate = $request->date('to_date') ?: now();
        $fromDate = $request->date('from_date') ?: $toDate->copy()->startOfMonth();

        $machineId = $request->integer('machine_id') ?: null;
        $projectId = $request->integer('project_id') ?: null;

        $machines = FixedAsset::query()
            ->where('asset_type', 'machinery')
            ->orderBy('asset_code')
            ->get(['id', 'asset_code', 'name']);

        $projects = Project::query()->orderBy('code')->orderBy('name')->get(['id', 'code', 'name']);

        $rowsQuery = VoucherLine::query()
            ->with(['voucher', 'account', 'machine'])
            ->whereNotNull('machine_id')
            ->whereHas('voucher', function ($q) use ($companyId, $fromDate, $toDate, $projectId) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereDate('voucher_date', '>=', $fromDate->toDateString())
                    ->whereDate('voucher_date', '<=', $toDate->toDateString());

                if ($projectId) {
                    $q->where('project_id', $projectId);
                }
            })
            ->orderBy('voucher_id')
            ->orderBy('line_no');

        if ($machineId) {
            $rowsQuery->where('machine_id', $machineId);
        }

        $rows = $rowsQuery->get();

        $groupedTotals = $rows
            ->groupBy(fn ($line) => $line->account?->name ?? ('Account #' . $line->account_id))
            ->map(function ($lines) {
                $debit = (float) $lines->sum('debit');
                $credit = (float) $lines->sum('credit');

                return [
                    'debit' => $debit,
                    'credit' => $credit,
                    'net' => $debit - $credit,
                ];
            })
            ->sortKeys();

        return view('accounting.reports.machine_expense_ledger', [
            'machines' => $machines,
            'projects' => $projects,
            'rows' => $rows,
            'groupedTotals' => $groupedTotals,
            'machineId' => $machineId,
            'projectId' => $projectId,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }
}
