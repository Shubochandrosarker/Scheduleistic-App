<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RemovePlanOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // platform.admin + password.confirm middleware already gate the route.
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
