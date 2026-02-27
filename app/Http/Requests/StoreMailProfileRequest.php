<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMailProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'           => 'required|string|max:50|unique:mail_profiles,code',
            'name'           => 'required|string|max:150',
            'company_id'     => 'nullable|integer|exists:companies,id',
            'department_id'  => 'nullable|integer|exists:departments,id',
            'from_name'      => 'nullable|string|max:150',
            'from_email'     => 'required|email|max:150',
            'reply_to'       => 'nullable|email|max:150',
            'smtp_host'      => 'required|string|max:150',
            'smtp_port'      => 'required|integer|min:1|max:65535',
            'smtp_encryption'=> 'nullable|string|in:ssl,tls',
            'smtp_username'  => 'required|string|max:150',
            'smtp_password'  => 'required|string|max:255',
            'is_default'     => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
        ];
    }
}
