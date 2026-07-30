<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesToWorkspace;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    use ScopesToWorkspace;

    public function authorize(): bool
    {
        return $this->user()->can('create', Tag::class);
    }

    public function rules(): array
    {
        return [
            'workspace_id' => $this->workspaceIdRules(),
            'name'         => ['required', 'string', 'max:40'],
            'color'        => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
