<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntitlementClearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // platform.admin middleware already gates the route.
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['capability', 'limit'])],
            'key' => [
                'required',
                'string',
                Rule::in($this->input('type') === 'limit'
                    ? array_keys(config('plans.enterprise.limits'))
                    : array_keys(config('plans.enterprise.capabilities'))),
            ],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
