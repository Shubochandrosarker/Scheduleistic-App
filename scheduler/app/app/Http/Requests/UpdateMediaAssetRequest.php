<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('asset'));
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:200'],
            'alt_text'    => ['nullable', 'string', 'max:1000'],
            'notes'       => ['nullable', 'string', 'max:2000'],
            'is_favorite' => ['nullable', 'boolean'],
            'archived'    => ['nullable', 'boolean'],
            'tags'        => ['nullable', 'array', 'max:20'],
            'tags.*'      => ['string', 'max:40'],
        ];
    }
}
