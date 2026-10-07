<?php

namespace App\Http\Requests;

use App\Models\ParentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public, unauthenticated form on a centre profile (ask a question / book a tour / join waitlist).
 * Rate limited by the `public-form` limiter; `website` is a honeypot that real users never fill.
 */
class ParentRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ParentRequest::TYPES)],
            'parent_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\-\s]{7,30}$/'],
            'child_age' => ['nullable', 'string', 'max:50'],
            'program_of_interest' => ['nullable', 'string', 'max:120'],
            'desired_start' => ['nullable', 'string', 'max:60'],
            'schedule' => ['nullable', 'string', 'max:60'],
            'preferred_contact_method' => ['nullable', Rule::in(array_keys(config('platform.contact_methods')))],
            'message' => ['nullable', 'string', 'max:3000'],
            'requested_at' => ['nullable', 'required_if:type,tour', 'date', 'after:now'],
            'consent' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => 'Please agree to share these details with the centre.',
            'requested_at.required_if' => 'Choose a preferred tour time.',
            'requested_at.after' => 'Choose a tour time in the future.',
            'phone.regex' => 'Enter a valid phone number.',
            'website.prohibited' => 'Your request could not be sent.',
        ];
    }
}
