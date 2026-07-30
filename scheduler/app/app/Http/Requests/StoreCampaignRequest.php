<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesToWorkspace;
use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    use ScopesToWorkspace;

    public function authorize(): bool
    {
        return $this->user()->can('create', Campaign::class);
    }

    public function rules(): array
    {
        return [
            'workspace_id'  => $this->workspaceIdRules(),
            'name'          => ['required', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:2000'],
            'goal'          => ['nullable', 'string', 'max:255'],
            'status'        => ['nullable', Rule::in(Campaign::STATUSES)],
            'starts_on'     => ['nullable', 'date'],
            'ends_on'       => ['nullable', 'date', 'after_or_equal:starts_on'],
            'budget_cents'  => ['nullable', 'integer', 'min:0', 'max:99999999999'],
            'currency'      => ['nullable', 'string', 'size:3'],
            'target_url'    => ['nullable', 'url:http,https', 'max:2048'],
            'color'         => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'owner_id'      => $this->assignableUserRule(),
            'utm'           => ['nullable', 'array'],
            'utm.source'    => ['nullable', 'string', 'max:100'],
            'utm.medium'    => ['nullable', 'string', 'max:100'],
            'utm.campaign'  => ['nullable', 'string', 'max:100'],
            'utm.term'      => ['nullable', 'string', 'max:100'],
            'utm.content'   => ['nullable', 'string', 'max:100'],
            'kpi_targets'   => ['nullable', 'array'],
            'kpi_targets.*' => ['numeric', 'min:0'],
        ];
    }
}
