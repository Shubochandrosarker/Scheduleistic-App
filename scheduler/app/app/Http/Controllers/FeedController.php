<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspaceAccess;
use App\Models\Feed;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->guardWorkspace($request, $workspace);

        $validated = $request->validate([
            'url'  => ['required', 'url', 'max:500'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $workspace->feeds()->create($validated);

        return back()->with('status', 'feed-added');
    }

    public function destroy(Request $request, Workspace $workspace, Feed $feed): RedirectResponse
    {
        $this->guardWorkspace($request, $workspace);
        abort_unless($feed->workspace_id === $workspace->id, 403);

        $feed->delete();

        return back()->with('status', 'feed-removed');
    }
}
