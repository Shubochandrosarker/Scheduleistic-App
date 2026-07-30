<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Drag-and-drop rescheduling. `scheduled_at` may be null, which returns the
 * post to the draft column rather than deleting the schedule silently.
 */
class ReschedulePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reschedule', $this->route('post'));
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['nullable', 'date'],
            'timezone'     => ['nullable', 'timezone'],
        ];
    }
}
