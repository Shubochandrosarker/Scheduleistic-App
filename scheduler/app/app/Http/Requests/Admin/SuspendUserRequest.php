<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A reason is required to suspend an account, but not to reactivate one —
 * the toggle direction is known from the target user's current state.
 */
class SuspendUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // platform.admin middleware already gates the whole route.
    }

    public function rules(): array
    {
        $aboutToSuspend = ! $this->route('user')->isSuspended();

        return [
            'reason' => [$aboutToSuspend ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }
}
