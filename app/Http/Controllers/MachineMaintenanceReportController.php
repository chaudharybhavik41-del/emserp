<?php

namespace App\Http\Controllers;

use App\Models\MachineAssignment;
use App\Models\MachineMaintenanceLog;
use App\Models\Party;
use Illuminate\Http\Request;

class MachineMaintenanceReportController extends Controller
{
    public function issuedRegister(Request $request)
    {
        $q = MachineAssignment::with(['machine','contractor','worker','project'])
            ->latest('assigned_date');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('assignment_type')) {
            $q->where('assignment_type', $request->string('assignment_type'));
        }

        if ($request->filled('contractor_party_id')) {
            $q->where('contractor_party_id', (int)$request->input('contractor_party_id'));
        }

        if ($request->filled('worker_user_id')) {
            $q->where('worker_user_id', (int)$request->input('worker_user_id'));
        }

        if ($request->filled('from')) {
            $q->whereDate('assigned_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('assigned_date', '<=', $request->date('to'));
        }

        $assignments = $q->paginate(25)->withQueryString();

        $contractors = Party::orderBy('name')->get();

        return view('machine_maintenance.reports.issued_register', compact('assignments', 'contractors'));
    }

    public function costAnalysis(Request $request)
    {
        $q = MachineMaintenanceLog::query()
            ->whereNotNull('contractor_party_id')
            ->whereNotNull('total_cost');

        if ($request->filled('from')) {
            $q->whereDate('completed_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('completed_at', '<=', $request->date('to'));
        }
        if ($request->filled('contractor_party_id')) {
            $q->where('contractor_party_id', (int)$request->input('contractor_party_id'));
        }

        $rows = $q->selectRaw('
                contractor_party_id,
                machine_id,
                COUNT(*) as jobs_count,
                SUM(total_cost) as total_cost_sum,
                SUM(downtime_hours) as downtime_sum
            ')
            ->groupBy('contractor_party_id', 'machine_id')
            ->orderByDesc('total_cost_sum')
            ->get();

        $rows->load(['contractor','machine']);

        $contractors = Party::orderBy('name')->get();

        return view('machine_maintenance.reports.cost_analysis', compact('rows', 'contractors'));
    }
}
