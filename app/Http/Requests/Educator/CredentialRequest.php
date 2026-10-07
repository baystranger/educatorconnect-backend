<?php

namespace App\Http\Requests\Educator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'kind' => [$required, Rule::in(['certification', 'education'])],
            'title' => [$required, 'string', 'max:150'],
            'issuer' => ['sometimes', 'nullable', 'string', 'max:150'],
            'issued_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'expires_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:issued_on'],
        ];
    }
}
