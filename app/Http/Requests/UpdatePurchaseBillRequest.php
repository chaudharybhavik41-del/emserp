<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdatePurchaseBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $billId = $this->route('bill')?->id ?? $this->route('purchase_bill')?->id ?? $this->route('id');

        $rules = [
            'supplier_id'        => ['required', 'integer', Rule::exists('parties', 'id')],
            'supplier_branch_id' => ['nullable', 'integer'],

            'purchase_order_id'  => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')],
            'project_id'         => ['nullable', 'integer', Rule::exists('projects', 'id')],

            'bill_number'        => ['required', 'string', 'max:50', Rule::unique('purchase_bills', 'bill_number')->ignore($billId)],
            'bill_date'          => ['required', 'date'],
            'posting_date'       => ['required', 'date'],
            'due_date'           => ['nullable', 'date'],

            'reference_no'       => ['nullable', 'string', 'max:100'],
            'challan_number'     => ['nullable', 'string', 'max:100'],
            'remarks'            => ['nullable', 'string', 'max:5000'],

            'currency'           => ['nullable', 'string', 'max:10'],
            'exchange_rate'      => ['nullable', 'numeric', 'min:0'],

            'invoice_total'      => ['nullable', 'numeric', 'min:0'],

            'tds_section'        => ['nullable', 'string', 'max:50'],
            'tds_rate'           => ['nullable', 'numeric', 'min:0'],
            'tds_amount'         => ['nullable', 'numeric', 'min:0'],
            'tds_auto_calculate' => ['sometimes', 'boolean'],

            'tcs_section'        => ['nullable', 'string', 'max:50'],
            'tcs_rate'           => ['nullable', 'numeric', 'min:0'],
            'tcs_amount'         => ['nullable', 'numeric', 'min:0'],

            'lines'                       => ['nullable', 'array'],
            'lines.*.id'                  => ['nullable', 'integer'],
            'lines.*.item_id'             => ['nullable', 'integer', Rule::exists('items', 'id')],
            'lines.*.uom_id'              => ['nullable', 'integer', Rule::exists('uoms', 'id')],
            'lines.*.description'         => ['nullable', 'string', 'max:500'],
            'lines.*.qty'                 => ['nullable', 'numeric', 'min:0'],
            'lines.*.rate'                => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.material_receipt_id' => ['nullable', 'integer', Rule::exists('material_receipts', 'id')],
            'lines.*.grn_line_id'         => ['nullable', 'integer'],

            'expense_lines'                  => ['nullable', 'array'],
            'expense_lines.*.id'             => ['nullable', 'integer'],
            'expense_lines.*.account_id'     => ['nullable', 'integer', Rule::exists('accounts', 'id')],
            'expense_lines.*.project_id'     => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'expense_lines.*.description'    => ['nullable', 'string', 'max:500'],
            'expense_lines.*.amount'         => ['nullable', 'numeric', 'min:0'],
            'expense_lines.*.tax_rate'       => ['nullable', 'numeric', 'min:0', 'max:100'],

            'attachments'          => ['nullable', 'array'],
            'attachments.*'        => ['file', 'max:5120'],
            'attachments_delete'   => ['nullable', 'array'],
            'attachments_delete.*' => ['integer'],
        ];

        if (Schema::hasTable('party_branches')) {
            $rules['supplier_branch_id'] = [
                'nullable',
                'integer',
                Rule::exists('party_branches', 'id')->where(function ($q) {
                    $supplierId = $this->input('supplier_id');
                    if ($supplierId) {
                        $q->where('party_id', $supplierId);
                    }
                }),
            ];
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $lines = (array) $this->input('lines', []);
            $exp   = (array) $this->input('expense_lines', []);

            $hasAny = false;

            foreach ($lines as $l) {
                if (!empty($l['item_id']) && !empty($l['qty'])) {
                    $hasAny = true;
                    break;
                }
            }

            if (!$hasAny) {
                foreach ($exp as $e) {
                    $amt = isset($e['amount']) ? (float) $e['amount'] : 0.0;
                    if (!empty($e['account_id']) && $amt > 0) {
                        $hasAny = true;
                        break;
                    }
                }
            }

            if (!$hasAny) {
                $v->errors()->add('lines', 'Please add at least one item line or expense line.');
            }
        });
    }
}
