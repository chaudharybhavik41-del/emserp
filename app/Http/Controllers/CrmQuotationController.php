<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCrmQuotationRequest;
use App\Http\Requests\UpdateCrmQuotationRequest;
use App\Models\Company;
use App\Models\CrmLead;
use App\Models\CrmLeadStage;
use App\Models\CrmQuotation;
use App\Models\CrmQuotationBreakupTemplate;
use App\Models\Item;
use App\Models\MailTemplate;
use App\Models\Party;
use App\Models\Uom;
use App\Models\StandardTerm;
use App\Services\CrmQuotationPricingService;
use App\Services\MailService;
use App\Services\ProjectService;
use App\Services\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmQuotationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:crm.quotation.view')
            ->only(['index', 'show', 'pdf']);

        $this->middleware('permission:crm.quotation.create')
            ->only(['create', 'createForLead', 'store', 'storeForLead']);

        $this->middleware('permission:crm.quotation.update')
            ->only(['edit', 'update', 'revise', 'emailForm', 'sendEmail']);

        $this->middleware('permission:crm.quotation.delete')
            ->only(['destroy']);

        $this->middleware('permission:crm.quotation.accept')
            ->only(['accept']);
    }

    /**
     * Generate sequential quotation code: QTN-YYYY-0001
     */
    protected function generateQuotationCode(): string
    {
        $year   = now()->format('Y');
        $prefix = "QTN-{$year}";

        $last = CrmQuotation::where('code', 'like', "{$prefix}-%")
            ->orderBy('code', 'desc')
            ->first();

        $nextSeq = 1;
        if ($last) {
            $nextSeq = (int) substr($last->code, -4) + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    protected function resolveCompanyForPdf(SettingsService $settings): ?Company
    {
        $defaultCompanyId = $settings->get('general', 'default_company_id', null);

        $companyQuery = Company::query();
        $company = null;

        if ($defaultCompanyId) {
            $company = $companyQuery->find($defaultCompanyId);
        }

        if (! $company) {
            $company = $companyQuery->where('is_default', true)->first()
                ?? $companyQuery->first();
        }

        return $company;
    }

    /**
     * List quotations with filters.
     */
    public function index(Request $request)
    {
        $query = CrmQuotation::with(['lead', 'party']);

        // Filter by quotation code
        if ($code = trim($request->get('code', ''))) {
            $query->where('code', 'like', "%{$code}%");
        }

        // Filter by status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filter by client (party_id)
        if ($clientId = $request->get('party_id')) {
            $query->where('party_id', $clientId);
        }

        $quotations = $query
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Clients list for the filter dropdown
        $clients = Party::where('is_client', true)
            ->orderBy('name')
            ->get();

        return view('crm.quotations.index', compact('quotations', 'clients'));
    }

    /**
     * Generic /crm/quotations/create.
     * We force users to start from a Lead.
     */
    public function create()
    {
        return redirect()
            ->route('crm.leads.index')
            ->with('error', 'Please open a Lead to create a quotation.');
    }

    /**
     * Create quotation FOR a specific lead.
     * Route: /crm/leads/{lead}/quotations/create
     */
    public function createForLead(CrmLead $lead)
    {
        // Ensure _form has a $quotation variable (create view includes only _form)
        $quotation = new CrmQuotation();
        $quotation->party_id = $lead->party_id;
        $quotation->project_name = $lead->title ?? '';
        $quotation->quote_mode = 'item';
        $quotation->is_rate_only = false;
        $quotation->profit_percent = 0;

        $clients = Party::where('is_client', true)->orderBy('name')->get();
        $items   = Item::orderBy('code')->get();
        $uoms    = Uom::orderBy('code')->get();

        $paymentTermsDaysOptions = [7, 10, 15, 30, 45, 60, 90];

        
        // Standard Terms templates (module: sales, sub_module: quotation)
        $standardTerms = collect();
        $defaultStandardTerm = null;

        // Breakup Templates (CRM Quotation module)
        $breakupTemplates = collect();
        $defaultBreakupTemplate = null;

        if (Schema::hasTable('standard_terms') && class_exists(StandardTerm::class)) {
            $standardTerms = StandardTerm::query()
                ->where('module', 'sales')
                ->where('sub_module', 'quotation')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $defaultStandardTerm = $standardTerms->firstWhere('is_default', true)
                ?? $standardTerms->first();
        }

        if (Schema::hasTable('crm_quotation_breakup_templates') && class_exists(CrmQuotationBreakupTemplate::class)) {
            $breakupTemplates = CrmQuotationBreakupTemplate::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $defaultBreakupTemplate = $breakupTemplates->firstWhere('is_default', true)
                ?? $breakupTemplates->first();
        }

        return view('crm.quotations.create', compact(
            'quotation',
            'lead',
            'clients',
            'items',
            'uoms',
            'paymentTermsDaysOptions',
            'standardTerms',
            'defaultStandardTerm',
            'breakupTemplates',
            'defaultBreakupTemplate'
        ));
    }


    /**
     * Core store logic for a lead's quotation.
     */
    public function store(StoreCrmQuotationRequest $request, CrmLead $lead)
    {
        $data = $request->validated();

        $pricingService = app(CrmQuotationPricingService::class);

        $code = ($data['code'] ?? null) ?: $this->generateQuotationCode();

        $quoteMode     = $data['quote_mode'] ?? 'item';
        $isRateOnly    = (bool) ($data['is_rate_only'] ?? false);
        $profitPercent = (float) ($data['profit_percent'] ?? 0);

        $standardTermId = $data['standard_term_id'] ?? null;
        $termsText = $data['terms_text'] ?? null;

        if ($standardTermId && empty($termsText) && Schema::hasTable('standard_terms') && class_exists(StandardTerm::class)) {
            $term = StandardTerm::find($standardTermId);
            if ($term) {
                $termsText = $term->content;
            }
        }

        $quotation = null;

        DB::transaction(function () use (
            $lead,
            $data,
            $code,
            $request,
            $pricingService,
            $quoteMode,
            $isRateOnly,
            $profitPercent,
            $standardTermId,
            $termsText,
            &$quotation
        ) {
            $createData = [
                'code'                 => $code,
                'revision_no'          => 0,
                'lead_id'              => $lead->id,
                'party_id'             => $data['party_id'] ?? $lead->party_id,
                'project_name'         => $data['project_name'],
                'client_po_number'     => $data['client_po_number'] ?? null,

                'quote_mode'           => $quoteMode,
                'is_rate_only'         => $isRateOnly,
                'profit_percent'       => $profitPercent,

                'scope_of_work'        => $data['scope_of_work'] ?? null,
                'exclusions'           => $data['exclusions'] ?? null,

                'status'               => 'draft',
                'valid_till'           => $data['valid_till'] ?? null,

                'payment_terms'        => $data['payment_terms'] ?? null,
                'payment_terms_days'   => $data['payment_terms_days'] ?? null,
                'freight_terms'        => $data['freight_terms'] ?? null,
                'delivery_terms'       => $data['delivery_terms'] ?? null,
                'other_terms'          => $data['other_terms'] ?? null,
                'project_special_notes'=> $data['project_special_notes'] ?? null,

                'created_by'           => $request->user()->id,
            ];

            if (Schema::hasColumn('crm_quotations', 'standard_term_id')) {
                $createData['standard_term_id'] = $standardTermId;
            }
            if (Schema::hasColumn('crm_quotations', 'terms_text')) {
                $createData['terms_text'] = $termsText;
            }

            $quotation = CrmQuotation::create($createData);

            $total = 0;

            foreach (($data['items'] ?? []) as $line) {
                $quantity  = (float) ($line['quantity'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);

                $directCostUnit = 0.0;

                $componentsRaw = [];
                if (! empty($line['cost_breakup_json'])) {
                    $decoded = json_decode((string) $line['cost_breakup_json'], true);
                    if (is_array($decoded)) {
                        $componentsRaw = $decoded;
                    }
                }

                if (! empty($componentsRaw)) {
                    $calc = $pricingService->calculate($quantity, $componentsRaw, $profitPercent);
                    $unitPrice = (float) $calc['sell_unit_price'];
                    $directCostUnit = (float) $calc['direct_cost_unit'];
                }

                $lineTotal = ($quoteMode === 'rate_per_kg' && $isRateOnly)
                    ? 0.0
                    : ($quantity * $unitPrice);

                $item = $quotation->items()->create([
                    'item_id'          => $line['item_id'] ?? null,
                    'description'      => $line['description'],
                    'quantity'         => $quantity,
                    'uom_id'           => $line['uom_id'] ?? null,
                    'unit_price'       => $unitPrice,
                    'direct_cost_unit' => $directCostUnit,
                    'line_total'       => $lineTotal,
                    'sort_order'       => $line['sort_order'] ?? 0,
                ]);

                // Persist cost breakup rows (normalized)
                if (! empty($componentsRaw)) {
                    $normalized = $pricingService->normalizeComponents($componentsRaw);

                    foreach ($normalized as $idx => $comp) {
                        $item->costBreakups()->create([
                            'component_code' => $comp['code'] ?? null,
                            'component_name' => $comp['name'],
                            'basis'          => $comp['basis'],
                            'rate'           => $comp['rate'],
                            'sort_order'     => $idx,
                        ]);
                    }
                }

                $total += $lineTotal;
            }

            if ($quoteMode === 'rate_per_kg' && $isRateOnly) {
                // Rate-only offer: do not compute totals
                $quotation->total_amount = 0;
                $quotation->tax_amount   = 0;
                $quotation->grand_total  = 0;
            } else {
                $quotation->total_amount = $total;
                $quotation->tax_amount   = 0; // adjust when you add tax logic
                $quotation->grand_total  = $total;
            }

            $quotation->save();
        });

        return redirect()
            ->route('crm.quotations.show', $quotation)
            ->with('success', 'Quotation created successfully.');
    }

    /**
     * Wrapper for lead-scoped route.
     */
    public function storeForLead(StoreCrmQuotationRequest $request, CrmLead $lead)
    {
        return $this->store($request, $lead);
    }

    public function show(CrmQuotation $quotation)
    {
        $quotation->load(['lead', 'party', 'standardTerm', 'items.item', 'items.uom', 'items.costBreakups']);

        $pricingService = app(CrmQuotationPricingService::class);

        $itemCalcs = [];
        foreach ($quotation->items as $line) {
            if ($line->costBreakups->count() === 0) {
                continue;
            }

            $components = $line->costBreakups->map(function ($cb) {
                return [
                    'code'  => $cb->component_code,
                    'name'  => $cb->component_name,
                    'basis' => $cb->basis,
                    'rate'  => (float) $cb->rate,
                ];
            })->toArray();

            $itemCalcs[$line->id] = $pricingService->calculate(
                (float) $line->quantity,
                $components,
                (float) ($quotation->profit_percent ?? 0)
            );
        }

        return view('crm.quotations.show', compact('quotation', 'itemCalcs'));
    }

    public function edit(CrmQuotation $quotation)
    {
        // Guard: only DRAFT or SENT are editable
        if (! in_array($quotation->status, ['draft', 'sent'], true)) {
            return redirect()
                ->route('crm.quotations.show', $quotation)
                ->with(
                    'error',
                    'Only DRAFT or SENT quotations can be edited. This quotation is already ' . strtoupper($quotation->status) . '.'
                );
        }

        $quotation->load(['items.costBreakups']);

        $lead    = $quotation->lead;
        $clients = Party::where('is_client', true)->orderBy('name')->get();
        $items   = Item::orderBy('code')->get();
        $uoms    = Uom::orderBy('code')->get();

        $paymentTermsDaysOptions = [7, 10, 15, 30, 45, 60, 90];


        
        // Standard Terms templates (module: sales, sub_module: quotation)
        $standardTerms = collect();
        $defaultStandardTerm = null;

        // Breakup Templates (CRM Quotation module)
        $breakupTemplates = collect();
        $defaultBreakupTemplate = null;

        if (Schema::hasTable('standard_terms') && class_exists(StandardTerm::class)) {
            $standardTerms = StandardTerm::query()
                ->where('module', 'sales')
                ->where('sub_module', 'quotation')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $defaultStandardTerm = $standardTerms->firstWhere('is_default', true)
                ?? $standardTerms->first();
        }

        if (Schema::hasTable('crm_quotation_breakup_templates') && class_exists(CrmQuotationBreakupTemplate::class)) {
            $breakupTemplates = CrmQuotationBreakupTemplate::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $defaultBreakupTemplate = $breakupTemplates->firstWhere('is_default', true)
                ?? $breakupTemplates->first();
        }

        return view('crm.quotations.edit', compact(
            'quotation',
            'lead',
            'clients',
            'items',
            'uoms',
            'paymentTermsDaysOptions',
            'standardTerms',
            'defaultStandardTerm',
            'breakupTemplates',
            'defaultBreakupTemplate'
        ));
    }

    public function update(UpdateCrmQuotationRequest $request, CrmQuotation $quotation)
    {
        if (! in_array($quotation->status, ['draft', 'sent'], true)) {
            return redirect()
                ->route('crm.quotations.show', $quotation)
                ->with(
                    'error',
                    'Only DRAFT or SENT quotations can be updated. This quotation is already ' . strtoupper($quotation->status) . '.'
                );
        }

        $data = $request->validated();

        $pricingService = app(CrmQuotationPricingService::class);

        $quoteMode     = $data['quote_mode'] ?? ($quotation->quote_mode ?? 'item');
        $isRateOnly    = (bool) ($data['is_rate_only'] ?? false);
        $profitPercent = (float) ($data['profit_percent'] ?? ($quotation->profit_percent ?? 0));


        $standardTermId = $data['standard_term_id'] ?? null;
        $termsText = $data['terms_text'] ?? null;

        if ($standardTermId && empty($termsText) && Schema::hasTable('standard_terms') && class_exists(StandardTerm::class)) {
            $term = StandardTerm::find($standardTermId);
            if ($term) {
                $termsText = $term->content;
            }
        }


        DB::transaction(function () use ($quotation, $data, $pricingService, $quoteMode, $isRateOnly, $profitPercent, $standardTermId, $termsText) {
            $updateData = [
                'project_name'          => $data['project_name'],
                'party_id'              => $data['party_id'] ?? $quotation->party_id,
                'client_po_number'      => $data['client_po_number'] ?? $quotation->client_po_number,

                'quote_mode'            => $quoteMode,
                'is_rate_only'          => $isRateOnly,
                'profit_percent'        => $profitPercent,

                'scope_of_work'         => $data['scope_of_work'] ?? $quotation->scope_of_work,
                'exclusions'            => $data['exclusions'] ?? $quotation->exclusions,

                'valid_till'            => $data['valid_till'] ?? null,

                'payment_terms'         => $data['payment_terms'] ?? null,
                'payment_terms_days'    => $data['payment_terms_days'] ?? null,
                'freight_terms'         => $data['freight_terms'] ?? null,
                'delivery_terms'        => $data['delivery_terms'] ?? null,
                'other_terms'           => $data['other_terms'] ?? null,
                'project_special_notes' => $data['project_special_notes'] ?? null,
            ];

            if (Schema::hasColumn('crm_quotations', 'standard_term_id')) {
                $updateData['standard_term_id'] = $standardTermId;
            }
            if (Schema::hasColumn('crm_quotations', 'terms_text')) {
                $updateData['terms_text'] = $termsText;
            }

            $quotation->update($updateData);

            // Simple strategy: delete all and re-insert lines (cost breakups cascade)
            $quotation->items()->delete();

            $total = 0;

            foreach (($data['items'] ?? []) as $line) {
                $quantity  = (float) ($line['quantity'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);

                $directCostUnit = 0.0;

                $componentsRaw = [];
                if (! empty($line['cost_breakup_json'])) {
                    $decoded = json_decode((string) $line['cost_breakup_json'], true);
                    if (is_array($decoded)) {
                        $componentsRaw = $decoded;
                    }
                }

                if (! empty($componentsRaw)) {
                    $calc = $pricingService->calculate($quantity, $componentsRaw, $profitPercent);
                    $unitPrice = (float) $calc['sell_unit_price'];
                    $directCostUnit = (float) $calc['direct_cost_unit'];
                }

                $lineTotal = ($quoteMode === 'rate_per_kg' && $isRateOnly)
                    ? 0.0
                    : ($quantity * $unitPrice);

                $item = $quotation->items()->create([
                    'item_id'          => $line['item_id'] ?? null,
                    'description'      => $line['description'],
                    'quantity'         => $quantity,
                    'uom_id'           => $line['uom_id'] ?? null,
                    'unit_price'       => $unitPrice,
                    'direct_cost_unit' => $directCostUnit,
                    'line_total'       => $lineTotal,
                    'sort_order'       => $line['sort_order'] ?? 0,
                ]);

                if (! empty($componentsRaw)) {
                    $normalized = $pricingService->normalizeComponents($componentsRaw);

                    foreach ($normalized as $idx => $comp) {
                        $item->costBreakups()->create([
                            'component_code' => $comp['code'] ?? null,
                            'component_name' => $comp['name'],
                            'basis'          => $comp['basis'],
                            'rate'           => $comp['rate'],
                            'sort_order'     => $idx,
                        ]);
                    }
                }

                $total += $lineTotal;
            }

            if ($quoteMode === 'rate_per_kg' && $isRateOnly) {
                $quotation->total_amount = 0;
                $quotation->tax_amount   = 0;
                $quotation->grand_total  = 0;
            } else {
                $quotation->total_amount = $total;
                $quotation->tax_amount   = 0;
                $quotation->grand_total  = $total;
            }

            $quotation->save();
        });

        return redirect()
            ->route('crm.quotations.show', $quotation)
            ->with('success', 'Quotation updated successfully.');
    }

    /**
     * Create a new revision of a quotation:
     * - current one becomes SUPERSEDED (read-only)
     * - new one starts as DRAFT
     */
    public function revise(Request $request, CrmQuotation $quotation)
    {
        // Only draft or sent quotations can be revised
        if (! in_array($quotation->status, ['draft', 'sent'], true)) {
            return redirect()
                ->route('crm.quotations.show', $quotation)
                ->with(
                    'error',
                    'Only DRAFT or SENT quotations can be revised. This quotation is already ' . strtoupper($quotation->status) . '.'
                );
        }

        $data = $request->validate([
            'revision_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $quotation->load(['items.costBreakups']);

        $latestRevision = CrmQuotation::where('code', $quotation->code)
            ->orderByDesc('revision_no')
            ->first();

        $newRevisionNo = $latestRevision ? ((int) $latestRevision->revision_no + 1) : 1;

        $newQuotation = null;

        DB::transaction(function () use ($quotation, $data, $newRevisionNo, &$newQuotation) {
            // Mark current as superseded
            $quotation->update([
                'status'          => 'superseded',
                'revision_reason' => $data['revision_reason'] ?? null,
                'rejected_at'     => now(),
            ]);

            // Clone quotation
            $newQuotation = $quotation->replicate([
                'status',
                'revision_no',
                'sent_at',
                'accepted_at',
                'rejected_at',
                'approved_by',
                'created_at',
                'updated_at',
            ]);

            $newQuotation->revision_no      = $newRevisionNo;
            $newQuotation->status           = 'draft';
            $newQuotation->revision_reason  = null;
            $newQuotation->sent_at          = null;
            $newQuotation->accepted_at      = null;
            $newQuotation->rejected_at      = null;
            $newQuotation->approved_by      = null;
            $newQuotation->created_at       = now();
            $newQuotation->updated_at       = now();
            $newQuotation->save();

            // Clone items + their cost breakups
            foreach ($quotation->items as $item) {
                $newItem = $item->replicate([
                    'created_at',
                    'updated_at',
                ]);

                $newItem->quotation_id = $newQuotation->id;
                $newItem->created_at   = now();
                $newItem->updated_at   = now();
                $newItem->save();

                foreach ($item->costBreakups as $cb) {
                    $newCb = $cb->replicate([
                        'created_at',
                        'updated_at',
                    ]);

                    $newCb->quotation_item_id = $newItem->id;
                    $newCb->created_at        = now();
                    $newCb->updated_at        = now();
                    $newCb->save();
                }
            }
        });

        return redirect()
            ->route('crm.quotations.show', $newQuotation)
            ->with('success', "Revision R{$newRevisionNo} created successfully.");
    }

    public function destroy(CrmQuotation $quotation)
    {
        $quotation->delete();

        return redirect()
            ->route('crm.quotations.index')
            ->with('success', 'Quotation deleted successfully.');
    }

    /**
     * Accept quotation, create/attach project, and mark related lead as WON.
     */
    public function accept(CrmQuotation $quotation, ProjectService $projectService)
    {
        $lead = $quotation->lead;

        // 1) Only allow accepting DRAFT or SENT quotations
        if (! in_array($quotation->status, ['draft', 'sent'], true)) {
            return redirect()
                ->route('crm.quotations.show', $quotation)
                ->with('error', 'Only DRAFT or SENT quotations can be accepted.');
        }

        // 2) Lead must still be OPEN
        if ($lead && $lead->status !== 'open') {
            return redirect()
                ->route('crm.quotations.show', $quotation)
                ->with(
                    'error',
                    'Cannot accept this quotation because the related lead is already '
                    . strtoupper($lead->status) . '.'
                );
        }

        DB::transaction(function () use ($quotation, $projectService) {
            // Mark this quotation as accepted
            $quotation->status      = 'accepted';
            $quotation->accepted_at = now();
            $quotation->save();

            // Mark other revisions of same code as superseded
            CrmQuotation::where('code', $quotation->code)
                ->where('id', '!=', $quotation->id)
                ->update(['status' => 'superseded']);

            // Mark related lead as WON (closed)
            $lead = $quotation->lead;
            if ($lead) {
                $wonStage = CrmLeadStage::where('is_active', true)
                    ->where('is_won', true)
                    ->orderBy('sort_order')
                    ->first();

                if ($wonStage) {
                    $lead->lead_stage_id = $wonStage->id;
                }

                $lead->status      = 'won';
                $lead->probability = 100;
                $lead->save();
            }

            // Create or attach project from this quotation
            $projectService->createFromQuotation($quotation);
        });

        return redirect()
            ->route('crm.quotations.show', $quotation)
            ->with('success', 'Quotation accepted, project created/linked and related lead marked as WON.');
    }

    public function pdf(CrmQuotation $quotation, SettingsService $settings)
    {
        $quotation->load(['items.item', 'items.uom', 'party', 'lead', 'standardTerm']);

        $company = $this->resolveCompanyForPdf($settings);

        $terms = $settings->get('sales', 'quotation_terms', null);

        $pdf = Pdf::loadView('crm.quotations.pdf', [
            'quotation' => $quotation,
            'company'   => $company,
            'terms'     => $terms,
        ])->setPaper('a4');

        $fileName = $quotation->code . '-rev' . $quotation->revision_no . '.pdf';

        return $pdf->stream($fileName);
    }

    /**
     * Show form to email quotation to client.
     */
    public function emailForm(CrmQuotation $quotation)
    {
        $templates = MailTemplate::where('is_active', true)
            ->orderBy('name')
            ->get();

        $defaultToName  = $quotation->lead->contact_name ?? optional($quotation->party)->name;
        $defaultToEmail = $quotation->lead->contact_email ?? optional($quotation->party)->email;

        return view('crm.quotations.email', compact(
            'quotation',
            'templates',
            'defaultToName',
            'defaultToEmail'
        ));
    }

    /**
     * Actually send the quotation email with PDF attached.
     */
    public function sendEmail(
        Request $request,
        CrmQuotation $quotation,
        MailService $mailService,
        SettingsService $settings
    ) {
        $validated = $request->validate([
            'to_email'      => ['required', 'email'],
            'to_name'       => ['nullable', 'string', 'max:150'],
            'template_code' => ['required', 'string', 'exists:mail_templates,code'],
        ]);

        $quotation->load(['items.item', 'items.uom', 'party', 'lead', 'standardTerm']);

        $company = $this->resolveCompanyForPdf($settings);
        $terms   = $settings->get('sales', 'quotation_terms', null);

        // Generate PDF
        $pdfBinary = Pdf::loadView('crm.quotations.pdf', [
            'quotation' => $quotation,
            'company'   => $company,
            'terms'     => $terms,
        ])->output();

        // Create a temporary file for attachment
        $tmpBase = tempnam(sys_get_temp_dir(), 'qtn_');
        $pdfPath = $tmpBase . '.pdf';
        file_put_contents($pdfPath, $pdfBinary);

        $currencySymbol = config('crm.currency_symbol', '₹');

        $dataForTemplate = [
            'client_name'    => $validated['to_name']
                ?? $quotation->lead->contact_name
                ?? optional($quotation->party)->name
                ?? '',
            'quotation_code' => $quotation->code,
            'project_name'   => $quotation->project_name,
            'grand_total'    => $currencySymbol . ' ' . number_format((float) $quotation->grand_total, 2),
            'quotation_url'  => route('crm.quotations.show', $quotation),
            'company_name'   => config('app.name'),
        ];

        $mailService->sendTemplateWithAttachments(
            templateCode: $validated['template_code'],
            toEmail:      $validated['to_email'],
            toName:       $validated['to_name'] ?? null,
            data:         $dataForTemplate,
            usage:        'general',
            companyId:    null,
            departmentId: null,
            attachments: [
                [
                    'path' => $pdfPath,
                    'name' => "Quotation-{$quotation->code}.pdf",
                    'mime' => 'application/pdf',
                ],
            ],
        );

        @unlink($pdfPath);
        @unlink($tmpBase);

        if ($quotation->status === 'draft') {
            $quotation->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);
        }

        return redirect()
            ->route('crm.quotations.show', $quotation)
            ->with('success', 'Quotation emailed to client.');
    }
}
