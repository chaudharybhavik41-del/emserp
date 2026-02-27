<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\MachineBreakdownRegister;
use App\Models\User;
use Illuminate\Http\Request;

class MachineBreakdownController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:machinery.breakdown.view')->only(['index', 'show']);
        $this->middleware('permission:machinery.breakdown.create')->only(['create', 'store']);
        $this->middleware('permission:machinery.breakdown.acknowledge')->only(['acknowledge','assignTeam','startRepair']);
        $this->middleware('permission:machinery.breakdown.resolve')->only(['resolve']);
    }

    public function index()
    {
        $breakdowns = MachineBreakdownRegister::with('machine')
            ->latest('reported_at')
            ->paginate(20);

        return view('machine_maintenance.breakdowns.index', compact('breakdowns'));
    }

    public function create()
    {
        $machines = Machine::where('is_active', true)->orderBy('name')->get();
        return view('machine_maintenance.breakdowns.create', compact('machines'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'reported_at' => 'required|date',
            'breakdown_type' => 'required|in:mechanical,electrical,hydraulic,software,operator_error,other',
            'severity' => 'required|in:minor,major,critical',
            'problem_description' => 'required|string',
            'immediate_action_taken' => 'nullable|string',
        ]);

        $breakdown = MachineBreakdownRegister::create([
            'breakdown_number' => MachineBreakdownRegister::generateNumber(),
            'machine_id' => $validated['machine_id'],
            'reported_at' => $validated['reported_at'],
            'breakdown_type' => $validated['breakdown_type'],
            'severity' => $validated['severity'],
            'problem_description' => $validated['problem_description'],
            'immediate_action_taken' => $validated['immediate_action_taken'] ?? null,
            'reported_by' => auth()->id(),
            'status' => 'reported',
        ]);

        // Update machine operational status
        $breakdown->machine?->update(['status' => 'breakdown']);

        return redirect()->route('maintenance.breakdowns.show', $breakdown)->with('success', 'Breakdown reported.');
    }

    public function show(MachineBreakdownRegister $breakdown)
    {
        $breakdown->load(['machine','reporter','acknowledger','maintenanceLog']);

        $users = User::orderBy('name')->get();

        return view('machine_maintenance.breakdowns.show', compact('breakdown','users'));
    }

    public function acknowledge(MachineBreakdownRegister $breakdown)
    {
        if ($breakdown->status !== 'reported') {
            return back()->with('error', 'Only reported breakdowns can be acknowledged.');
        }

        $breakdown->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => auth()->id(),
        ]);

        return back()->with('success', 'Breakdown acknowledged.');
    }

    public function assignTeam(Request $request, MachineBreakdownRegister $breakdown)
    {
        $validated = $request->validate([
            'maintenance_team_assigned' => 'required|array|min:1',
            'maintenance_team_assigned.*' => 'exists:users,id',
        ]);

        $breakdown->update([
            'maintenance_team_assigned' => $validated['maintenance_team_assigned'],
            'status' => $breakdown->status === 'reported' ? 'acknowledged' : $breakdown->status,
        ]);

        return back()->with('success', 'Maintenance team assigned.');
    }

    public function startRepair(MachineBreakdownRegister $breakdown)
    {
        if (!in_array($breakdown->status, ['reported','acknowledged'], true)) {
            return back()->with('error', 'Repair can only start for reported/acknowledged breakdowns.');
        }

        $breakdown->update([
            'status' => 'in_progress',
            'repair_started_at' => now(),
        ]);

        $breakdown->machine?->update(['status' => 'under_maintenance']);

        return back()->with('success', 'Repair started.');
    }

    public function resolve(Request $request, MachineBreakdownRegister $breakdown)
    {
        $validated = $request->validate([
            'root_cause' => 'nullable|string',
            'corrective_action' => 'nullable|string',
            'repair_notes' => 'nullable|string',
        ]);

        $breakdown->update([
            'status' => 'resolved',
            'repair_completed_at' => now(),
            'resolved_by' => auth()->id(),
            'root_cause' => $validated['root_cause'] ?? null,
            'corrective_action' => $validated['corrective_action'] ?? null,
            'repair_notes' => $validated['repair_notes'] ?? null,
        ]);

        $breakdown->machine?->update(['status' => 'active']);

        return back()->with('success', 'Breakdown resolved.');
    }
}