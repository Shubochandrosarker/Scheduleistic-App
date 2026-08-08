<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared shape for granting/raising and for clearing an entitlement — the
 * controller decides which service method to call, but both directions
 * validate the same way: a real capability/limit key, and a reason.
 */
class EntitlementGrantRequest extends FormRequest
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
            'value' => ['required_if:type,limit', 'nullable', 'integer', 'min:-1'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
