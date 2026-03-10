<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MachineExpenseReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.reports.view')->only(['index']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) Config::get('accounting.default_company_id', 1);
    }

    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $fromDate = $request->date('from_date') ?: now()->startOfMonth();
        $toDate = $request->date('to_date') ?: now();
        $machineId = $request->integer('machine_id') ?: null;
        $projectId = $request->integer('project_id') ?: null;

        $machines = Machine::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $projects = Project::query()
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $hasMachineDimension = Schema::hasTable('voucher_lines')
            && Schema::hasColumn('voucher_lines', 'machine_id');

        $rows = collect();
        $summaryByAccount = collect();
        $grandTotal = 0.0;

        if ($hasMachineDimension) {
            $baseQuery = DB::table('voucher_lines as vl')
                ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
                ->join('accounts as a', 'a.id', '=', 'vl.account_id')
                ->leftJoin('cost_centers as cc', 'cc.id', '=', 'vl.cost_center_id')
                ->leftJoin('machines as m', 'm.id', '=', 'vl.machine_id')
                ->where('v.company_id', $companyId)
                ->where('v.status', 'posted')
                ->whereNotNull('vl.machine_id')
                ->where('vl.debit', '>', 0)
                ->whereDate('v.voucher_date', '>=', $fromDate->toDateString())
                ->whereDate('v.voucher_date', '<=', $toDate->toDateString());

            if ($machineId) {
                $baseQuery->where('vl.machine_id', $machineId);
            }

            if ($projectId) {
                $baseQuery->where(function ($q) use ($projectId) {
                    $q->where('v.project_id', $projectId)
                        ->orWhere('cc.project_id', $projectId);
                });
            }

            $rows = (clone $baseQuery)
                ->select([
                    'v.id as voucher_id',
                    'v.voucher_no',
                    'v.voucher_date',
                    'v.voucher_type',
                    'v.narration as voucher_narration',
                    'vl.line_no',
                    'vl.description as line_description',
                    'vl.debit',
                    'a.id as account_id',
                    'a.code as account_code',
                    'a.name as account_name',
                    'm.id as machine_id',
                    'm.code as machine_code',
                    'm.name as machine_name',
                ])
                ->orderBy('v.voucher_date')
                ->orderBy('v.id')
                ->orderBy('vl.line_no')
                ->get();

            $summaryByAccount = (clone $baseQuery)
                ->selectRaw('a.id as account_id, a.code as account_code, a.name as account_name, SUM(vl.debit) as total_debit')
                ->groupBy('a.id', 'a.code', 'a.name')
                ->orderByDesc('total_debit')
                ->get();

            $grandTotal = (float) $rows->sum('debit');
        }

        return view('accounting.reports.machine_expense', [
            'companyId' => $companyId,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'machineId' => $machineId,
            'projectId' => $projectId,
            'machines' => $machines,
            'projects' => $projects,
            'hasMachineDimension' => $hasMachineDimension,
            'rows' => $rows,
            'summaryByAccount' => $summaryByAccount,
            'grandTotal' => round($grandTotal, 2),
        ]);
    }
}
