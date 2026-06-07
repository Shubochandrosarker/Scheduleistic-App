<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = $request->user()->currentTeam;

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $organization->workspaces()
                ->withCount('channels')
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

        $request->user()->currentTeam->workspaces()->create($validated);

        return back()->with('status', 'workspace-created');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($workspace->team_id === $request->user()->currentTeam->id, 403);

        $workspace->delete();

        return back()->with('status', 'workspace-deleted');
    }
}
