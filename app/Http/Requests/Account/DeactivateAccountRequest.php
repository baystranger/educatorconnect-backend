<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm' => ['accepted'],
            'password' => [$this->user()->password ? 'required' : 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return ['confirm.accepted' => 'Please confirm you understand what deactivating does.'];
    }
}
