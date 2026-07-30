<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Shared tenancy plumbing for every workspace-scoped model.
 *
 * The important part is `scopeForWorkspaces()`: callers pass the set of
 * workspace ids the *user* is allowed to see (resolved once, server-side) and
 * the query is narrowed to it. No controller filters on an id that came from
 * the request without first intersecting it with that set.
 */
trait BelongsToWorkspace
{
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $workspaceIds
     */
    public function scopeForWorkspaces(Builder $query, Collection|array $workspaceIds): Builder
    {
        return $query->whereIn($this->getTable().'.workspace_id', $workspaceIds);
    }
}
