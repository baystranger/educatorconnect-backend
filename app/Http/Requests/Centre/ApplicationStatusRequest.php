<?php

namespace App\Http\Requests\Centre;

use App\Support\ApplicationStages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ApplicationStages::CENTRE_SETTABLE)],
            'interview_at' => ['nullable', 'required_if:status,interview', 'date', 'after:now'],
            'interview_location' => ['nullable', 'string', 'max:255'],
            'interview_details' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'interview_at.required_if' => 'Choose a date and time for the interview.',
            'interview_at.after' => 'The interview must be in the future.',
        ];
    }
}
