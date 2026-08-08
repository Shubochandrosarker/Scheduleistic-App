<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantPlanOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // platform.admin + password.confirm middleware already gate the route.
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::in(array_keys(config('plans')))],
            'reason' => ['required', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
