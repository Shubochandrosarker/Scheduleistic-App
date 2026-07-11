<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Workspace;
use App\Services\PostComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /** Calendar / list of posts across every workspace the user can see. */
    public function index(Request $request): Response
    {
        $workspaceIds = $this->visibleWorkspaceIds($request);

        return Inertia::render('Posts/Index', [
            'posts' => Post::query()
                ->whereIn('workspace_id', $workspaceIds)
                ->with([
                    'workspace:id,name',
                    'targets:id,post_id,status',
                    'comments.user:id,name',
                ])
                ->latest('scheduled_at')
                ->limit(200)
                ->get(),
            'workspaceMembers' => $this->workspaceMembers($workspaceIds),
        ]);
    }

    /**
     * Users who can be @mentioned in a comment on a post in each workspace:
     * the organization's owner + members, plus anyone individually assigned
     * to that specific workspace (the client portal).
     *
     * @return array<int, array<int, array{id: int, name: string}>> keyed by workspace_id
     */
    protected function workspaceMembers(\Illuminate\Support\Collection $workspaceIds): array
    {
        return Workspace::query()
            ->whereIn('id', $workspaceIds)
            ->with(['team.owner:id,name', 'team.users:id,name', 'users:id,name'])
            ->get()
            ->mapWithKeys(function (Workspace $workspace) {
                $members = $workspace->team->allUsers()
                    ->merge($workspace->users)
                    ->unique('id')
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                    ->values();

                return [$workspace->id => $members];
            })
            ->all();
    }

    /**
     * Workspaces the user may see: those owned by their current organization,
     * plus any they are individually assigned to (the client portal).
     */
    protected function visibleWorkspaceIds(Request $request): \Illuminate\Support\Collection
    {
        $user = $request->user();

        $owned    = $user->currentTeam?->workspaces()->pluck('id') ?? collect();
        $assigned = $user->workspaces()->pluck('workspaces.id');

        return $owned->merge($assigned)->unique()->values();
    }

    /** The composer. */
    public function create(Request $request): Response
    {
        $workspaces = $request->user()->currentTeam
            ->workspaces()
            ->with('channels:id,workspace_id,provider,name')
            ->get();

        return Inertia::render('Posts/Composer', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(Request $request, PostComposer $composer): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_id'  => ['required', 'integer'],
            'content'       => ['required', 'string'],
            'channel_ids'   => ['required', 'array', 'min:1'],
            'channel_ids.*' => ['integer'],
            'scheduled_at'  => ['nullable', 'date'],
            'use_queue'     => ['nullable', 'boolean'],
            'recurring_rule'=> ['nullable', 'in:daily,weekly,monthly'],
            'overrides'     => ['nullable', 'array'],
        ]);

        $workspace = Workspace::findOrFail($validated['workspace_id']);
        abort_unless($workspace->team_id === $request->user()->currentTeam->id, 403);

        if (! app(\App\Services\UsageService::class)->allows($workspace->team, 'monthly_posts')) {
            return back()->withErrors(['plan' => 'You have reached this month\'s post limit on your plan. Upgrade for more.']);
        }

        $composer->schedule(
            workspace: $workspace,
            content: $validated['content'],
            channelIds: $validated['channel_ids'],
            scheduledAt: isset($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null,
            overrides: $validated['overrides'] ?? [],
            authorId: $request->user()->id,
            useQueue: (bool) ($validated['use_queue'] ?? false),
            recurringRule: $validated['recurring_rule'] ?? null,
        );

        return redirect()->route('posts.index')->with('status', 'post-scheduled');
    }
}
