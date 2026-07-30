<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesToWorkspace;
use App\Models\ContentPillar;
use Illuminate\Foundation\Http\FormRequest;

class StoreContentPillarRequest extends FormRequest
{
    use ScopesToWorkspace;

    public function authorize(): bool
    {
        return $this->user()->can('create', ContentPillar::class);
    }

    public function rules(): array
    {
        return [
            'workspace_id' => $this->workspaceIdRules(),
            'name'         => ['required', 'string', 'max:60'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'color'        => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'target_share' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
