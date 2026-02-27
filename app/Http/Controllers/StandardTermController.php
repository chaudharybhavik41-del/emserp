<?php

namespace App\Http\Controllers;

use App\Models\StandardTerm;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StandardTermController extends Controller
{
    public function __construct()
	{
    $this->middleware('can:standard-terms.view')->only(['index', 'show']);
    $this->middleware('can:standard-terms.create')->only(['create', 'store']);
    $this->middleware('can:standard-terms.update')->only(['edit', 'update']);
    $this->middleware('can:standard-terms.delete')->only(['destroy']);
	}

    public function index(Request $request): View
    {
        $module    = $request->get('module');
        $subModule = $request->get('sub_module');

        $query = StandardTerm::query()->orderBy('module')->orderBy('sub_module')->orderBy('sort_order');

        if ($module) {
            $query->where('module', $module);
        }

        if ($subModule) {
            $query->where('sub_module', $subModule);
        }

        $terms = $query->paginate(20);

        return view('standard_terms.index', [
            'terms'      => $terms,
            'module'     => $module,
            'sub_module' => $subModule,
        ]);
    }

    public function create(): View
    {
        return view('standard_terms.create', [
            'term' => new StandardTerm(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        // if a default is selected, reset others in same module/sub_module
        if (!empty($data['is_default'])) {
            StandardTerm::where('module', $data['module'])
                ->where('sub_module', $data['sub_module'])
                ->update(['is_default' => false]);
        }

        StandardTerm::create($data);

        return redirect()
            ->route('standard-terms.index')
            ->with('success', 'Standard Terms template created.');
    }

    public function edit(StandardTerm $standardTerm): View
    {
        return view('standard_terms.edit', [
            'term' => $standardTerm,
        ]);
    }

    public function update(Request $request, StandardTerm $standardTerm): RedirectResponse
    {
        $data = $this->validateData($request, $standardTerm->id);

        if (!empty($data['is_default'])) {
            StandardTerm::where('module', $data['module'])
                ->where('sub_module', $data['sub_module'])
                ->where('id', '!=', $standardTerm->id)
                ->update(['is_default' => false]);
        }

        $standardTerm->update($data);

        return redirect()
            ->route('standard-terms.index')
            ->with('success', 'Standard Terms template updated.');
    }

    public function destroy(StandardTerm $standardTerm): RedirectResponse
    {
        // soft delete style: mark inactive
        $standardTerm->is_active = false;
        $standardTerm->save();

        return redirect()
            ->route('standard-terms.index')
            ->with('success', 'Standard Terms template deactivated.');
    }

    protected function validateData(Request $request, ?int $id = null): array
    {
        $idRule = $id ? ',' . $id : '';

        return $request->validate([
            'code'       => ['required', 'string', 'max:100', 'unique:standard_terms,code' . $idRule],
            'name'       => ['required', 'string', 'max:255'],
            'module'     => ['required', 'string', 'max:100'],
            'sub_module' => ['nullable', 'string', 'max:100'],
            'version'    => ['nullable', 'integer', 'min:1'],
            'is_active'  => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'content'    => ['required', 'string'],
        ], [], [
            'code'    => 'Code',
            'name'    => 'Name',
            'module'  => 'Module',
            'content' => 'Content',
        ]);
    }
}
