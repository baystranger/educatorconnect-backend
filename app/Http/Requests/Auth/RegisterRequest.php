<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }

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
        $role = $this->input('role');

        return [
            'role' => ['required', Rule::in(['educator', 'childcare_centre'])],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::min(10)->numbers()->symbols()],
            'first_name' => [Rule::requiredIf($role === 'educator'), 'nullable', 'string', 'max:100'],
            'last_name' => [Rule::requiredIf($role === 'educator'), 'nullable', 'string', 'max:100'],
            'centre_name' => [Rule::requiredIf($role === 'childcare_centre'), 'nullable', 'string', 'max:255'],
            'contact_person' => [Rule::requiredIf($role === 'childcare_centre'), 'nullable', 'string', 'max:255'],
            'phone' => [Rule::requiredIf($role === 'childcare_centre'), 'nullable', 'string', 'max:30'],
            'address' => [Rule::requiredIf($role === 'childcare_centre'), 'nullable', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'terms' => ['required', 'accepted'],
            'is_student' => ['sometimes', 'boolean'],
        ];
    }
}
