<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $visible = $request->user()->visibleWorkspaceIds();

        $campaigns = Campaign::query()
            ->forWorkspaces($visible)
            ->with(['workspace:id,name,color', 'owner:id,name'])
            // withCount avoids the N+1 the audit flagged elsewhere: one
            // subquery instead of a posts() call per row.
            ->withCount([
                'posts',
                'posts as published_posts_count' => fn ($q) => $q->where('status', Post::STATUS_PUBLISHED),
            ])
            ->orderByDesc('starts_on')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Campaigns/Index', [
            'campaigns'  => $campaigns,
            'workspaces' => $request->user()->currentTeam?->workspaces()->get(['id', 'name', 'color']) ?? [],
            'statuses'   => Campaign::STATUSES,
        ]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = Campaign::create($request->safe()->all());

        AuditLog::record('campaign.created', $campaign, ['name' => $campaign->name]);

        return back()->with('status', 'campaign-created');
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($request->safe()->all());

        AuditLog::record('campaign.updated', $campaign);

        return back()->with('status', 'campaign-updated');
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        DB::transaction(function () use ($campaign) {
            // Posts outlive their campaign — detach rather than cascade, so
            // deleting a campaign never destroys published content.
            $campaign->posts()->update(['campaign_id' => null]);
            $campaign->ideas()->update(['campaign_id' => null]);
            $campaign->delete();
        });

        AuditLog::record('campaign.deleted', $campaign);

        return back()->with('status', 'campaign-deleted');
    }
}
