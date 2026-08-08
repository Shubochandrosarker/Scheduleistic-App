<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Services\ChannelHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The channel health centre: one row per connected profile with its live state,
 * last successful publish, last sync, and the reconnect action.
 */
class ChannelHealthController extends Controller
{
    public function __construct(private readonly ChannelHealthService $health) {}

    public function index(Request $request): Response
    {
        $visible = $request->user()->visibleWorkspaceIds();

        $channels = Channel::query()
            ->whereIn('workspace_id', $visible)
            ->with(['workspace:id,name,color', 'openHealthEvents'])
            ->orderBy('workspace_id')
            ->orderBy('provider')
            ->get();

        return Inertia::render('Channels/Health', [
            'channels' => $channels->map(fn (Channel $channel) => [
                'id' => $channel->id,
                'provider' => $channel->provider,
                'name' => $channel->name,
                'workspace' => $channel->workspace?->only(['id', 'name', 'color']),
                'health_state' => $channel->health_state,
                'can_publish' => $channel->canPublish(),
                'token_expires_at' => $channel->token_expires_at?->toIso8601String(),
                'last_published_at' => $channel->last_published_at?->toIso8601String(),
                'last_metrics_sync_at' => $channel->last_metrics_sync_at?->toIso8601String(),
                'last_health_check_at' => $channel->last_health_check_at?->toIso8601String(),
                'issues' => $channel->openHealthEvents->map(fn ($e) => [
                    'state' => $e->state,
                    'severity' => $e->severity,
                    'message' => $e->message,
                    'detected_at' => $e->detected_at?->toIso8601String(),
                ])->values(),
            ])->values(),
            'summary' => $this->health->summarize($channels),
        ]);
    }

    /** Re-evaluate every visible channel on demand. */
    public function refresh(Request $request): RedirectResponse
    {
        $channels = Channel::whereIn('workspace_id', $request->user()->visibleWorkspaceIds())->get();

        foreach ($channels as $channel) {
            $this->health->evaluate($channel);
        }

        return back()->with('status', 'health-refreshed');
    }

    /**
     * The full health timeline for one channel, including resolved events —
     * the index above only ever loads `openHealthEvents`. Documented as an
     * intended feature (`docs/SCHEDULEISTIC_2_ADMIN_GUIDE.md` "use it as the
     * history when a tenant asks why a post failed three weeks ago") that
     * had no read path or page until now.
     */
    public function history(Request $request, Channel $channel): Response
    {
        abort_unless(
            $request->user()->visibleWorkspaceIds()->contains($channel->workspace_id),
            403,
        );

        $channel->load('workspace:id,name,color');

        $events = $channel->healthEvents()
            ->latest('detected_at')
            ->paginate(25)
            ->through(fn ($e) => [
                'id' => $e->id,
                'state' => $e->state,
                'severity' => $e->severity,
                'message' => $e->message,
                'detected_at' => $e->detected_at?->toIso8601String(),
                'resolved_at' => $e->resolved_at?->toIso8601String(),
                'resolved' => $e->resolved_at !== null,
            ]);

        return Inertia::render('Channels/HealthHistory', [
            'channel' => [
                'id' => $channel->id,
                'provider' => $channel->provider,
                'name' => $channel->name,
                'workspace' => $channel->workspace?->only(['id', 'name', 'color']),
                'health_state' => $channel->health_state,
            ],
            'events' => $events,
        ]);
    }
}
