<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Get password requirements from settings
        $minLength = (int) setting('password_min_length', 8);
        $requireUpper = setting('password_require_uppercase', true);
        $requireNumber = setting('password_require_number', true);
        $requireSpecial = setting('password_require_special', false);

        $passwordRules = Password::min($minLength);
        
        if ($requireUpper) {
            $passwordRules->mixedCase();
        }
        if ($requireNumber) {
            $passwordRules->numbers();
        }
        if ($requireSpecial) {
            $passwordRules->symbols();
        }

        return [
            'employee_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'employee_code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'confirmed', $passwordRules],
            'profile_photo' => ['nullable', 'image', 'max:2048'], // 2MB max
            'is_active' => ['boolean'],

            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],

            'departments' => ['nullable', 'array'],
            'departments.*' => ['integer', 'exists:departments,id'],

            'primary_department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already registered.',
            'employee_code.unique' => 'This employee code is already in use.',
            'password.min' => 'Password must be at least :min characters.',
            'profile_photo.max' => 'Profile photo must not exceed 2MB.',
        ];
    }
}
