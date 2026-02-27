<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaterialSubcategoryRequest;
use App\Http\Requests\UpdateMaterialSubcategoryRequest;
use App\Models\Accounting\Account;
use App\Models\MaterialCategory;
use App\Models\MaterialSubcategory;

use Illuminate\Http\Request;
class MaterialSubcategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:core.material_subcategory.view')->only('index');
        $this->middleware('permission:core.material_subcategory.create')->only(['create', 'store']);
        $this->middleware('permission:core.material_subcategory.update')->only(['edit', 'update']);
        $this->middleware('permission:core.material_subcategory.delete')->only('destroy');
    }
    public function index(Request $request)
    {
        $categories = MaterialCategory::with('type')
            ->orderBy('material_type_id')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $query = MaterialSubcategory::with(['category.type', 'expenseAccount']);

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('category', function ($cq) use ($like) {
                        $cq->where('code', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
            });
        }

        $categoryId = $request->get('material_category_id');
        if ($categoryId !== null && $categoryId !== '') {
            $query->where('material_category_id', (int) $categoryId);
        }

        $status = $request->get('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = ['material_category_id', 'code', 'name', 'sort_order', 'is_active', 'created_at'];

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            // Preserve existing behaviour
            $query->orderBy('material_category_id')
                ->orderBy('sort_order')
                ->orderBy('code');
        }

        $subcategories = $query->paginate(25)->withQueryString();

        return view('material_subcategories.index', compact('subcategories', 'categories'));
    }

    public function create()
    {
        $subcategory = new MaterialSubcategory();

        $categories = MaterialCategory::with('type')
            ->orderBy('material_type_id')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $accounts = Account::orderBy('name')->get();

        return view('material_subcategories.create', compact('subcategory', 'categories', 'accounts'));
    }

    public function store(StoreMaterialSubcategoryRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        // Normalise prefix (2–3 letters suggested)
        if (! empty($data['item_code_prefix'])) {
            $data['item_code_prefix'] = $this->normalisePrefix($data['item_code_prefix']);
        }

        // Always auto-generate code; ignore any incoming 'code'
        $category = MaterialCategory::findOrFail($data['material_category_id']);
        $data['code'] = $this->generateSubcategoryCode($category);

        MaterialSubcategory::create($data);

        return redirect()
            ->route('material-subcategories.index')
            ->with('success', 'Material subcategory created successfully.');
    }

    public function edit(MaterialSubcategory $material_subcategory)
    {
        $subcategory = $material_subcategory;

        $categories = MaterialCategory::with('type')
            ->orderBy('material_type_id')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $accounts = Account::orderBy('name')->get();

        return view('material_subcategories.edit', compact('subcategory', 'categories', 'accounts'));
    }

    public function update(UpdateMaterialSubcategoryRequest $request, MaterialSubcategory $material_subcategory)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        if (! empty($data['item_code_prefix'])) {
            $data['item_code_prefix'] = $this->normalisePrefix($data['item_code_prefix']);
        }

        // Never allow code change via UI
        $data['code'] = $material_subcategory->code;

        $material_subcategory->update($data);

        return redirect()
            ->route('material-subcategories.index')
            ->with('success', 'Material subcategory updated successfully.');
    }

    public function destroy(MaterialSubcategory $material_subcategory)
    {
        // later: guard if linked to items
        $material_subcategory->delete();

        return redirect()
            ->route('material-subcategories.index')
            ->with('success', 'Material subcategory deleted successfully.');
    }

    /**
     * CATEGORY_CODE-XXX pattern, unique within category.
     */
    protected function generateSubcategoryCode(MaterialCategory $category): string
    {
        $prefix = $category->code;

        $base = $prefix . '-';

        // Consider only codes that look like PREFIX-### (3-part max)
        $existingCodes = MaterialSubcategory::where('material_category_id', $category->id)
            ->where('code', 'like', $base . '%')
            ->pluck('code');

        $maxSeq = 0;
        foreach ($existingCodes as $code) {
            $parts = explode('-', $code);
            // We only consider codes like CAT-001, ignore older custom formats
            if (count($parts) === 2) {
                $last = $parts[1];
                if (ctype_digit($last)) {
                    $maxSeq = max($maxSeq, (int) $last);
                }
            }
        }

        $nextSeq = $maxSeq + 1;

        return sprintf('%s-%03d', $prefix, $nextSeq);
    }

    protected function normalisePrefix(string $prefix): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($prefix));

        // Limit length (2-5 chars practical)
        return mb_substr($normalized, 0, 5);
    }
}


