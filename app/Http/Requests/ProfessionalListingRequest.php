<?php

namespace App\Http\Requests;

use App\Support\Website;
use Illuminate\Foundation\Http\FormRequest;

class ProfessionalListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'professional_category_id' => ['required', 'exists:professional_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'professional_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'services_offered' => ['nullable', 'array', 'max:20'],
            'services_offered.*' => ['string', 'max:120'],
            'credentials' => ['nullable', 'array', 'max:20'],
            'credentials.*' => ['string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'service_area' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'website' => Website::rules(),
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'social_links' => ['nullable', 'array', 'max:6'],
            'social_links.*' => ['url', 'max:255'],
            'book_consultation' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['website.regex' => 'Enter a valid website address.'];
    }
}
