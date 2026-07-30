<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesToWorkspace;
use App\Models\Idea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdeaRequest extends FormRequest
{
    use ScopesToWorkspace;

    public function authorize(): bool
    {
        return $this->user()->can('create', Idea::class);
    }

    public function rules(): array
    {
        return [
            'workspace_id'      => $this->workspaceIdRules(),
            'title'             => ['required', 'string', 'max:200'],
            'notes'             => ['nullable', 'string', 'max:10000'],
            'stage'             => ['nullable', Rule::in(Idea::STAGES)],
            'priority'          => ['nullable', Rule::in(Idea::PRIORITIES)],
            'source_url'        => ['nullable', 'url:http,https', 'max:2048'],
            'due_on'            => ['nullable', 'date'],
            'campaign_id'       => $this->belongsToWorkspaceRules('campaigns'),
            'content_pillar_id' => $this->belongsToWorkspaceRules('content_pillars'),
            'assignee_id'       => $this->assignableUserRule(),
            'tags'              => ['nullable', 'array', 'max:20'],
            'tags.*'            => ['string', 'max:40'],
        ];
    }
}
