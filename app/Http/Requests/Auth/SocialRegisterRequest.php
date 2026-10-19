<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Finishes a Google/LinkedIn sign-up. Name, email and role come from the pending
 * provider record, so only the details the provider doesn't share are collected.
 */
class SocialRegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('province')) {
            $this->merge(['province' => $this->input('province') ?: $this->input('region')]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:64'],
            // The role is fixed by the pending sign-up; centre fields are enforced in AuthService.
            'centre_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'terms' => ['required', 'accepted'],
            'is_student' => ['sometimes', 'boolean'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
