<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Accounts created with Google/LinkedIn have no password yet and may set one.
            'current_password' => [$this->user()->password ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(10)->numbers()->symbols()],
        ];
    }
}
