<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExtendMachineAssignmentRequest;
use App\Http\Requests\ReturnMachineAssignmentRequest;
use App\Http\Requests\StoreMachineAssignmentRequest;
use App\Models\ActivityLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Party;
use App\Models\Project;
use App\Models\User;
use App\Services\Accounting\ToolCustodyPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MachineAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:machinery.assignment.view')->only(['index', 'show']);
        $this->middleware('permission:machinery.assignment.create')->only(['create', 'store']);
        $this->middleware('permission:machinery.assignment.return')->only(['returnForm', 'processReturn']);
        $this->middleware('permission:machinery.assignment.extend')->only(['extendForm', 'processExtend']);
    }

    public function index(Request $request)
    {
        $today = Carbon::today();

        $query = MachineAssignment::query()
            ->with(['machine', 'contractor', 'worker', 'project'])
            ->orderByDesc('created_at');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('assignment_type')) {
            $query->where('assignment_type', (string) $request->string('assignment_type'));
        }

        if ($request->filled('machine_id')) {
            $query->where('machine_id', (int) $request->input('machine_id'));
        }

        if ($request->boolean('overdue_only')) {
            $query->where('status', 'active')
                ->whereNotNull('expected_return_date')
                ->whereDate('expected_return_date', '<', $today);
        }

        // Search: support both "q" (UI) and legacy "search"
        $search = trim((string) ($request->input('q') ?? $request->input('search') ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('assignment_number', 'like', "%{$search}%")
                    ->orWhereHas('machine', function ($m) use ($search) {
                        $m->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $assignments = $query->paginate(20)->withQueryString();

        // For filter dropdown
        $machines = Machine::query()
            ->select(['id', 'code'])
            ->orderBy('code')
            ->get();

        // Summary cards
        $stats = [
            'active' => MachineAssignment::where('status', 'active')->count(),
            'overdue' => MachineAssignment::where('status', 'active')
                ->whereNotNull('expected_return_date')
                ->whereDate('expected_return_date', '<', $today)
                ->count(),
            'returned_month' => MachineAssignment::where('status', 'returned')
                ->whereNotNull('actual_return_date')
                ->whereMonth('actual_return_date', $today->month)
                ->whereYear('actual_return_date', $today->year)
                ->count(),
            'total' => MachineAssignment::count(),
        ];

        return view('machine_assignments.index', compact('assignments', 'machines', 'stats'));
    }

    public function create()
    {
        // Only machines that are active and not currently issued
        $availableMachines = Machine::query()
            ->where('is_active', true)
            ->where('is_issued', false)
            ->where('status', '!=', 'disposed')
            ->orderBy('code')
            ->get();

        $contractors = Party::query()
            ->where(function ($q) {
                $q->where('is_contractor', true)
                    ->orWhere('is_supplier', true);
            })
            ->orderBy('name')
            ->get();

        $workers = User::query()->orderBy('name')->get();
        $projects = Project::query()->where('status', 'active')->orderBy('name')->get();

        return view('machine_assignments.create', compact('availableMachines', 'contractors', 'workers', 'projects'));
    }

    public function store(StoreMachineAssignmentRequest $request, ToolCustodyPostingService $toolPosting)
    {
        $data = $request->validated();

        try {
            $assignmentId = null;

            DB::transaction(function () use ($data, $toolPosting, &$assignmentId) {
                $machine = Machine::query()->lockForUpdate()->findOrFail($data['machine_id']);

                if (! $machine->is_active) {
                    throw new \RuntimeException('Selected machine is not active.');
                }

                if ($machine->is_issued) {
                    throw new \RuntimeException('Selected machine is already issued.');
                }

                // Build assignment
                $assignment = new MachineAssignment();
                $assignment->assignment_number = MachineAssignment::generateNumber();
                $assignment->machine_id = $machine->id;
                $assignment->assignment_type = $data['assignment_type'];
                $assignment->contractor_party_id = $data['contractor_party_id'] ?? null;
                $assignment->worker_user_id = $data['worker_user_id'] ?? null;
                $assignment->project_id = $data['project_id'] ?? null;
                $assignment->assigned_date = Carbon::parse($data['assigned_date']);
                $assignment->expected_return_date = ! empty($data['expected_return_date']) ? Carbon::parse($data['expected_return_date']) : null;
                $assignment->expected_duration_days = $data['expected_duration_days'] ?? null;

                $assignment->condition_at_issue = $data['condition_at_issue'] ?? 'good';
                $assignment->meter_reading_at_issue = $data['meter_reading_at_issue'] ?? null;
                $assignment->issue_remarks = $data['issue_remarks'] ?? null;

                $assignment->issued_by = Auth::id();
                $assignment->status = 'active';

                // If expected duration is set but expected_return_date isn't, derive it
                if (! $assignment->expected_return_date && $assignment->expected_duration_days) {
                    $assignment->expected_return_date = (clone $assignment->assigned_date)->addDays((int) $assignment->expected_duration_days);
                }

                // Basic validation based on assignment type
                if ($assignment->assignment_type === 'contractor' && ! $assignment->contractor_party_id) {
                    throw new \RuntimeException('Contractor is required for contractor assignment.');
                }
                if ($assignment->assignment_type === 'company_worker' && ! $assignment->worker_user_id) {
                    throw new \RuntimeException('Worker is required for company worker assignment.');
                }

                $assignment->save();
                $assignmentId = $assignment->id;

                // Update machine status flags (keep machine.status stable)
                $machine->is_issued = true;
                $machine->current_assignment_type = $assignment->assignment_type;
                $machine->current_assignment_id = $assignment->id;
                $machine->current_contractor_party_id = $assignment->contractor_party_id;
                $machine->current_worker_user_id = $assignment->worker_user_id;
                $machine->current_project_id = $assignment->project_id;
                $machine->assigned_date = $assignment->assigned_date;
                $machine->save();

                // Post accounting only for Tool Stock machines
                $voucher = $toolPosting->postIssueToCustody($assignment);
                if ($voucher) {
                    $assignment->issue_voucher_id = $voucher->id;
                    $assignment->save();
                }

                // Log activity
                ActivityLog::create([
                    'model_type' => MachineAssignment::class,
                    'model_id'   => $assignment->id,
                    'user_id'    => Auth::id(),
                    'action'     => 'created',
                    'old_values' => null,
                    'new_values' => $assignment->toArray(),
                ]);
            });

            return redirect()->route('machine-assignments.show', $assignmentId)
                ->with('success', 'Machine assigned successfully.');

        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Failed to create assignment: ' . $e->getMessage());
        }
    }

    public function show(MachineAssignment $machineAssignment)
    {
        $assignment = $machineAssignment->load([
            'machine',
            'contractor',
            'worker',
            'project',
            'issuedBy',
            'returnedBy',
            'issueVoucher',
            'returnVoucher',
        ]);

        return view('machine_assignments.show', compact('assignment'));
    }

    public function returnForm(MachineAssignment $machineAssignment)
    {
        $assignment = $machineAssignment->load(['machine', 'contractor', 'worker', 'project']);

        if (! $assignment->isActive()) {
            return redirect()->route('machine-assignments.show', $assignment)
                ->with('error', 'Only active assignments can be returned.');
        }

        return view('machine_assignments.return', compact('assignment'));
    }

    public function processReturn(ReturnMachineAssignmentRequest $request, MachineAssignment $machineAssignment, ToolCustodyPostingService $toolPosting)
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($machineAssignment, $data, $toolPosting) {
                $assignment = MachineAssignment::query()->lockForUpdate()->findOrFail($machineAssignment->id);
                $machine = Machine::query()->lockForUpdate()->findOrFail($assignment->machine_id);

                if (! $assignment->isActive()) {
                    throw new \RuntimeException('Only active assignments can be returned.');
                }

                $assignment->actual_return_date = Carbon::parse($data['actual_return_date']);
                $assignment->condition_at_return = $data['condition_at_return'] ?? 'good';
                $assignment->meter_reading_at_return = $data['meter_reading_at_return'] ?? null;
                $assignment->return_remarks = $data['return_remarks'] ?? null;

                $assignment->return_disposition = $data['return_disposition'] ?? 'returned';
                $assignment->damage_borne_by = $data['damage_borne_by'] ?? null;
                $assignment->damage_recovery_amount = $data['damage_recovery_amount'] ?? 0;

                $assignment->returned_by = Auth::id();

                // Decide status
                if ($assignment->return_disposition === 'scrapped') {
                    $assignment->status = 'lost';
                } elseif ($assignment->condition_at_return === 'damaged') {
                    $assignment->status = 'damaged';
                } else {
                    $assignment->status = 'returned';
                }

                $assignment->save();

                // Update machine flags
                $machine->is_issued = false;
                // IMPORTANT: machines.current_assignment_type is an enum with default 'unassigned' (NOT nullable)
                $machine->current_assignment_type = 'unassigned';
                $machine->current_assignment_id = null;
                $machine->current_contractor_party_id = null;
                $machine->current_worker_user_id = null;
                $machine->current_project_id = null;
                $machine->assigned_date = null;

                if ($assignment->return_disposition === 'scrapped') {
                    // Tool is closed/disposed
                    $machine->status = 'disposed';
                    $machine->is_active = false;
                } elseif ($assignment->condition_at_return === 'damaged') {
                    $machine->status = 'breakdown';
                    $machine->is_active = true;
                } else {
                    $machine->status = 'active';
                    $machine->is_active = true;
                }

                $machine->save();

                // Post accounting (Tool Stock only)
                if ($assignment->return_disposition === 'scrapped') {
                    $voucher = $toolPosting->postScrapSettlement(
                        assignment: $assignment,
                        borneBy: (string) ($assignment->damage_borne_by ?: 'company'),
                        recoveryAmount: (float) ($assignment->damage_recovery_amount ?? 0)
                    );
                    if ($voucher) {
                        $assignment->return_voucher_id = $voucher->id;
                        $assignment->save();
                    }
                } else {
                    $voucher = $toolPosting->postReturnFromCustody($assignment);
                    if ($voucher) {
                        $assignment->return_voucher_id = $voucher->id;
                        $assignment->save();
                    }
                }

                // Log
                ActivityLog::create([
                    'model_type' => MachineAssignment::class,
                    'model_id'   => $assignment->id,
                    'user_id'    => Auth::id(),
                    'action'     => 'returned',
                    'old_values' => null,
                    'new_values' => $assignment->toArray(),
                ]);
            });

            return redirect()->route('machine-assignments.show', $machineAssignment)
                ->with('success', 'Machine return processed successfully.');

        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Failed to process return: ' . $e->getMessage());
        }
    }

    public function extendForm(MachineAssignment $machineAssignment)
    {
        $assignment = $machineAssignment->load(['machine', 'contractor', 'worker', 'project']);

        if (! $assignment->isActive()) {
            return redirect()->route('machine-assignments.show', $assignment)
                ->with('error', 'Only active assignments can be extended.');
        }

        return view('machine_assignments.extend', compact('assignment'));
    }

    public function processExtend(ExtendMachineAssignmentRequest $request, MachineAssignment $machineAssignment)
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($machineAssignment, $data) {
                $assignment = MachineAssignment::query()->lockForUpdate()->findOrFail($machineAssignment->id);

                if (! $assignment->isActive()) {
                    throw new \RuntimeException('Only active assignments can be extended.');
                }

                $assignment->expected_return_date = Carbon::parse($data['new_expected_return_date']);
                $assignment->extended_reason = $data['extension_reason'] ?? null;
                $assignment->status = 'extended';
                $assignment->save();

                ActivityLog::create([
                    'model_type' => MachineAssignment::class,
                    'model_id'   => $assignment->id,
                    'user_id'    => Auth::id(),
                    'action'     => 'extended',
                    'old_values' => null,
                    'new_values' => $assignment->toArray(),
                ]);
            });

            return redirect()->route('machine-assignments.show', $machineAssignment)
                ->with('success', 'Assignment extended successfully.');

        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Failed to extend assignment: ' . $e->getMessage());
        }
    }
}
