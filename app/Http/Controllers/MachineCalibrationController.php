<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMachineCalibrationRequest;
use App\Http\Requests\UpdateMachineCalibrationRequest;
use App\Models\Machine;
use App\Models\MachineCalibrationRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MachineCalibrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:machinery.calibration.view')->only(['index', 'show', 'dashboard']);
        $this->middleware('permission:machinery.calibration.create')->only(['create', 'store']);
        $this->middleware('permission:machinery.calibration.update')->only(['edit', 'update']);
        $this->middleware('permission:machinery.calibration.delete')->only(['destroy']);
    }

    /**
     * Calibration dashboard with overview
     */
    public function dashboard()
    {
        $alertDays = config('machinery.calibration_alert_days', 15);

        $stats = [
            'total_requiring' => Machine::where('requires_calibration', true)->count(),
            'overdue_count' => MachineCalibrationRecord::overdue()->count(),
            'due_soon_count' => MachineCalibrationRecord::dueSoon($alertDays)->count(),
            'completed_this_month' => MachineCalibrationRecord::completed()
                ->whereMonth('calibration_date', now()->month)
                ->whereYear('calibration_date', now()->year)
                ->count(),
        ];

        $overdueCalibrations = MachineCalibrationRecord::with('machine')
            ->overdue()
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $dueSoonCalibrations = MachineCalibrationRecord::with('machine')
            ->dueSoon(30)
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        return view('machine_calibrations.dashboard', compact(
            'stats',
            'overdueCalibrations',
            'dueSoonCalibrations'
        ));
    }

    /**
     * List all calibration records
     */
    public function index(Request $request)
    {
        $query = MachineCalibrationRecord::with(['machine.category', 'performer', 'verifier']);

        // Search
        if ($search = trim($request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('calibration_number', 'like', '%' . $search . '%')
                    ->orWhere('certificate_number', 'like', '%' . $search . '%')
                    ->orWhereHas('machine', function ($mq) use ($search) {
                        $mq->where('code', 'like', '%' . $search . '%')
                            ->orWhere('name', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filters
        if ($machineId = $request->get('machine_id')) {
            $query->where('machine_id', $machineId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($result = $request->get('result')) {
            $query->where('result', $result);
        }

        // Date range
        if ($fromDate = $request->get('from_date')) {
            $query->whereDate('calibration_date', '>=', $fromDate);
        }

        if ($toDate = $request->get('to_date')) {
            $query->whereDate('calibration_date', '<=', $toDate);
        }

        $calibrations = $query->orderByDesc('calibration_date')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        // Data for filters
        $machines = Machine::where('requires_calibration', true)->orderBy('code')->get();

        return view('machine_calibrations.index', compact('calibrations', 'machines'));
    }

    /**
     * Show create form
     */
    public function create(Request $request)
    {
        $calibration = new MachineCalibrationRecord();
        
        $selectedMachineId = $request->get('machine_id');
        $selectedMachine = null;

        if ($selectedMachineId) {
            $selectedMachine = Machine::findOrFail($selectedMachineId);
        }

        $machines = Machine::where('requires_calibration', true)
            ->with('category')
            ->orderBy('code')
            ->get();
            
        // ADD THIS - was missing!
    	$users = User::where('is_active', true)
        ->orderBy('name')
        ->get();
    
    return view('machine_calibrations.create', compact('machines', 'users'));
    }

    /**
     * Store calibration record
     */
    public function store(StoreMachineCalibrationRequest $request)
    {
        $data = $request->validated();

        DB::beginTransaction();
        try {
            // Generate calibration number
            $data['calibration_number'] = MachineCalibrationRecord::generateNumber();
            $data['created_by'] = Auth::id();

            // Handle file uploads
            if ($request->hasFile('certificate_file')) {
                $data['certificate_file_path'] = $request->file('certificate_file')
                    ->store('calibrations/certificates', 'public');
            }

            if ($request->hasFile('report_file')) {
                $data['report_file_path'] = $request->file('report_file')
                    ->store('calibrations/reports', 'public');
            }

            // Create record
            $calibration = MachineCalibrationRecord::create($data);

            // Update machine calibration dates
            $machine = Machine::findOrFail($data['machine_id']);
            $machine->update([
                'last_calibration_date' => $data['calibration_date'],
                'next_calibration_due_date' => $data['next_due_date'],
                'calibration_agency' => $data['calibration_agency'] ?? $machine->calibration_agency,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()
                ->route('machine-calibrations.show', $calibration)
                ->with('success', 'Calibration record created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded files if transaction fails
            if (isset($data['certificate_file_path'])) {
                Storage::disk('public')->delete($data['certificate_file_path']);
            }
            if (isset($data['report_file_path'])) {
                Storage::disk('public')->delete($data['report_file_path']);
            }

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create calibration record: ' . $e->getMessage()]);
        }
    }

    /**
     * Show calibration details
     */
    public function show(MachineCalibrationRecord $machineCalibration)
    {
        $machineCalibration->load(['machine.category', 'performer', 'verifier', 'creator']);

        return view('machine_calibrations.show', compact('machineCalibration'));
    }

    /**
     * Show edit form
     */
    public function edit(MachineCalibrationRecord $machineCalibration)
    {
        $machines = Machine::where('requires_calibration', true)
            ->with('category')
            ->orderBy('code')
            ->get();
            
        $users = User::where('is_active', true)->orderBy('name')->get();

        return view('machine_calibrations.edit', compact('machineCalibration', 'machines', 'users'));
    }

    /**
     * Update calibration record
     */
    public function update(UpdateMachineCalibrationRequest $request, MachineCalibrationRecord $machineCalibration)
    {
        $data = $request->validated();

        DB::beginTransaction();
        try {
            // Handle file uploads
            if ($request->hasFile('certificate_file')) {
                // Delete old file
                if ($machineCalibration->certificate_file_path) {
                    Storage::disk('public')->delete($machineCalibration->certificate_file_path);
                }
                $data['certificate_file_path'] = $request->file('certificate_file')
                    ->store('calibrations/certificates', 'public');
            }

            if ($request->hasFile('report_file')) {
                // Delete old file
                if ($machineCalibration->report_file_path) {
                    Storage::disk('public')->delete($machineCalibration->report_file_path);
                }
                $data['report_file_path'] = $request->file('report_file')
                    ->store('calibrations/reports', 'public');
            }

            // Update record
            $machineCalibration->update($data);

            // Update machine calibration dates if this is the latest
            $latestCalibration = MachineCalibrationRecord::where('machine_id', $machineCalibration->machine_id)
                ->where('status', 'completed')
                ->orderByDesc('calibration_date')
                ->first();

            if ($latestCalibration && $latestCalibration->id === $machineCalibration->id) {
                $machineCalibration->machine->update([
                    'last_calibration_date' => $data['calibration_date'],
                    'next_calibration_due_date' => $data['next_due_date'],
                    'updated_by' => Auth::id(),
                ]);
            }

            DB::commit();

            return redirect()
                ->route('machine-calibrations.show', $machineCalibration)
                ->with('success', 'Calibration record updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update calibration record: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete calibration record
     */
    public function destroy(MachineCalibrationRecord $machineCalibration)
    {
        try {
            $machineCalibration->delete(); // Files auto-deleted via model boot

            return redirect()
                ->route('machine-calibrations.index')
                ->with('success', 'Calibration record deleted successfully.');

        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete calibration record: ' . $e->getMessage()]);
        }
    }
}
