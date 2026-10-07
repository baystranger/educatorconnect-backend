<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = array_keys(config('platform.notification_types.'.$this->user()->role, []));

        return [
            'types' => ['sometimes', 'array'],
            'types.*' => ['array'],
            'types.*.email' => ['sometimes', 'boolean'],
            'types.*.in_app' => ['sometimes', 'boolean'],
            'job_alert_frequency' => ['sometimes', Rule::in(array_keys(config('platform.job_alert_frequencies')))],
        ] + collect($types)->mapWithKeys(fn ($t) => ["types.{$t}" => ['sometimes', 'array']])->all();
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();
        $allowed = array_keys(config('platform.notification_types.'.$this->user()->role, []));
        $data['types'] = array_intersect_key($data['types'] ?? [], array_flip($allowed));

        return $key ? ($data[$key] ?? $default) : $data;
    }
}
