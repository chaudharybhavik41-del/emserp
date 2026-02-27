<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaterialTypeRequest;
use App\Http\Requests\UpdateMaterialTypeRequest;
use App\Models\MaterialType;

use Illuminate\Http\Request;
class MaterialTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:core.material_type.view')->only('index');
        $this->middleware('permission:core.material_type.create')->only(['create', 'store']);
        $this->middleware('permission:core.material_type.update')->only(['edit', 'update']);
        $this->middleware('permission:core.material_type.delete')->only('destroy');
    }
    public function index(Request $request)
    {
        $query = MaterialType::withCount('categories');

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
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

        $sortable = ['code', 'name', 'sort_order', 'is_active', 'categories_count', 'created_at'];

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            // Preserve existing behaviour
            $query->orderBy('sort_order')->orderBy('code');
        }

        $types = $query->paginate(25)->withQueryString();

        return view('material_types.index', compact('types'));
    }

    public function create()
    {
        $type = new MaterialType();

        return view('material_types.create', compact('type'));
    }

    public function store(StoreMaterialTypeRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        MaterialType::create($data);

        return redirect()
            ->route('material-types.index')
            ->with('success', 'Material type created successfully.');
    }

    public function edit(MaterialType $material_type)
    {
        $type = $material_type;

        return view('material_types.edit', compact('type'));
    }

    public function update(UpdateMaterialTypeRequest $request, MaterialType $material_type)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $material_type->update($data);

        return redirect()
            ->route('material-types.index')
            ->with('success', 'Material type updated successfully.');
    }

    public function destroy(MaterialType $material_type)
    {
        if ($material_type->categories()->exists()) {
            return redirect()
                ->route('material-types.index')
                ->with('error', 'Cannot delete material type while categories are assigned.');
        }

        $material_type->delete();

        return redirect()
            ->route('material-types.index')
            ->with('success', 'Material type deleted successfully.');
    }
}


