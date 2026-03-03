<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMachineRequest;
use App\Http\Requests\UpdateMachineRequest;
use App\Models\Machine;
use App\Models\MaterialType;
use App\Models\MaterialCategory;
use App\Models\MaterialSubcategory;
use App\Models\Party;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\Machinery\MachineOpeningFaPostingService;
use App\Services\MaintenanceScheduleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MachineController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:machinery.machine.view')->only(['index', 'show']);
        $this->middleware('permission:machinery.machine.create')->only(['create', 'store', 'importOpeningForm', 'importOpening', 'postOpeningFaVoucher']);
        $this->middleware('permission:machinery.machine.update')->only(['edit', 'update']);
        $this->middleware('permission:machinery.machine.delete')->only('destroy');
    }

    public function index(Request $request)
    {
        $query = Machine::with([
            'category',
            'subcategory',
            'department',
            'currentContractor',
            'currentWorker',
            'currentProject'
        ]);

        // Search
        if ($search = trim($request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('serial_number', 'like', '%' . $search . '%');
            });
        }

        // Filter by category
        if ($categoryId = $request->get('category_id')) {
            $query->where('material_category_id', $categoryId);
        }

        // Filter by status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filter by assignment
        if ($assignment = $request->get('assignment')) {
            if ($assignment === 'available') {
                $query->where('is_issued', false)->where('status', 'active');
            } elseif ($assignment === 'issued') {
                $query->where('is_issued', true);
            }
        }

        // Filter by active/inactive
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $machines = $query->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        // Get MACHINERY type categories for filter
        $machineryType = MaterialType::where('code', 'MACHINERY')->first();
        $categories = $machineryType 
            ? MaterialCategory::where('material_type_id', $machineryType->id)->orderBy('sort_order')->get()
            : collect();

        $statuses = ['active', 'under_maintenance', 'breakdown', 'retired', 'disposed'];

        return view('machines.index', compact('machines', 'categories', 'statuses'));
    }

    public function create()
    {
        $machine = new Machine();

        // Get MACHINERY type and its categories
        $machineryType = MaterialType::where('code', 'MACHINERY')->firstOrFail();
        $categories = MaterialCategory::where('material_type_id', $machineryType->id)
            ->orderBy('sort_order')
            ->get();

        $subcategories = MaterialSubcategory::whereIn('material_category_id', $categories->pluck('id'))
            ->orderBy('code')
            ->get();

        $suppliers = Party::where('is_supplier', true)->orderBy('name')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return view('machines.create', compact(
            'machine',
            'machineryType',
            'categories',
            'subcategories',
            'suppliers',
            'departments'
        ));
    }

    public function importOpeningForm(Request $request, MachineOpeningFaPostingService $openingPostingService): View
    {
        $cutoverDate = (string) ($request->query('cutover_date')
            ?: setting('machinery.opening_fa_cutover_date', '2026-01-01'));

        $openingPreview = $openingPostingService->preview($cutoverDate);

        return view('machines.import_opening', [
            'defaultCutoverDate' => $cutoverDate,
            'openingPreview' => $openingPreview,
        ]);
    }

    public function importOpening(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cutover_date' => ['nullable', 'date'],
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $cutoverDate = (string) ($validated['cutover_date'] ?? '2026-01-01');
        $filePath = (string) $validated['csv_file']->getRealPath();

        $machineryType = MaterialType::where('code', 'MACHINERY')->first();
        if (! $machineryType) {
            return back()->withErrors(['csv_file' => 'MACHINERY material type not found.']);
        }

        $defaultCategory = MaterialCategory::query()
            ->where('material_type_id', $machineryType->id)
            ->where('code', 'OTHER')
            ->first();

        if (! $defaultCategory) {
            $defaultCategory = MaterialCategory::query()
                ->where('material_type_id', $machineryType->id)
                ->orderBy('id')
                ->first();
        }

        if (! $defaultCategory) {
            return back()->withErrors(['csv_file' => 'No machinery category found.']);
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return back()->withErrors(['csv_file' => 'Unable to open uploaded CSV file.']);
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers) || empty($headers)) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV header row is missing or invalid.']);
        }

        $normalizedHeaders = array_map(function ($h) {
            $h = strtolower(trim((string) $h));
            $h = str_replace(['-', ' '], '_', $h);
            return preg_replace('/[^a-z0-9_]/', '', $h);
        }, $headers);

        $headerIndex = [];
        foreach ($normalizedHeaders as $idx => $name) {
            if ($name !== '') {
                $headerIndex[$name] = $idx;
            }
        }

        $created = 0;
        $skipped = 0;
        $errors = [];
        $lineNo = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNo++;

            $get = function (array $keys) use ($row, $headerIndex): string {
                foreach ($keys as $k) {
                    if (array_key_exists($k, $headerIndex)) {
                        $value = $row[$headerIndex[$k]] ?? '';
                        return trim((string) $value);
                    }
                }
                return '';
            };

            $name = $get(['name', 'machine_name', 'asset_name']);
            $assetCode = $get(['asset_code', 'code']);
            $serialNo = $get(['serial_no', 'serial_number']);
            $openingWdvRaw = $get(['opening_wdv', 'wdv']);
            $openingCostRaw = $get(['opening_cost', 'gross_cost', 'cost']);
            $openingAccumRaw = $get(['opening_accum_depr', 'opening_accumulated_depreciation', 'accum_depr']);
            $openingDateRaw = $get(['opening_date']);
            $purchaseDateRaw = $get(['purchase_date']);
            $make = $get(['make']);
            $model = $get(['model']);
            $location = $get(['location', 'current_location']);

            // Skip fully blank lines
            $isBlankRow = true;
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    $isBlankRow = false;
                    break;
                }
            }
            if ($isBlankRow) {
                continue;
            }

            if ($name === '') {
                $errors[] = "Row {$lineNo}: name is required.";
                continue;
            }

            $openingWdv = $this->parseCsvDecimal($openingWdvRaw);
            if ($openingWdv === null) {
                $errors[] = "Row {$lineNo}: opening_wdv is required and must be numeric.";
                continue;
            }
            if ($openingWdv < 0) {
                $errors[] = "Row {$lineNo}: opening_wdv cannot be negative.";
                continue;
            }

            $openingCost = $this->parseCsvDecimal($openingCostRaw);
            $openingAccum = $this->parseCsvDecimal($openingAccumRaw);

            if ($openingCost !== null && $openingAccum !== null && $openingWdv <= 0) {
                $openingWdv = max(0, round($openingCost - $openingAccum, 2));
            }

            if ($assetCode !== '' && Machine::where('code', $assetCode)->exists()) {
                $skipped++;
                continue;
            }

            if ($serialNo !== '' && Machine::where('serial_number', $serialNo)->exists()) {
                $skipped++;
                continue;
            }

            if ($assetCode === '') {
                $assetCode = Machine::generateCode((int) $defaultCategory->id);
            }

            if ($serialNo === '') {
                $serialNo = 'OPEN-' . $assetCode;
            }
            $serialNo = $this->ensureUniqueMachineSerial($serialNo);

            $openingDate = $this->parseCsvDate($openingDateRaw) ?? $cutoverDate;
            $purchaseDate = $this->parseCsvDate($purchaseDateRaw);

            Machine::create([
                'material_type_id' => (int) $machineryType->id,
                'material_category_id' => (int) $defaultCategory->id,
                'material_subcategory_id' => null,
                'code' => substr($assetCode, 0, 50),
                'name' => substr($name, 0, 200),
                'short_name' => substr($name, 0, 100),
                'serial_number' => substr($serialNo, 0, 100),
                'make' => $make !== '' ? substr($make, 0, 100) : null,
                'model' => $model !== '' ? substr($model, 0, 100) : null,
                'current_location' => $location !== '' ? substr($location, 0, 200) : null,
                'purchase_date' => $purchaseDate,
                'opening_date' => $openingDate,
                'opening_cost' => $openingCost,
                'opening_accum_depr' => $openingAccum,
                'opening_wdv' => round(max(0, $openingWdv), 2),
                'purchase_price' => round(max(0, $openingCost ?? $openingWdv), 2),
                'accounting_treatment' => 'fixed_asset',
                'status' => 'active',
                'is_active' => true,
                'is_issued' => false,
                'current_assignment_type' => 'unassigned',
                'maintenance_frequency_days' => 90,
                'maintenance_alert_days' => 7,
                'created_by' => Auth::id(),
            ]);

            $created++;
        }

        fclose($handle);

        $summary = [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ];

        $status = "Opening machinery import completed. Created: {$created}, Skipped: {$skipped}, Errors: " . count($errors) . '.';

        return redirect()
            ->route('machines.import-opening')
            ->with('success', $status)
            ->with('import_summary', $summary);
    }

    public function postOpeningFaVoucher(
        Request $request,
        MachineOpeningFaPostingService $openingPostingService
    ): RedirectResponse {
        $validated = $request->validate([
            'cutover_date' => ['required', 'date'],
        ]);

        try {
            $result = $openingPostingService->post((string) $validated['cutover_date']);
            $voucher = $result['voucher'];

            if (!empty($result['created'])) {
                $msg = 'Opening FA JV posted: ' . ($voucher->voucher_no ?? ('#' . $voucher->id))
                    . ' | Assets: ' . (int) ($result['asset_count'] ?? 0)
                    . ' | Amount: ' . number_format((float) ($result['total_opening_wdv'] ?? 0), 2);
            } else {
                $msg = 'Opening FA JV already exists: ' . ($voucher->voucher_no ?? ('#' . $voucher->id));
            }

            return redirect()
                ->route('machines.import-opening', ['cutover_date' => (string) $validated['cutover_date']])
                ->with('success', $msg);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('machines.import-opening', ['cutover_date' => (string) $validated['cutover_date']])
                ->withErrors(['cutover_date' => 'Failed to post Opening FA JV: ' . $e->getMessage()]);
        }
    }

    public function store(StoreMachineRequest $request)
    {
        $data = $request->validated();
        $data = $this->normalizeOpeningAssetValues($data);

        // Get MACHINERY type ID
        $machineryType = MaterialType::where('code', 'MACHINERY')->firstOrFail();
        $data['material_type_id'] = $machineryType->id;

        // Auto-generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = Machine::generateCode($data['material_category_id']);
        }

        // Set defaults
        $data['is_active'] = $request->boolean('is_active', true);
        $data['allow_fuel_issue'] = $request->boolean('allow_fuel_issue', false);
        $data['is_issued'] = false;
        $data['current_assignment_type'] = 'unassigned';
        $data['created_by'] = Auth::id();

        // Calculate warranty expiry if warranty months provided
        if (!empty($data['purchase_date']) && !empty($data['warranty_months'])) {
            $data['warranty_expiry_date'] = now()
                ->parse($data['purchase_date'])
                ->addMonths((int) $data['warranty_months'])
                ->format('Y-m-d');
        }

        // Calculate next maintenance due date
        if (!empty($data['maintenance_frequency_days'])) {
            $data['next_maintenance_due_date'] = now()
                ->addDays((int) $data['maintenance_frequency_days'])
                ->format('Y-m-d');
        }

        $machine = Machine::create($data);

        ActivityLog::logCreated($machine, "Created machine: {$machine->code} - {$machine->name}");

        return redirect()
            ->route('machines.show', $machine)
            ->with('success', 'Machine created successfully.');
    }

    public function show(Machine $machine)
    {
        $machine->load([
            'category',
            'subcategory',
            'supplier',
            'department',
            'currentContractor',
            'currentWorker',
            'currentProject',
            'creator',
            'updater'
        ]);

        return view('machines.show', compact('machine'));
    }

    public function edit(Machine $machine)
	{
    $machineryType = MaterialType::where('code', 'MACHINERY')->firstOrFail();
    
    $categories = MaterialCategory::where('material_type_id', $machineryType->id)
        ->orderBy('name')
        ->get();
    
    $subcategories = MaterialSubcategory::whereIn(
        'material_category_id',
        $categories->pluck('id')
    )->orderBy('name')->get();
    
    $suppliers = Party::where('is_supplier', true)->orderBy('name')->get();
    $departments = Department::orderBy('name')->get();
    
    return view('machines.edit', compact(
        'machine',
        'categories',
        'subcategories',
        'suppliers',
        'departments'
    ));
	}


    public function update(
        UpdateMachineRequest $request,
        Machine $machine,
        MaintenanceScheduleService $scheduler
    )
    {
        $data = $request->validated();
        $data = $this->normalizeOpeningAssetValues($data);
        $oldValues = $machine->toArray();

        $data['is_active'] = $request->boolean('is_active', true);
        $data['allow_fuel_issue'] = $request->boolean('allow_fuel_issue', false);
        $data['updated_by'] = Auth::id();

        // Recalculate warranty expiry if changed
        if (isset($data['purchase_date']) && isset($data['warranty_months'])) {
            $data['warranty_expiry_date'] = now()
                ->parse($data['purchase_date'])
                ->addMonths((int) $data['warranty_months'])
                ->format('Y-m-d');
        }

        $machine->update($data);
        $scheduler->syncMachineNextDueDate((int) $machine->id);

        ActivityLog::logUpdated($machine, $oldValues, "Updated machine: {$machine->code} - {$machine->name}");

        return redirect()
            ->route('machines.show', $machine)
            ->with('success', 'Machine updated successfully.');
    }

    public function destroy(Machine $machine)
    {
        // Guard: Don't allow delete if machine is issued
        if ($machine->is_issued) {
            return redirect()
                ->route('machines.index')
                ->with('error', 'Cannot delete machine that is currently issued. Please return it first.');
        }

        $machine->delete();

        return redirect()
            ->route('machines.index')
            ->with('success', 'Machine deleted successfully.');
    }

    /**
     * Keep opening asset values consistent for legacy/cutover machinery.
     */
    protected function normalizeOpeningAssetValues(array $data): array
    {
        $openingCost = isset($data['opening_cost']) && $data['opening_cost'] !== ''
            ? (float) $data['opening_cost']
            : null;

        $openingAccumDepr = isset($data['opening_accum_depr']) && $data['opening_accum_depr'] !== ''
            ? (float) $data['opening_accum_depr']
            : null;

        $hasOpeningWdv = isset($data['opening_wdv']) && $data['opening_wdv'] !== '';

        if (! $hasOpeningWdv && $openingCost !== null) {
            $computed = $openingCost - ($openingAccumDepr ?? 0.0);
            $data['opening_wdv'] = round(max(0, $computed), 2);
        } elseif ($hasOpeningWdv) {
            $data['opening_wdv'] = round(max(0, (float) $data['opening_wdv']), 2);
        }

        return $data;
    }

    protected function parseCsvDecimal(?string $value): ?float
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        $v = str_replace(',', '', $v);
        if (! is_numeric($v)) {
            return null;
        }

        return round((float) $v, 2);
    }

    protected function parseCsvDate(?string $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        $ts = strtotime($v);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    protected function ensureUniqueMachineSerial(string $baseSerial): string
    {
        $serial = trim($baseSerial) !== '' ? trim($baseSerial) : ('OPEN-' . now()->format('YmdHis'));
        $candidate = substr($serial, 0, 100);
        $suffix = 1;

        while (Machine::where('serial_number', $candidate)->exists()) {
            $tail = '-' . $suffix;
            $head = substr($serial, 0, max(1, 100 - strlen($tail)));
            $candidate = $head . $tail;
            $suffix++;
        }

        return $candidate;
    }
}
