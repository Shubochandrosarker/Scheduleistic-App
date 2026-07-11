<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\UsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(private readonly UsageService $usage) {}

    public function index(Request $request): Response
    {
        $organization = $request->user()->currentTeam;

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $organization->workspaces()
                ->withCount(['channels', 'feeds', 'timeSlots'])
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'timezone'    => ['nullable', 'string', 'max:64'],
            'color'       => ['nullable', 'string', 'max:9'],
        ]);

        $team = $request->user()->currentTeam;

        if (! $this->usage->allows($team, 'workspaces')) {
            return back()->withErrors(['plan' => 'Your plan\'s workspace limit has been reached. Upgrade to add more.']);
        }

        $team->workspaces()->create($validated);

        return back()->with('status', 'workspace-created');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($workspace->team_id === $request->user()->currentTeam->id, 403);

        $workspace->delete();

        return back()->with('status', 'workspace-deleted');
    }
}
