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
        // Every role has the holder's first/last name. Educators must keep both (they name the
        // profile); `name` is the display name, editable only where it is the person's own name.
        $namesAccount = in_array($this->user()?->role, ['professional', 'administrator'], true);

        return [
            // `sometimes` first: absent fields are fine for every role, present ones must belong to it.
            'first_name' => ['sometimes', $isEducator ? 'required' : 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', $isEducator ? 'required' : 'nullable', 'string', 'max:100'],
            'name' => ['sometimes', $namesAccount ? 'required' : 'prohibited', 'string', 'max:255'],
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
