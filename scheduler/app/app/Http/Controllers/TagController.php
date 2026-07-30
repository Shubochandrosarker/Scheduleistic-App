<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function store(StoreTagRequest $request): RedirectResponse
    {
        // resolve() is find-or-create by slug within the workspace, so
        // submitting an existing tag name is a no-op rather than a duplicate.
        $tag = Tag::resolve($request->integer('workspace_id'), $request->string('name'));

        if ($request->filled('color')) {
            $tag->update(['color' => $request->string('color')]);
        }

        return back()->with('status', 'tag-created');
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return back()->with('status', 'tag-deleted');
    }
}
