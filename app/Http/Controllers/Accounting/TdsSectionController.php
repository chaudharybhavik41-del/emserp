<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\TdsSection;
use App\Models\ClientRaBill;
use App\Models\PurchaseBill;
use App\Models\SubcontractorRaBill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class TdsSectionController extends Controller
{
    public function __construct()
    {
        // Reuse accounting accounts permissions (simple)
        $this->middleware('permission:accounting.accounts.view')->only(['index']);
        $this->middleware('permission:accounting.accounts.update')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $q = trim((string) $request->string('q'));

        $query = TdsSection::query()
            ->where('company_id', $companyId)
            ->orderBy('code');

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('code', 'like', '%' . $q . '%')
                    ->orWhere('name', 'like', '%' . $q . '%')
                    ->orWhere('description', 'like', '%' . $q . '%');
            });
        }

        $sections = $query->paginate(25)->withQueryString();

        return view('accounting.tds_sections.index', [
            'companyId' => $companyId,
            'q'         => $q,
            'sections'  => $sections,
        ]);
    }

    public function create()
    {
        $companyId = $this->defaultCompanyId();

        $section = new TdsSection([
            'company_id'   => $companyId,
            'is_active'    => true,
            'default_rate' => 0,
        ]);

        return view('accounting.tds_sections.create', [
            'companyId' => $companyId,
            'section'   => $section,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $data = $request->validate([
            'code'         => [
                'required',
                'string',
                'max:20',
                Rule::unique('tds_sections', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name'         => ['required', 'string', 'max:150'],
            'description'  => ['nullable', 'string', 'max:500'],
            'default_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $section = new TdsSection();
        $section->company_id   = $companyId;
        $section->code         = strtoupper(trim($data['code']));
        $section->name         = trim($data['name']);
        $section->description  = $data['description'] ? trim($data['description']) : null;
        $section->default_rate = (float) $data['default_rate'];
        $section->is_active    = (bool) ($data['is_active'] ?? false);
        $section->save();

        return redirect()
            ->route('accounting.tds-sections.index')
            ->with('success', 'TDS section created.');
    }

    public function edit(TdsSection $tdsSection)
    {
        $companyId = $this->defaultCompanyId();

        if ((int) $tdsSection->company_id !== $companyId) {
            abort(404);
        }

        return view('accounting.tds_sections.edit', [
            'companyId' => $companyId,
            'section'   => $tdsSection,
        ]);
    }

    public function update(Request $request, TdsSection $tdsSection)
    {
        $companyId = $this->defaultCompanyId();

        if ((int) $tdsSection->company_id !== $companyId) {
            abort(404);
        }

        $data = $request->validate([
            'code'         => [
                'required',
                'string',
                'max:20',
                Rule::unique('tds_sections', 'code')
                    ->ignore($tdsSection->id)
                    ->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name'         => ['required', 'string', 'max:150'],
            'description'  => ['nullable', 'string', 'max:500'],
            'default_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $tdsSection->code         = strtoupper(trim($data['code']));
        $tdsSection->name         = trim($data['name']);
        $tdsSection->description  = $data['description'] ? trim($data['description']) : null;
        $tdsSection->default_rate = (float) $data['default_rate'];
        $tdsSection->is_active    = (bool) ($data['is_active'] ?? false);
        $tdsSection->save();

        return redirect()
            ->route('accounting.tds-sections.index')
            ->with('success', 'TDS section updated.');
    }

    public function destroy(TdsSection $tdsSection)
    {
        $companyId = $this->defaultCompanyId();

        if ((int) $tdsSection->company_id !== $companyId) {
            abort(404);
        }

        $code = (string) $tdsSection->code;

        $inUse = PurchaseBill::query()
            ->where('company_id', $companyId)
            ->where('tds_section', $code)
            ->exists();

        if (! $inUse) {
            $inUse = SubcontractorRaBill::query()
                ->where('company_id', $companyId)
                ->where('tds_section', $code)
                ->exists();
        }

        if (! $inUse) {
            $inUse = ClientRaBill::query()
                ->where('company_id', $companyId)
                ->where('tds_section', $code)
                ->exists();
        }

        if ($inUse) {
            return redirect()
                ->route('accounting.tds-sections.index')
                ->with('error', 'Cannot delete. This TDS section is already used in bills.');
        }

        $tdsSection->delete();

        return redirect()
            ->route('accounting.tds-sections.index')
            ->with('success', 'TDS section deleted.');
    }
}
