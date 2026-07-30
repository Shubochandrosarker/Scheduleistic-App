<?php

namespace App\Http\Requests\Concerns;

use App\Models\Workspace;
use Illuminate\Validation\Rule;

/**
 * Validation helpers for requests that carry a `workspace_id`.
 *
 * The rule below is the reason IDs from the client are never trusted: a
 * `workspace_id` is only valid if it appears in the *user's* visible set, and
 * every related id (campaign, pillar, tag, asset, assignee) must belong to
 * that same workspace. A caller who guesses another tenant's campaign id gets
 * a validation error, not a cross-tenant write.
 */
trait ScopesToWorkspace
{
    /** The workspace the request targets, already checked against tenancy. */
    public function workspace(): Workspace
    {
        return Workspace::findOrFail($this->resolvedWorkspaceId());
    }

    protected function resolvedWorkspaceId(): int
    {
        return (int) $this->input('workspace_id');
    }

    /**
     * `workspace_id` must exist AND be visible to this user.
     *
     * @return array<int, mixed>
     */
    protected function workspaceIdRules(): array
    {
        return [
            'required',
            'integer',
            Rule::in($this->user()->visibleWorkspaceIds()->all()),
        ];
    }

    /**
     * A rule that keeps a related record inside the same workspace as the
     * request. Used for campaign_id, content_pillar_id, media asset ids, etc.
     *
     * @return array<int, mixed>
     */
    protected function belongsToWorkspaceRules(string $table, bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'integer',
            Rule::exists($table, 'id')->where('workspace_id', $this->resolvedWorkspaceId()),
        ];
    }

    /**
     * Assignees must be someone who can actually see the workspace, otherwise
     * assigning a post becomes an information leak: the assignee would receive
     * a notification about content they have no access to.
     */
    protected function assignableUserRule(): array
    {
        return [
            'nullable',
            'integer',
            function (string $attribute, $value, \Closure $fail) {
                if ($value === null) {
                    return;
                }

                $workspace = Workspace::find($this->resolvedWorkspaceId());

                if (! $workspace) {
                    $fail('The selected workspace is invalid.');

                    return;
                }

                $allowed = $workspace->team->allUsers()->pluck('id')
                    ->merge($workspace->users()->pluck('users.id'))
                    ->unique();

                if (! $allowed->contains((int) $value)) {
                    $fail('That person does not have access to this workspace.');
                }
            },
        ];
    }
}
