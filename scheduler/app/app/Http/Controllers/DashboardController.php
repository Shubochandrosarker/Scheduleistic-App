<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Post;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly UsageService $usage) {}

    public function index(Request $request): Response
    {
        $team         = $request->user()->currentTeam;
        $workspaceIds = $team->workspaces()->pluck('id');

        $upcoming = Post::whereIn('workspace_id', $workspaceIds)
            ->where('status', Post::STATUS_SCHEDULED)
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->with('workspace:id,name')
            ->limit(5)
            ->get()
            ->map(fn (Post $p) => [
                'id'           => $p->id,
                'content'      => Str::limit($p->content, 80),
                'workspace'    => $p->workspace?->name,
                // ISO so the dashboard can format it in the viewer's locale.
                'scheduled_at' => optional($p->scheduled_at)->toIso8601String(),
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'workspaces'      => $workspaceIds->count(),
                'channels'        => Channel::whereIn('workspace_id', $workspaceIds)->count(),
                'scheduled'       => Post::whereIn('workspace_id', $workspaceIds)
                    ->where('status', Post::STATUS_SCHEDULED)->count(),
                'published_month' => Post::whereIn('workspace_id', $workspaceIds)
                    ->where('status', Post::STATUS_PUBLISHED)
                    ->where('updated_at', '>=', now()->startOfMonth())->count(),
            ],
            'plan'     => $team->plan,
            'usage'    => $this->usage->snapshot($team),
            'upcoming' => $upcoming,
        ]);
    }
}
