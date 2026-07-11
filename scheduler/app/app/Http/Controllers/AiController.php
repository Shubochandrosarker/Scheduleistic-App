<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspaceAccess;
use App\Models\Post;
use App\Services\AiAssistant;
use App\Services\PlanService;
use App\Services\PostAiAgents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function __construct(
        private readonly AiAssistant $ai,
        private readonly PostAiAgents $agents,
        private readonly PlanService $plans,
    ) {}

    /** Generate brand-voice captions for the requested networks. */
    public function generate(Request $request): JsonResponse
    {
        // Must belong to an organization (cost is attributable to a tenant).
        abort_unless($request->user()->currentTeam, 403);

        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'providers' => ['required', 'array', 'min:1'],
            'providers.*' => ['string'],
        ]);

        return response()->json([
            'captions' => $this->ai->captions($validated['topic'], $validated['providers'], $request->user()->currentTeam),
        ]);
    }

    /** Clean up / rewrite an existing post's content, grounded in its workspace's shared brand brain. */
    public function rewrite(Request $request, Post $post): JsonResponse
    {
        $this->guardPost($request, $post);
        $this->guardAgentsPlan($post);

        return response()->json($this->agents->rewrite($post));
    }

    /** Suggest hashtags for an existing post. */
    public function optimizeHashtags(Request $request, Post $post): JsonResponse
    {
        $this->guardPost($request, $post);
        $this->guardAgentsPlan($post);

        return response()->json($this->agents->optimizeHashtags($post));
    }

    /** Score an existing post's readiness to publish. */
    public function qualityCheck(Request $request, Post $post): JsonResponse
    {
        $this->guardPost($request, $post);
        $this->guardAgentsPlan($post);

        return response()->json($this->agents->qualityCheck($post));
    }

    /** The post AI agents (rewrite/hashtags/quality) are gated behind the owning team's plan. */
    private function guardAgentsPlan(Post $post): void
    {
        abort_unless($this->plans->feature($post->workspace->team, 'ai_agents'), 402);
    }
}
