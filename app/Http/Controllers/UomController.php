<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUomRequest;
use App\Http\Requests\UpdateUomRequest;
use App\Models\Uom;

use Illuminate\Http\Request;
class UomController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:core.uom.view')->only(['index']);
        $this->middleware('permission:core.uom.create')->only(['create', 'store']);
        $this->middleware('permission:core.uom.update')->only(['edit', 'update']);
        $this->middleware('permission:core.uom.delete')->only(['destroy']);
    }
    public function index(Request $request)
    {
        $query = Uom::query();

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('category', 'like', $like);
            });
        }

        $status = $request->get('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = ['code', 'name', 'category', 'decimal_places', 'is_active', 'created_at'];

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            // Preserve existing behaviour
            $query->orderBy('code');
        }

        $uoms = $query->paginate(25)->withQueryString();

        return view('uoms.index', compact('uoms'));
    }

    public function create()
    {
        $uom = new Uom();

        return view('uoms.create', compact('uom'));
    }

    public function store(StoreUomRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        Uom::create($data);

        return redirect()
            ->route('uoms.index')
            ->with('success', 'UOM created successfully.');
    }

    public function edit(Uom $uom)
    {
        return view('uoms.edit', compact('uom'));
    }

    public function update(UpdateUomRequest $request, Uom $uom)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $uom->update($data);

        return redirect()
            ->route('uoms.index')
            ->with('success', 'UOM updated successfully.');
    }

    public function destroy(Uom $uom)
    {
        // later we can check if UOM is used in transactions
        $uom->delete();

        return redirect()
            ->route('uoms.index')
            ->with('success', 'UOM deleted successfully.');
    }
}


