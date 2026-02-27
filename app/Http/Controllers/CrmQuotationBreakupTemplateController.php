<?php

namespace App\Http\Controllers;

use App\Models\CrmQuotationBreakupTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrmQuotationBreakupTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Single permission to manage breakup templates (view/create/update/delete)
        $this->middleware('permission:crm.quotation.breakup_templates.manage');
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', ''); // active | inactive | '' (all)

        $query = CrmQuotationBreakupTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $templates = $query->paginate(20)->withQueryString();

        return view('crm.quotation_breakup_templates.index', compact('templates', 'q', 'status'));
    }

    /**
     * View/preview a single breakup template.
     */
    public function show(CrmQuotationBreakupTemplate $template): View
    {
        $lines = $this->parseTemplateContentToLines((string) ($template->content ?? ''));

        $basisLabels = [
            'per_unit' => 'Per Unit',
            'lumpsum'  => 'Lumpsum',
            'percent'  => '%',
        ];

        return view('crm.quotation_breakup_templates.show', compact('template', 'lines', 'basisLabels'));
    }

    public function create(): View
    {
        return view('crm.quotation_breakup_templates.create', [
            'template' => new CrmQuotationBreakupTemplate(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        if (!empty($data['is_default'])) {
            CrmQuotationBreakupTemplate::query()->update(['is_default' => false]);
        }

        CrmQuotationBreakupTemplate::create($data);

        return redirect()
            ->route('crm.quotation-breakup-templates.index')
            ->with('success', 'Breakup template created.');
    }

    public function edit(CrmQuotationBreakupTemplate $template): View
    {
        return view('crm.quotation_breakup_templates.edit', compact('template'));
    }

    public function update(Request $request, CrmQuotationBreakupTemplate $template): RedirectResponse
    {
        $data = $this->validatedData($request, $template->id);

        if (!empty($data['is_default'])) {
            CrmQuotationBreakupTemplate::query()
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($data);

        return redirect()
            ->route('crm.quotation-breakup-templates.index')
            ->with('success', 'Breakup template updated.');
    }

    /**
     * Deactivate (soft) so it won’t appear in quotation screens.
     */
    public function destroy(CrmQuotationBreakupTemplate $template): RedirectResponse
    {
        $template->update(['is_active' => false]);

        return redirect()
            ->route('crm.quotation-breakup-templates.index')
            ->with('success', 'Breakup template deactivated.');
    }

    protected function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:crm_quotation_breakup_templates,code';
        if ($ignoreId) {
            $unique .= ',' . $ignoreId;
        }

        $baseRules = [
            'code'       => ['required', 'string', 'max:100', $unique],
            'name'       => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'is_active'  => ['nullable', 'boolean'],
        ];

        // New UI (table) posts breakup_lines[][name|basis|rate]
        $hasLines = $request->has('breakup_lines') && is_array($request->input('breakup_lines'));
        if ($hasLines) {
            $validated = $request->validate(array_merge($baseRules, [
                'breakup_lines'           => ['required', 'array', 'min:1'],
                'breakup_lines.*.name'    => ['required', 'string', 'max:255', 'regex:/\S/'],
                'breakup_lines.*.basis'   => ['nullable', 'string', 'in:per_unit,lumpsum,percent'],
                'breakup_lines.*.rate'    => ['nullable', 'numeric', 'min:0'],
            ]));

            $validated['content'] = $this->buildContentFromLines((array) ($validated['breakup_lines'] ?? []));

            if (trim((string) $validated['content']) === '') {
                throw ValidationException::withMessages([
                    'breakup_lines' => 'At least one component line is required.',
                ]);
            }

            unset($validated['breakup_lines']);

            return $validated;
        }

        // Backward compatibility: accept raw "content" textarea.
        return $request->validate(array_merge($baseRules, [
            'content' => ['required', 'string'],
        ]));
    }

    protected function normalizeBasis(?string $basis): string
    {
        $basis = strtolower(trim((string) $basis));

        if ($basis === '' || $basis === 'per_unit' || $basis === 'perunit' || $basis === 'unit' || $basis === 'per') {
            return 'per_unit';
        }

        if (in_array($basis, ['lumpsum', 'lump', 'ls', 'lump_sum', 'lump sum'], true)) {
            return 'lumpsum';
        }

        if (in_array($basis, ['percent', '%', 'percentage', 'pct'], true)) {
            return 'percent';
        }

        return 'per_unit';
    }

    protected function formatRate($rate): string
    {
        $num = is_numeric($rate) ? (float) $rate : 0.0;
        if ($num < 0) {
            $num = 0.0;
        }

        // Keep up to 4 decimals, trim trailing zeros.
        $s = number_format($num, 4, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');

        return $s === '' ? '0' : $s;
    }

    /**
     * Convert persisted "content" into an array of lines for a read-only preview.
     * Supports both:
     *  - Name|basis|rate
     *  - Name (basis/rate defaulted)
     */
    protected function parseTemplateContentToLines(string $content): array
    {
        $out = [];

        $lines = preg_split("/\r\n|\n|\r/", (string) $content) ?: [];

        foreach ($lines as $line) {
            $l = trim((string) $line);
            if ($l === '') {
                continue;
            }

            // allow comments
            if (str_starts_with($l, '#') || str_starts_with($l, '//')) {
                continue;
            }

            // strip bullets / numbering
            $l = preg_replace('/^\s*[\-\*\x{2022}]\s*/u', '', $l);
            $l = preg_replace('/^\s*\d+[\)\.]\s*/u', '', $l);

            $parts = array_map('trim', explode('|', $l));
            $name  = (string) ($parts[0] ?? '');

            if (trim($name) === '') {
                continue;
            }

            $basis = $this->normalizeBasis($parts[1] ?? 'per_unit');
            $rate  = $this->formatRate($parts[2] ?? 0);

            $out[] = [
                'name'  => $name,
                'basis' => $basis,
                'rate'  => $rate,
            ];
        }

        return $out;
    }

    /**
     * Convert table rows into the persisted "content" format:
     *   Name|basis|rate
     * one line per row (used by the quotation breakup modal parser).
     */
    protected function buildContentFromLines(array $lines): string
    {
        $out = [];

        foreach ($lines as $line) {
            $name = trim((string) ($line['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $basis = $this->normalizeBasis($line['basis'] ?? 'per_unit');
            $rate  = $this->formatRate($line['rate'] ?? 0);

            $out[] = $name . '|' . $basis . '|' . $rate;
        }

        return implode("\n", $out);
    }
}
