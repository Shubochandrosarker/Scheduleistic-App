<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostHistoryFilterRequest;
use App\Models\Campaign;
use App\Models\Channel;
use App\Models\PostTarget;
use App\Models\Workspace;
use App\Services\PostHistoryQuery;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The permanent, unbounded post/schedule history: every past publish attempt
 * across every channel, filterable and CSV-exportable — distinct from the
 * planner, which only ever shows a bounded upcoming/recent window.
 */
class PostHistoryController extends Controller
{
    public function __construct(private readonly PostHistoryQuery $history) {}

    public function index(PostHistoryFilterRequest $request): Response
    {
        $visible = $request->user()->visibleWorkspaceIds();

        $targets = $this->history->build($request, $visible)
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('History/Index', [
            'targets' => $targets->through(fn (PostTarget $target) => $this->history->row($target)),
            // Echoed back so the client can rebuild its filter state from the
            // URL alone — filters are shareable links, not hidden state.
            'filters' => $request->only([
                'workspace_ids', 'providers', 'statuses', 'campaign_ids',
                'search', 'from', 'to', 'sort', 'direction', 'per_page',
            ]),
            'summary' => $this->history->summary($request, $visible),
            'options' => $this->filterOptions($visible),
        ]);
    }

    /**
     * CSV export of the same filtered set, capped rather than the literal
     * unbounded table — a broad filter must not turn a download link into an
     * unbounded memory write. 5,000 rows is generous for "export what I'm
     * looking at"; anyone needing more should narrow the date range first.
     */
    public function export(PostHistoryFilterRequest $request): StreamedResponse
    {
        $visible = $request->user()->visibleWorkspaceIds();

        $targets = $this->history->build($request, $visible)->limit(5000)->get();

        $filename = 'post-history-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($targets) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Scheduled at', 'Published at', 'Status', 'Attempts', 'Provider',
                'Channel', 'Workspace', 'Campaign', 'Title', 'Error',
            ]);

            foreach ($targets as $target) {
                fputcsv($out, [
                    $target->post?->scheduled_at?->toDateTimeString(),
                    $target->published_at?->toDateTimeString(),
                    $target->status,
                    $target->attempts,
                    $target->channel?->provider,
                    $target->channel?->name,
                    $target->post?->workspace?->name,
                    $target->post?->campaign?->name,
                    $target->post?->title,
                    $target->error,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Everything the filter bar needs to render, scoped to the user's own
     * workspaces — mirrors PlannerController::filterOptions().
     *
     * @param  Collection<int, int>  $visible
     * @return array<string, mixed>
     */
    protected function filterOptions(Collection $visible): array
    {
        return [
            'workspaces' => Workspace::whereIn('id', $visible)->orderBy('name')->get(['id', 'name', 'color']),
            'campaigns' => Campaign::whereIn('workspace_id', $visible)->orderBy('name')->get(['id', 'workspace_id', 'name', 'color']),
            'providers' => Channel::whereIn('workspace_id', $visible)->distinct()->orderBy('provider')->pluck('provider'),
            'statuses' => [
                PostTarget::STATUS_PENDING,
                PostTarget::STATUS_PUBLISHING,
                PostTarget::STATUS_PUBLISHED,
                PostTarget::STATUS_FAILED,
            ],
        ];
    }
}
