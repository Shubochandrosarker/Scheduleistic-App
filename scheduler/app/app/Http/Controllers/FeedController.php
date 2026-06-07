<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspaceAccess;
use App\Models\Feed;
use App\Models\Workspace;
use App\Support\SsrfGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function store(Request $request, Workspace $workspace, SsrfGuard $ssrf): RedirectResponse
    {
        $this->guardWorkspace($request, $workspace);

        $validated = $request->validate([
            'url'  => [
                'required', 'url', 'max:500',
                // Reject internal/reserved targets up front (defense in depth;
                // the fetch path re-checks with DNS resolution).
                fn ($attr, $value, $fail) => $ssrf->isAllowed($value) ?: $fail('That feed URL is not allowed.'),
            ],
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
