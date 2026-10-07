<?php

namespace App\Http\Requests\Centre;

use App\Models\ParentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParentRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ParentRequest::STATUSES)],
            'requested_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
