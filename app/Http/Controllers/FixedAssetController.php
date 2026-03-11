<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Models\Party;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FixedAssetController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:fixed_assets.view')->only(['index', 'show']);
        $this->middleware('permission:fixed_assets.create')->only(['create', 'store']);
        $this->middleware('permission:fixed_assets.edit')->only(['edit', 'update']);
    }

    public function index(Request $request): View
    {
        $query = FixedAsset::query()
            ->with(['project', 'vendor'])
            ->where('asset_type', 'machinery');

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('serial_no', 'like', '%' . $search . '%');
            });
        }

        if ($projectId = $request->integer('project_id')) {
            $query->where('project_id', $projectId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('purchase_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('purchase_date', '<=', $dateTo);
        }

        $assets = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $projects = Project::orderBy('name')->get(['id', 'name']);
        $statuses = ['in_use', 'idle', 'sold', 'scrapped'];

        return view('fixed_assets.machinery.index', compact('assets', 'projects', 'statuses'));
    }

    public function create(): View
    {
        $asset = new FixedAsset([
            'asset_type' => 'machinery',
            'status' => 'in_use',
            'opening_wdv' => 0,
        ]);

        $projects = Project::orderBy('name')->get(['id', 'name']);
        $vendors = Party::where('is_supplier', true)->orderBy('name')->get(['id', 'name']);
        $statuses = ['in_use', 'idle', 'sold', 'scrapped'];
        $machineTypes = ['long_term', 'short_term'];

        return view('fixed_assets.machinery.create', compact('asset', 'projects', 'vendors', 'statuses', 'machineTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['asset_type'] = 'machinery';
        $data['created_by'] = Auth::id();

        $asset = FixedAsset::create($data);

        return redirect()->route('fixed-assets.machinery.show', $asset)->with('success', 'Fixed asset created successfully.');
    }

    public function show(FixedAsset $fixedAsset): View
    {
        abort_unless($fixedAsset->asset_type === 'machinery', 404);

        $fixedAsset->load([
            'project',
            'vendor',
            'item',
            'links.voucher',
            'links.voucherLine',
            'creator',
            'updater',
        ]);

        return view('fixed_assets.machinery.show', ['asset' => $fixedAsset]);
    }

    public function edit(FixedAsset $fixedAsset): View
    {
        abort_unless($fixedAsset->asset_type === 'machinery', 404);

        $projects = Project::orderBy('name')->get(['id', 'name']);
        $vendors = Party::where('is_supplier', true)->orderBy('name')->get(['id', 'name']);
        $statuses = ['in_use', 'idle', 'sold', 'scrapped'];
        $machineTypes = ['long_term', 'short_term'];

        return view('fixed_assets.machinery.edit', ['asset' => $fixedAsset, 'projects' => $projects, 'vendors' => $vendors, 'statuses' => $statuses, 'machineTypes' => $machineTypes]);
    }

    public function update(Request $request, FixedAsset $fixedAsset): RedirectResponse
    {
        abort_unless($fixedAsset->asset_type === 'machinery', 404);

        $data = $this->validatedData($request, $fixedAsset->id);
        $data['updated_by'] = Auth::id();

        $fixedAsset->update($data);

        return redirect()->route('fixed-assets.machinery.show', $fixedAsset)->with('success', 'Fixed asset updated successfully.');
    }

    protected function validatedData(Request $request, ?int $assetId = null): array
    {
        return $request->validate([
            'asset_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fixed_assets', 'asset_code')->ignore($assetId)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'machine_type' => ['nullable', 'in:long_term,short_term'],
            'serial_no' => ['nullable', 'string', 'max:255'],
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'location_id' => ['nullable', 'integer'],
            'vendor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'purchase_date' => ['nullable', 'date'],
            'put_to_use_date' => ['nullable', 'date'],
            'opening_wdv' => ['required', 'numeric', 'min:0'],
            'opening_as_of' => ['nullable', 'date'],
            'original_cost' => ['nullable', 'numeric', 'min:0'],
            'accum_dep_opening' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:in_use,idle,sold,scrapped'],
        ]);
    }
}
