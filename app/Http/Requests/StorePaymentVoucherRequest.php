<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.vouchers.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'voucher_date'   => ['required', 'date'],
            'bank_account_id'=> ['required', 'integer', 'exists:accounts,id'],
            'project_id'     => ['nullable', 'integer', 'exists:projects,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'currency_id'    => ['nullable', 'integer', 'exists:currencies,id'],
            'narration'      => ['nullable', 'string'],
            'reference'      => ['nullable', 'string', 'max:255'],

            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.account_id'     => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.amount'         => ['required', 'numeric', 'min:0.01'],
            'lines.*.description'    => ['nullable', 'string', 'max:255'],
            'lines.*.cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'lines.*.reference_type' => ['nullable', 'string', 'max:255'],
            'lines.*.reference_id'   => ['nullable', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'bank_account_id'         => 'bank / cash account',
            'lines.*.account_id'      => 'line account',
            'lines.*.amount'          => 'line amount',
        ];
    }
}
