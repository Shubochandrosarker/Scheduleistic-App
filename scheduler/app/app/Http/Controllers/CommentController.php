<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspaceAccess;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function store(Request $request, Post $post): RedirectResponse
    {
        $this->guardPost($request, $post);

        $validated = $request->validate([
            'body'       => ['required', 'string', 'max:5000'],
            'mentions'   => ['nullable', 'array'],
            'mentions.*' => ['integer'],
        ]);

        $post->comments()->create([
            'user_id'  => $request->user()->id,
            'body'     => $validated['body'],
            'mentions' => $validated['mentions'] ?? [],
        ]);

        return back()->with('status', 'comment-added');
    }
}
