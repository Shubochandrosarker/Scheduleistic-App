<?php

namespace App\Services;

use App\Http\Requests\PostHistoryFilterRequest;
use App\Models\PostTarget;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the post/schedule history query: one row per (post, channel) publish
 * attempt, the oldest alongside the newest — unlike the planner, which is
 * windowed to whatever the calendar has on screen. History is expected to
 * grow unboundedly, so this pages with real `paginate()` rather than
 * PlannerQuery's `limit(500)`.
 *
 * A join against `posts`, not `whereHas()`, because sorting by
 * `posts.scheduled_at` alongside `post_targets`' own columns requires the
 * column to be reachable by `orderBy()` — a relationship constraint can
 * filter a query but cannot order it by a related table's column.
 */
class PostHistoryQuery
{
    /**
     * @param  Collection<int, int>  $visibleWorkspaceIds  the user's tenancy boundary
     */
    public function build(PostHistoryFilterRequest $filters, Collection $visibleWorkspaceIds): Builder
    {
        $workspaceIds = $this->resolveWorkspaceIds($filters, $visibleWorkspaceIds);

        $query = PostTarget::query()
            ->join('posts', 'posts.id', '=', 'post_targets.post_id')
            ->whereIn('posts.workspace_id', $workspaceIds)
            ->select('post_targets.*');

        $this->applyFilters($query, $filters);

        return $query
            ->with([
                'post:id,workspace_id,campaign_id,title,content,scheduled_at,published_at,status',
                'post.workspace:id,name,color',
                'post.campaign:id,name,color',
                'channel:id,provider,name,workspace_id',
            ])
            ->orderBy($this->sortColumn($filters->sort()), $filters->direction());
    }

    /**
     * Counts for the currently filtered set, computed before pagination so
     * they describe the whole matching result rather than just the visible
     * page — the status stat cards, and the "advanced feature"
     * per-campaign/tag breakdowns the audit called for.
     *
     * @return array<string, mixed>
     */
    public function summary(PostHistoryFilterRequest $filters, Collection $visibleWorkspaceIds): array
    {
        $workspaceIds = $this->resolveWorkspaceIds($filters, $visibleWorkspaceIds);

        $base = PostTarget::query()
            ->join('posts', 'posts.id', '=', 'post_targets.post_id')
            ->whereIn('posts.workspace_id', $workspaceIds);

        $this->applyFilters($base, $filters);

        $byStatus = (clone $base)
            ->selectRaw('post_targets.status as status, count(*) as total')
            ->groupBy('post_targets.status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);

        $byCampaign = (clone $base)
            ->whereNotNull('posts.campaign_id')
            ->join('campaigns', 'campaigns.id', '=', 'posts.campaign_id')
            ->selectRaw('campaigns.id as id, campaigns.name as name, campaigns.color as color, count(*) as total')
            ->groupBy('campaigns.id', 'campaigns.name', 'campaigns.color')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name, 'color' => $row->color, 'total' => (int) $row->total])
            ->values();

        // A post can carry more than one tag, so a target on a two-tag post
        // is counted under both — the same "what's in front of you" reading
        // a per-post view would give, just rolled up across the filtered set.
        $byTag = (clone $base)
            ->join('post_tag', 'post_tag.post_id', '=', 'posts.id')
            ->join('tags', 'tags.id', '=', 'post_tag.tag_id')
            ->selectRaw('tags.id as id, tags.name as name, tags.color as color, count(*) as total')
            ->groupBy('tags.id', 'tags.name', 'tags.color')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name, 'color' => $row->color, 'total' => (int) $row->total])
            ->values();

        return [
            'total' => (int) $byStatus->sum(),
            'by_status' => $byStatus->all(),
            'by_campaign' => $byCampaign->all(),
            'by_tag' => $byTag->all(),
        ];
    }

    /**
     * The requested workspaces intersected with what the user may see.
     * Intersection, not replacement: an empty request means "all of mine,"
     * never "all of everyone's."
     *
     * @param  Collection<int, int>  $visibleWorkspaceIds
     * @return Collection<int, int>
     */
    protected function resolveWorkspaceIds(PostHistoryFilterRequest $filters, Collection $visibleWorkspaceIds): Collection
    {
        $requested = collect($filters->input('workspace_ids', []))->map(fn ($id) => (int) $id);

        return $requested->isNotEmpty()
            ? $requested->intersect($visibleWorkspaceIds)->values()
            : $visibleWorkspaceIds;
    }

    protected function applyFilters(Builder $query, PostHistoryFilterRequest $filters): void
    {
        $this->applyIn($query, 'post_targets.status', $filters->input('statuses', []));
        $this->applyIn($query, 'posts.campaign_id', $filters->input('campaign_ids', []));

        if ($providers = $filters->input('providers', [])) {
            $query->whereHas('channel', fn (Builder $q) => $q->whereIn('channels.provider', $providers));
        }

        if ($from = $filters->input('from')) {
            $query->where('posts.scheduled_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $filters->input('to')) {
            $query->where('posts.scheduled_at', '<=', Carbon::parse($to)->endOfDay());
        }

        if ($search = trim((string) $filters->input('search', ''))) {
            // Escaped LIKE, matching PlannerQuery's search: a caption search
            // for "50%" must not become a match-everything query, and the
            // explicit ESCAPE clause is required because MySQL assumes a
            // backslash escape by default but SQLite and Postgres do not.
            $escaped = addcslashes($search, '%_\\');

            $query->where(function (Builder $q) use ($escaped) {
                $q->whereRaw('posts.content LIKE ? ESCAPE ?', ["%{$escaped}%", '\\'])
                    ->orWhereRaw('posts.title LIKE ? ESCAPE ?', ["%{$escaped}%", '\\']);
            });
        }
    }

    /** @param array<int, mixed> $values */
    protected function applyIn(Builder $query, string $column, array $values): void
    {
        if ($values !== []) {
            $query->whereIn($column, $values);
        }
    }

    protected function sortColumn(string $sort): string
    {
        return match ($sort) {
            'scheduled_at' => 'posts.scheduled_at',
            'published_at' => 'post_targets.published_at',
            default => 'post_targets.created_at',
        };
    }

    /**
     * The shape a history row needs on the wire — deliberately narrow, same
     * philosophy as PlannerQuery::card().
     *
     * @return array<string, mixed>
     */
    public function row(PostTarget $target): array
    {
        return [
            'id' => $target->id,
            'status' => $target->status,
            'provider_post_id' => $target->provider_post_id,
            'error' => $target->error,
            'attempts' => $target->attempts,
            'published_at' => $target->published_at?->toIso8601String(),
            'created_at' => $target->created_at?->toIso8601String(),
            'channel' => $target->channel ? [
                'id' => $target->channel->id,
                'provider' => $target->channel->provider,
                'name' => $target->channel->name,
            ] : null,
            'post' => $target->post ? [
                'id' => $target->post->id,
                'title' => $target->post->title,
                'excerpt' => Str::limit((string) $target->post->content, 120),
                'status' => $target->post->status,
                'scheduled_at' => $target->post->scheduled_at?->toIso8601String(),
                'published_at' => $target->post->published_at?->toIso8601String(),
                'workspace' => $target->post->workspace ? [
                    'id' => $target->post->workspace->id,
                    'name' => $target->post->workspace->name,
                    'color' => $target->post->workspace->color,
                ] : null,
                'campaign' => $target->post->campaign ? [
                    'id' => $target->post->campaign->id,
                    'name' => $target->post->campaign->name,
                    'color' => $target->post->campaign->color,
                ] : null,
            ] : null,
        ];
    }
}
