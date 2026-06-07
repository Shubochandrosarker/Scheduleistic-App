<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspaceAccess;
use App\Models\Post;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function __construct(private readonly ApprovalService $approvals) {}

    public function submit(Request $request, Post $post): RedirectResponse
    {
        $this->guardPost($request, $post);

        $this->approvals->submit($post, $request->user());

        return back()->with('status', 'submitted-for-approval');
    }

    public function decide(Request $request, Post $post): RedirectResponse
    {
        $this->guardPost($request, $post);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject,request_changes'],
            'comment'  => ['nullable', 'string', 'max:2000'],
        ]);

        match ($validated['decision']) {
            'approve'         => $this->approvals->approve($post, $request->user(), $validated['comment'] ?? null),
            'reject'          => $this->approvals->reject($post, $request->user(), $validated['comment'] ?? null),
            'request_changes' => $this->approvals->requestChanges($post, $request->user(), $validated['comment'] ?? null),
        };

        return back()->with('status', 'approval-decided');
    }
}
