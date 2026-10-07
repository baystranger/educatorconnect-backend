<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isEducator = $this->user()?->isEducator();

        return [
            'first_name' => [$isEducator ? 'sometimes' : 'prohibited', 'required', 'string', 'max:100'],
            'last_name' => [$isEducator ? 'sometimes' : 'prohibited', 'required', 'string', 'max:100'],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9+().\-\s]{7,30}$/'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter a valid phone number.'];
    }
}
