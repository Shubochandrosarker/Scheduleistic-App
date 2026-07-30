<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Bulk calendar operations.
 *
 * `post_ids` is checked against the user's visible workspaces *in the rules*,
 * so a request containing one foreign id fails wholesale instead of partially
 * applying. Bulk actions that half-succeed are worse than ones that fail.
 */
class BulkPostActionRequest extends FormRequest
{
    public const ACTIONS = ['reschedule', 'delete', 'submit', 'approve', 'assign', 'tag', 'campaign'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $visible = $this->user()->visibleWorkspaceIds()->all();

        return [
            'action'      => ['required', Rule::in(self::ACTIONS)],
            'post_ids'    => ['required', 'array', 'min:1', 'max:200'],
            'post_ids.*'  => ['integer', Rule::exists('posts', 'id')->whereIn('workspace_id', $visible)],

            // Action-specific payloads.
            'scheduled_at' => ['required_if:action,reschedule', 'nullable', 'date'],
            'shift_minutes'=> ['nullable', 'integer', 'between:-525600,525600'],
            'assignee_id'  => ['required_if:action,assign', 'nullable', 'integer'],
            'tag_ids'      => ['required_if:action,tag', 'nullable', 'array', 'max:20'],
            'tag_ids.*'    => ['integer', Rule::exists('tags', 'id')->whereIn('workspace_id', $visible)],
            'campaign_id'  => ['required_if:action,campaign', 'nullable', 'integer',
                               Rule::exists('campaigns', 'id')->whereIn('workspace_id', $visible)],
            'decision'     => ['required_if:action,approve', 'nullable', Rule::in(['approved', 'changes_requested'])],
            'note'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return Collection<int, Post> */
    public function posts(): Collection
    {
        return Post::with('workspace')->whereIn('id', $this->input('post_ids'))->get();
    }
}
