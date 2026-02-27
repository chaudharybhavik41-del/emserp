<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdatePartyRequest;
use App\Models\Party;
use Illuminate\Http\Request;

class PartyController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);

        $this->middleware('permission:core.party.view')
            ->only(['index', 'show']);

        $this->middleware('permission:core.party.create')
            ->only(['create', 'store']);

        $this->middleware('permission:core.party.update')
            ->only(['edit', 'update']);

        $this->middleware('permission:core.party.delete')
            ->only(['destroy']);
    }

    /**
     * Display a listing of the parties.
     */
    public function index(Request $request)
    {
        $query = Party::query()->withCount('branches');

        // Type filters (Supplier / Contractor / Client)
        $typeKeys = ['is_supplier', 'is_contractor', 'is_client'];
        $enabledTypes = collect($typeKeys)
            ->filter(fn ($k) => $request->boolean($k))
            ->values()
            ->all();

        if (count($enabledTypes)) {
            $query->where(function ($q) use ($enabledTypes) {
                foreach ($enabledTypes as $key) {
                    $q->orWhere($key, true);
                }
            });
        }

        $search = $request->query('q');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                  ->orWhere('name', 'like', '%' . $search . '%')
                  ->orWhere('legal_name', 'like', '%' . $search . '%')
                  ->orWhere('gstin', 'like', '%' . $search . '%')
                  ->orWhereHas('branches', function ($b) use ($search) {
                      $b->where('gstin', 'like', '%' . $search . '%')
                        ->orWhere('branch_name', 'like', '%' . $search . '%')
                        ->orWhere('state', 'like', '%' . $search . '%')
                        ->orWhere('city', 'like', '%' . $search . '%');
                  });
            });
        }

        $parties = $query->orderBy('code')->paginate(25)->withQueryString();

        return view('parties.index', [
            'parties' => $parties,
            'search'  => $search,
        ]);
    }

    /**
     * Show the form for creating a new party.
     */
    public function create()
    {
        $party = new Party();

        return view('parties.create', compact('party'));
    }

    /**
     * Store a newly created party in storage.
     */
    public function store(StorePartyRequest $request)
    {
        $data = $request->validated();

        $data['is_supplier']   = $request->boolean('is_supplier');
        $data['is_contractor'] = $request->boolean('is_contractor');
        $data['is_client']     = $request->boolean('is_client');
        $data['is_active']     = $request->has('is_active')
            ? $request->boolean('is_active')
            : true;

        // Auto-generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = $this->generatePartyCode($data);
        }

        $party = Party::create($data);

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Party created successfully.');
    }

    /**
     * Display the specified party.
     */
    public function show(Party $party)
    {
        $party->load(['contacts', 'banks', 'branches', 'attachments']);

        return view('parties.show', compact('party'));
    }

    /**
     * Show the form for editing the specified party.
     */
    public function edit(Party $party)
    {
        return view('parties.edit', compact('party'));
    }

    /**
     * Update the specified party in storage.
     */
    public function update(UpdatePartyRequest $request, Party $party)
    {
        $data = $request->validated();

        $data['is_supplier']   = $request->boolean('is_supplier');
        $data['is_contractor'] = $request->boolean('is_contractor');
        $data['is_client']     = $request->boolean('is_client');
        $data['is_active']     = $request->has('is_active')
            ? $request->boolean('is_active')
            : false;

        $party->update($data);

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Party updated successfully.');
    }

    /**
     * Remove the specified party from storage.
     *
     * (Later we can add guards if party is used in Purchase, etc.)
     */
    public function destroy(Party $party)
    {
        $party->delete();

        return redirect()
            ->route('parties.index')
            ->with('success', 'Party deleted successfully.');
    }

    /**
     * Generate auto party code based on type & year.
     *
     * Pattern: SUP-2025-0001 / CON-2025-0001 / CLI-2025-0001 / PTY-2025-0001
     */
    protected function generatePartyCode(array $data): string
    {
        // Decide prefix by type
        $prefix = 'PTY';

        $isSupplier   = !empty($data['is_supplier']);
        $isContractor = !empty($data['is_contractor']);
        $isClient     = !empty($data['is_client']);

        if ($isSupplier && !$isContractor && !$isClient) {
            $prefix = 'SUP';
        } elseif ($isContractor && !$isSupplier && !$isClient) {
            $prefix = 'CON';
        } elseif ($isClient && !$isSupplier && !$isContractor) {
            $prefix = 'CLI';
        }

        $year = now()->format('Y');

        // Look for last code with same prefix + year
        $like = $prefix . '-' . $year . '-%';

        $lastCode = Party::where('code', 'like', $like)
            ->orderByDesc('id')
            ->value('code');

        $nextSeq = 1;

        if ($lastCode) {
            $parts = explode('-', $lastCode);
            $lastSeq = (int) ($parts[2] ?? 0);
            if ($lastSeq > 0) {
                $nextSeq = $lastSeq + 1;
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextSeq);
    }
}



