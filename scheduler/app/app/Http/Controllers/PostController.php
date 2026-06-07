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
    /** Calendar / list of posts across the organization's workspaces. */
    public function index(Request $request): Response
    {
        $workspaceIds = $request->user()->currentTeam->workspaces()->pluck('id');

        return Inertia::render('Posts/Index', [
            'posts' => Post::query()
                ->whereIn('workspace_id', $workspaceIds)
                ->with(['workspace:id,name', 'targets:id,post_id,status'])
                ->latest('scheduled_at')
                ->limit(200)
                ->get(),
        ]);
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
            'workspace_id' => ['required', 'integer'],
            'content'      => ['required', 'string'],
            'channel_ids'  => ['required', 'array', 'min:1'],
            'channel_ids.*'=> ['integer'],
            'scheduled_at' => ['nullable', 'date'],
            'overrides'    => ['nullable', 'array'],
        ]);

        $workspace = Workspace::findOrFail($validated['workspace_id']);
        abort_unless($workspace->team_id === $request->user()->currentTeam->id, 403);

        $composer->schedule(
            workspace: $workspace,
            content: $validated['content'],
            channelIds: $validated['channel_ids'],
            scheduledAt: isset($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null,
            overrides: $validated['overrides'] ?? [],
            authorId: $request->user()->id,
        );

        return redirect()->route('posts.index')->with('status', 'post-scheduled');
    }
}
