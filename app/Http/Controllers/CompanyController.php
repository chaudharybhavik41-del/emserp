<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;

use Illuminate\Http\Request;
class CompanyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:core.company.view')->only(['index', 'edit']);
        $this->middleware('permission:core.company.update')->only(['create', 'store', 'update']);
    }
    public function index(Request $request)
    {
        $query = Company::query();

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('legal_name', 'like', $like)
                    ->orWhere('gst_number', 'like', $like)
                    ->orWhere('pan_number', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        $status = $request->get('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $default = $request->get('default');
        if ($default === 'default') {
            $query->where('is_default', true);
        } elseif ($default === 'non_default') {
            $query->where('is_default', false);
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = ['code', 'name', 'gst_number', 'is_default', 'is_active', 'created_at'];

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            // Preserve existing behaviour
            $query->orderBy('name');
        }

        $companies = $query->paginate(25)->withQueryString();

        return view('companies.index', compact('companies'));
    }

    public function create()
    {
        $company = new Company();

        return view('companies.create', compact('company'));
    }

    public function store(StoreCompanyRequest $request)
    {
        $data = $request->validated();
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active');

        if ($data['is_default']) {
            Company::where('is_default', true)->update(['is_default' => false]);
        }

        Company::create($data);

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company created successfully.');
    }

    public function edit(Company $company)
    {
        return view('companies.edit', compact('company'));
    }

    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $data = $request->validated();
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active');

        if ($data['is_default']) {
            Company::where('is_default', true)
                ->where('id', '!=', $company->id)
                ->update(['is_default' => false]);
        }

        $company->update($data);

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company updated successfully.');
    }
}


