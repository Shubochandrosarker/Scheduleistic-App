<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkPostActionRequest;
use App\Http\Requests\PlannerFilterRequest;
use App\Http\Requests\ReschedulePostRequest;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\ContentPillar;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Workspace;
use App\Services\ApprovalService;
use App\Services\PlannerQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The content planner — every calendar view, the post drawer, drag-and-drop
 * rescheduling and bulk operations.
 *
 * All reads go through PlannerQuery so the tenancy narrowing and the payload
 * shape live in one place, and every write re-authorizes per post rather than
 * trusting that the list was filtered correctly upstream.
 */
class PlannerController extends Controller
{
    public function __construct(private readonly PlannerQuery $planner) {}

    public function index(PlannerFilterRequest $request): Response
    {
        $visible = $request->user()->visibleWorkspaceIds();

        [$from, $to] = $request->window();

        $posts = $this->planner->build($request, $visible)
            ->limit(500)
            ->get()
            ->map(fn (Post $post) => $this->planner->card($post))
            ->values();

        return Inertia::render('Planner/Index', [
            'posts'   => $posts,
            'view'    => $request->view(),
            'window'  => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            // The month the grid is *for*. The window starts on the Monday of
            // the week containing the 1st, which is normally in the previous
            // month, so the client cannot infer this from the window.
            'anchor'  => $request->anchor()->toIso8601String(),
            // Echoed back so the client can rebuild its filter state from the
            // URL alone — filters are shareable links, not hidden state.
            'filters' => $request->only([
                'view', 'from', 'to', 'workspace_ids', 'providers', 'statuses',
                'campaign_ids', 'pillar_ids', 'tag_ids', 'assignee_ids', 'search',
            ]),
            'options' => $this->filterOptions($request, $visible),
        ]);
    }

    /**
     * The post detail drawer. Fetched on demand rather than shipped with the
     * calendar, which is what keeps the calendar payload small.
     */
    public function show(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $post->load([
            'workspace:id,name,color,timezone',
            'campaign:id,name,color',
            'pillar:id,name,color',
            'author:id,name',
            'assignee:id,name',
            'tags:id,name,color',
            'versions',
            'targets.channel:id,provider,name,health_state',
            'comments.user:id,name',
            'approvals.requester:id,name',
            'approvals.decider:id,name',
        ]);

        return response()->json([
            'post' => [
                'id'           => $post->id,
                'title'        => $post->title,
                'content'      => $post->content,
                'status'       => $post->status,
                'approval'     => $post->approval_state,
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                'published_at' => $post->published_at?->toIso8601String(),
                'timezone'     => $post->timezone,
                'media'        => $post->media ?? [],
                'hashtags'     => $post->hashtags ?? [],
                'workspace'    => $post->workspace,
                'campaign'     => $post->campaign,
                'pillar'       => $post->pillar,
                'author'       => $post->author,
                'assignee'     => $post->assignee,
                'tags'         => $post->tags,
                'versions'     => $post->versions->map(fn ($v) => [
                    'provider' => $v->provider,
                    'content'  => $v->content,
                    'options'  => $v->options,
                ]),
                'targets' => $post->targets->map(fn ($t) => [
                    'id'       => $t->id,
                    'status'   => $t->status,
                    'error'    => $t->error,
                    'attempts' => $t->attempts,
                    'provider' => $t->channel?->provider,
                    'channel'  => $t->channel?->name,
                    'health'   => $t->channel?->health_state,
                ]),
                'comments' => $post->comments->map(fn ($c) => [
                    'id'         => $c->id,
                    'body'       => $c->body,
                    'user'       => $c->user?->name,
                    'created_at' => $c->created_at?->toIso8601String(),
                ]),
                'approvals' => $post->approvals->map(fn ($a) => [
                    'id'         => $a->id,
                    'state'      => $a->state,
                    'comment'    => $a->comment,
                    'requester'  => $a->requester?->name,
                    'decider'    => $a->decider?->name,
                    'decided_at' => $a->decided_at?->toIso8601String(),
                    'created_at' => $a->created_at?->toIso8601String(),
                ]),
            ],
            'can' => [
                'update'     => $request->user()->can('update', $post),
                'delete'     => $request->user()->can('delete', $post),
                'approve'    => $request->user()->can('approve', $post),
                'reschedule' => $request->user()->can('reschedule', $post),
            ],
        ]);
    }

    /**
     * Drag-and-drop reschedule.
     *
     * The policy already refuses posts that are publishing or published, so a
     * card cannot be dragged out from under the queue. Optimistic UI on the
     * client is safe precisely because this endpoint is authoritative and
     * returns the post's real new state.
     */
    public function reschedule(ReschedulePostRequest $request, Post $post): JsonResponse
    {
        $scheduledAt = $request->filled('scheduled_at')
            ? Carbon::parse($request->input('scheduled_at'))
            : null;

        $post->forceFill([
            'scheduled_at' => $scheduledAt,
            'timezone'     => $request->input('timezone') ?? $post->timezone,
            // Clearing the date returns the post to draft; setting one on a
            // draft schedules it. Any other status is left alone.
            'status'       => match (true) {
                $scheduledAt === null && $post->status === Post::STATUS_SCHEDULED => Post::STATUS_DRAFT,
                $scheduledAt !== null && $post->status === Post::STATUS_DRAFT     => Post::STATUS_SCHEDULED,
                default                                                          => $post->status,
            },
        ])->save();

        return response()->json([
            'id'           => $post->id,
            'status'       => $post->status,
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
        ]);
    }

    /**
     * Bulk operations.
     *
     * Authorization is re-checked per post inside the transaction. The request
     * already proved every id is in the user's tenancy; this proves the user's
     * *role* permits the specific verb on each one. Anything that fails
     * authorization aborts the whole batch.
     */
    public function bulk(BulkPostActionRequest $request, ApprovalService $approvals): RedirectResponse
    {
        $action = $request->input('action');
        $posts  = $request->posts();

        DB::transaction(function () use ($request, $action, $posts, $approvals) {
            foreach ($posts as $post) {
                $this->authorizeBulk($request, $action, $post);

                match ($action) {
                    'reschedule' => $this->bulkReschedule($request, $post),
                    'delete'     => $post->delete(),
                    'submit'     => $approvals->submit($post, $request->user()),
                    'approve'    => $request->input('decision') === 'changes_requested'
                        ? $approvals->requestChanges($post, $request->user(), $request->input('note'))
                        : $approvals->approve($post, $request->user(), $request->input('note')),
                    'assign'     => $post->forceFill(['assignee_id' => $request->input('assignee_id')])->save(),
                    'tag'        => $post->tags()->syncWithoutDetaching($request->input('tag_ids', [])),
                    'campaign'   => $post->forceFill(['campaign_id' => $request->input('campaign_id')])->save(),
                    default      => null,
                };
            }

            AuditLog::record('planner.bulk_action', null, [
                'action' => $action,
                'count'  => $posts->count(),
            ]);
        });

        return back()->with('status', "bulk-{$action}-".$posts->count());
    }

    protected function authorizeBulk(Request $request, string $action, Post $post): void
    {
        $ability = match ($action) {
            'delete'     => 'delete',
            'approve'    => 'approve',
            'reschedule' => 'reschedule',
            default      => 'update',
        };

        $this->authorize($ability, $post);
    }

    protected function bulkReschedule(BulkPostActionRequest $request, Post $post): void
    {
        // Two modes: move everything to one moment, or shift each post by a
        // relative amount (which is what "push the week back by a day" means).
        $shift = $request->integer('shift_minutes');

        $target = $shift && $post->scheduled_at
            ? $post->scheduled_at->copy()->addMinutes($shift)
            : Carbon::parse($request->input('scheduled_at'));

        $post->forceFill([
            'scheduled_at' => $target,
            'status'       => $post->status === Post::STATUS_DRAFT ? Post::STATUS_SCHEDULED : $post->status,
        ])->save();
    }

    /**
     * Everything the filter bar needs to render, scoped to the user's own
     * workspaces. Cheap because each is a narrow projection.
     *
     * @return array<string, mixed>
     */
    protected function filterOptions(Request $request, \Illuminate\Support\Collection $visible): array
    {
        $workspaces = Workspace::whereIn('id', $visible)
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'timezone']);

        return [
            'workspaces' => $workspaces,
            'campaigns'  => Campaign::whereIn('workspace_id', $visible)
                ->orderBy('name')->get(['id', 'workspace_id', 'name', 'color', 'status']),
            'pillars'    => ContentPillar::whereIn('workspace_id', $visible)
                ->orderBy('position')->get(['id', 'workspace_id', 'name', 'color']),
            'tags'       => Tag::whereIn('workspace_id', $visible)
                ->orderBy('name')->get(['id', 'workspace_id', 'name', 'color']),
            'assignees'  => $this->assignees($request),
            'providers'  => \App\Models\Channel::whereIn('workspace_id', $visible)
                ->distinct()->orderBy('provider')->pluck('provider'),
            'statuses'   => [
                Post::STATUS_DRAFT,
                Post::STATUS_PENDING_APPROVAL,
                Post::STATUS_SCHEDULED,
                Post::STATUS_PUBLISHING,
                Post::STATUS_PUBLISHED,
                Post::STATUS_PARTIALLY_FAILED,
                Post::STATUS_FAILED,
            ],
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array{id:int, name:string}> */
    protected function assignees(Request $request): \Illuminate\Support\Collection
    {
        $team = $request->user()->currentTeam;

        if (! $team) {
            return collect();
        }

        return $team->allUsers()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->sortBy('name')
            ->values();
    }
}
