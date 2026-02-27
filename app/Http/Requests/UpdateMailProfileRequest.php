<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMailProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $profileId = $this->route('mail_profile')->id ?? null;

        return [
            'code'           => [
                'required',
                'string',
                'max:50',
                Rule::unique('mail_profiles', 'code')->ignore($profileId),
            ],
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
            // allow blank to mean "keep existing"
            'smtp_password'  => 'nullable|string|max:255',
            'is_default'     => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
        ];
    }

    public function validated($key = null, $default = null)
	{
    $data = parent::validated($key, $default);

    // If a single key was requested, don't transform the payload.
    if ($key !== null) {
        return $data;
    }

    if (is_array($data) && array_key_exists('smtp_password', $data)) {
        $pw = $data['smtp_password'];
        if ($pw === null || $pw === '') {
            unset($data['smtp_password']);
        }
    }

    return $data;
	}
}
