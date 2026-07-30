<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentPillarRequest;
use App\Models\ContentPillar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContentPillarController extends Controller
{
    public function store(StoreContentPillarRequest $request): RedirectResponse
    {
        $workspace = $request->workspace();

        ContentPillar::create([
            ...$request->safe()->all(),
            'position' => $workspace->pillars()->max('position') + 1,
        ]);

        return back()->with('status', 'pillar-created');
    }

    public function update(StoreContentPillarRequest $request, ContentPillar $pillar): RedirectResponse
    {
        $this->authorize('update', $pillar);

        $pillar->update($request->safe()->except('workspace_id'));

        return back()->with('status', 'pillar-updated');
    }

    public function destroy(Request $request, ContentPillar $pillar): RedirectResponse
    {
        $this->authorize('delete', $pillar);

        $pillar->posts()->update(['content_pillar_id' => null]);
        $pillar->delete();

        return back()->with('status', 'pillar-deleted');
    }
}
