<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id ?? null;

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
                Rule::unique('users', 'employee_code')->ignore($userId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'confirmed', $passwordRules],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
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
            'email.unique' => 'This email address is already in use by another user.',
            'employee_code.unique' => 'This employee code is already in use.',
            'password.min' => 'Password must be at least :min characters.',
            'profile_photo.max' => 'Profile photo must not exceed 2MB.',
        ];
    }

    /**
     * Return only the basic user fields for mass assignment.
     */
    public function validatedBasic(): array
    {
        return $this->only(['employee_code', 'name', 'email', 'phone', 'designation']);
    }
}
