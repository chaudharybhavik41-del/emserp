<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'material_category_id' => ['required', 'integer', 'exists:material_categories,id'],
            'material_subcategory_id' => ['nullable', 'integer', 'exists:material_subcategories,id'],
            'code' => ['nullable', 'string', 'max:50', 'unique:machines,code'],
            'name' => ['required', 'string', 'max:200'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100', 'unique:machines,serial_number'],
            'grade' => ['nullable', 'string', 'max:100'],
            'spec' => ['nullable', 'string', 'max:150'],
            'year_of_manufacture' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'supplier_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'accounting_treatment' => ['nullable', 'in:fixed_asset,tool_stock'],
            'purchase_invoice_no' => ['nullable', 'string', 'max:100'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
            'rated_capacity' => ['nullable', 'string', 'max:100'],
            'power_rating' => ['nullable', 'string', 'max:50'],
            'fuel_type' => ['nullable', 'in:electric,diesel,gas,hydraulic,manual,other'],
            'current_location' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['required', 'in:active,under_maintenance,breakdown,retired,disposed'],
            'maintenance_frequency_days' => ['nullable', 'integer', 'min:1'],
            'maintenance_alert_days' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
