<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIdeaRequest;
use App\Http\Requests\UpdateIdeaRequest;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\ContentPillar;
use App\Models\Idea;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The ideas pipeline. Kanban and list are the same data in two shapes, so the
 * controller returns one payload and the client picks the layout.
 */
class IdeaController extends Controller
{
    public function index(Request $request): Response
    {
        $visible = $request->user()->visibleWorkspaceIds();

        $ideas = Idea::query()
            ->forWorkspaces($visible)
            ->with(['workspace:id,name,color', 'campaign:id,name,color', 'pillar:id,name,color', 'assignee:id,name', 'tags:id,name,color'])
            ->withCount('posts')
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Ideas/Index', [
            'ideas'      => $ideas,
            'stages'     => Idea::STAGES,
            'priorities' => Idea::PRIORITIES,
            'workspaces' => $request->user()->currentTeam?->workspaces()->get(['id', 'name', 'color']) ?? [],
            'campaigns'  => Campaign::whereIn('workspace_id', $visible)->orderBy('name')->get(['id', 'workspace_id', 'name']),
            'pillars'    => ContentPillar::whereIn('workspace_id', $visible)->orderBy('position')->get(['id', 'workspace_id', 'name', 'color']),
        ]);
    }

    public function store(StoreIdeaRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('tags');

        $idea = Idea::create([
            ...$data,
            'author_id' => $request->user()->id,
            'position'  => Idea::where('workspace_id', $data['workspace_id'])->max('position') + 1,
        ]);

        $this->syncTags($idea, $request->input('tags', []));

        return back()->with('status', 'idea-created');
    }

    public function update(UpdateIdeaRequest $request, Idea $idea): RedirectResponse
    {
        $idea->update($request->safe()->except(['tags', 'workspace_id']));

        if ($request->has('tags')) {
            $this->syncTags($idea, $request->input('tags', []));
        }

        return back()->with('status', 'idea-updated');
    }

    public function destroy(Request $request, Idea $idea): RedirectResponse
    {
        $this->authorize('delete', $idea);

        $idea->delete();

        return back()->with('status', 'idea-deleted');
    }

    /**
     * Turn an idea into one or more drafts — one per selected workspace
     * channel grouping, or a single draft when none is specified.
     *
     * The idea is not consumed: it moves to `drafting` and keeps a link to the
     * posts it produced, so the board can show what became of it.
     */
    public function convert(Request $request, Idea $idea): RedirectResponse
    {
        $this->authorize('update', $idea);
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $count = (int) ($validated['count'] ?? 1);

        DB::transaction(function () use ($idea, $count, $request) {
            for ($i = 0; $i < $count; $i++) {
                Post::create([
                    'workspace_id'      => $idea->workspace_id,
                    'campaign_id'       => $idea->campaign_id,
                    'content_pillar_id' => $idea->content_pillar_id,
                    'author_id'         => $request->user()->id,
                    'assignee_id'       => $idea->assignee_id,
                    'idea_id'           => $idea->id,
                    'status'            => Post::STATUS_DRAFT,
                    'title'             => $count > 1 ? $idea->title.' ('.($i + 1).')' : $idea->title,
                    'content'           => (string) $idea->notes,
                    'timezone'          => $idea->workspace->timezone ?? 'UTC',
                ]);
            }

            $idea->update(['stage' => 'drafting']);
        });

        AuditLog::record('idea.converted', $idea, ['posts' => $count]);

        return redirect()->route('planner.index')->with('status', "idea-converted-{$count}");
    }

    /** @param array<int, string> $names */
    protected function syncTags(Idea $idea, array $names): void
    {
        $ids = collect($names)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->map(fn ($n) => Tag::resolve($idea->workspace_id, $n)->id)
            ->all();

        $idea->tags()->sync($ids);
    }
}
