<?php

namespace App\Services;

use App\Http\Requests\PlannerFilterRequest;
use App\Models\Post;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the planner's post query from validated filters.
 *
 * Kept out of the controller because the same filter set drives the calendar,
 * the list view, the shared read-only calendar and the CSV export — and
 * because the tenancy narrowing must happen in exactly one place.
 */
class PlannerQuery
{
    /**
     * @param  Collection<int, int>  $visibleWorkspaceIds  the user's tenancy boundary
     */
    public function build(PlannerFilterRequest $filters, Collection $visibleWorkspaceIds): Builder
    {
        [$from, $to] = $filters->window();

        // The requested workspaces intersected with what the user may see.
        // Intersection, not replacement: an empty request means "all of mine",
        // never "all of everyone's".
        $requested = collect($filters->input('workspace_ids', []))->map(fn ($id) => (int) $id);
        $workspaceIds = $requested->isNotEmpty()
            ? $requested->intersect($visibleWorkspaceIds)->values()
            : $visibleWorkspaceIds;

        $query = Post::query()
            ->whereIn('posts.workspace_id', $workspaceIds)
            ->with([
                'workspace:id,name,color',
                'campaign:id,name,color',
                'pillar:id,name,color',
                'assignee:id,name',
                'tags:id,name,color',
                'targets:id,post_id,channel_id,status,error',
                'targets.channel:id,provider,name',
            ])
            ->withCount('comments');

        // Drafts have no scheduled_at; excluding them from a date window would
        // hide them from every view, so they are always included.
        $query->where(function (Builder $q) use ($from, $to) {
            $q->whereBetween('scheduled_at', [$from, $to])
                ->orWhereNull('scheduled_at');
        });

        $this->applyIn($query, 'posts.status', $filters->input('statuses', []));
        $this->applyIn($query, 'posts.campaign_id', $filters->input('campaign_ids', []));
        $this->applyIn($query, 'posts.content_pillar_id', $filters->input('pillar_ids', []));
        $this->applyIn($query, 'posts.assignee_id', $filters->input('assignee_ids', []));

        if ($tagIds = $filters->input('tag_ids', [])) {
            $query->whereHas('tags', fn (Builder $q) => $q->whereIn('tags.id', $tagIds));
        }

        if ($providers = $filters->input('providers', [])) {
            $query->whereHas(
                'targets.channel',
                fn (Builder $q) => $q->whereIn('channels.provider', $providers)
            );
        }

        if ($search = trim((string) $filters->input('search', ''))) {
            // Escape the LIKE wildcards so a caption search for "50%" does not
            // become a match-everything query. The explicit ESCAPE clause is
            // required: MySQL assumes a backslash escape but SQLite and
            // Postgres do not, and this app supports all three.
            $escaped = addcslashes($search, '%_\\');

            $query->where(function (Builder $q) use ($escaped) {
                $q->whereRaw('posts.content LIKE ? ESCAPE ?', ["%{$escaped}%", '\\'])
                    ->orWhereRaw('posts.title LIKE ? ESCAPE ?', ["%{$escaped}%", '\\']);
            });
        }

        return $query->orderByRaw('scheduled_at is null')->orderBy('scheduled_at');
    }

    /** @param array<int, mixed> $values */
    protected function applyIn(Builder $query, string $column, array $values): void
    {
        if ($values !== []) {
            $query->whereIn($column, $values);
        }
    }

    /**
     * The shape a calendar card needs — deliberately narrow. The audit's P1
     * finding was that the old page shipped whole post records including every
     * comment; the drawer fetches the rest on demand.
     *
     * @return array<string, mixed>
     */
    public function card(Post $post): array
    {
        return [
            'id'            => $post->id,
            'title'         => $post->title,
            'excerpt'       => \Illuminate\Support\Str::limit((string) $post->content, 120),
            'status'        => $post->status,
            'approval'      => $post->approval_state,
            'scheduled_at'  => $post->scheduled_at?->toIso8601String(),
            'timezone'      => $post->timezone,
            'published_at'  => $post->published_at?->toIso8601String(),
            'workspace'     => $post->workspace ? [
                'id'    => $post->workspace->id,
                'name'  => $post->workspace->name,
                'color' => $post->workspace->color,
            ] : null,
            'campaign'      => $post->campaign ? [
                'id'    => $post->campaign->id,
                'name'  => $post->campaign->name,
                'color' => $post->campaign->color,
            ] : null,
            'pillar'        => $post->pillar ? [
                'id'    => $post->pillar->id,
                'name'  => $post->pillar->name,
                'color' => $post->pillar->color,
            ] : null,
            'assignee'      => $post->assignee ? ['id' => $post->assignee->id, 'name' => $post->assignee->name] : null,
            'tags'          => $post->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values(),
            'providers'     => $post->targets->map(fn ($t) => $t->channel?->provider)->filter()->unique()->values(),
            'thumbnail'     => $this->thumbnail($post),
            'comment_count' => $post->comments_count ?? 0,
            // Failures belong on the card itself — the brief requires errors to
            // be visible without opening the post.
            'errors'        => $post->targets
                ->filter(fn ($t) => $t->status === \App\Models\PostTarget::STATUS_FAILED)
                ->map(fn ($t) => [
                    'provider' => $t->channel?->provider,
                    'message'  => \Illuminate\Support\Str::limit((string) $t->error, 160),
                ])->values(),
        ];
    }

    /** First image on the post, if any — used for the calendar card preview. */
    protected function thumbnail(Post $post): ?string
    {
        $media = $post->media ?? [];

        foreach ($media as $item) {
            if (is_string($item)) {
                return $item;
            }

            if (is_array($item) && ! empty($item['url'])) {
                return $item['url'];
            }
        }

        return null;
    }
}
