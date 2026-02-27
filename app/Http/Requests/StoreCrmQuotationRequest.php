<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.quotation.create') ?? false;
    }

    public function rules(): array
    {
        $quoteMode  = $this->input('quote_mode', 'item');
        $isRateOnly = $this->boolean('is_rate_only');

        $qtyRequired = ($quoteMode === 'item') || (! $isRateOnly);

        return [
            'code' => [
                'nullable',
                'string',
                'max:50',
                // Only enforce uniqueness for base revision (0)
                Rule::unique('crm_quotations', 'code')
                    ->where(fn ($q) => $q->where('revision_no', 0)),
            ],

            'quote_mode'     => ['required', 'string', Rule::in(['item', 'rate_per_kg'])],
            'is_rate_only'   => ['nullable', 'boolean'],
            'profit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'scope_of_work' => ['nullable', 'string'],
            'exclusions'    => ['nullable', 'string'],

            'project_name'     => ['required', 'string', 'max:255'],
            'party_id'         => ['required', 'integer', 'exists:parties,id'],
            'client_po_number' => ['nullable', 'string', 'max:100'],
            'valid_till'       => ['nullable', 'date'],

            'payment_terms'        => ['nullable', 'string'],
            'payment_terms_days'   => ['nullable', 'integer', 'min:0', 'max:3650'],
            'freight_terms'        => ['nullable', 'string', 'max:255'],
            'delivery_terms'       => ['nullable', 'string'],
            'other_terms'          => ['nullable', 'string'],
            'project_special_notes'=> ['nullable', 'string'],

            'standard_term_id' => ['nullable', 'integer', 'exists:standard_terms,id'],
            'terms_text'       => ['nullable', 'string'],

            // Line items
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.item_id'            => ['nullable', 'integer', 'exists:items,id'],
            'items.*.description'        => ['required', 'string'],
            'items.*.quantity'           => [
                Rule::requiredIf($qtyRequired),
                'nullable',
                'numeric',
                'min:0',
            ],
            'items.*.uom_id'             => ['nullable', 'integer', 'exists:uoms,id'],
            'items.*.unit_price'         => ['required', 'numeric', 'min:0'],
            'items.*.line_total'         => ['nullable', 'numeric', 'min:0'],
            'items.*.sort_order'         => ['nullable', 'integer', 'min:0'],

            // Stored as JSON string from UI modal
            'items.*.cost_breakup_json'  => ['nullable', 'json'],
        ];
    }
}
