<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Post-composition AI agents — cleanup/rewrite, hashtag suggestion, and a
 * pre-publish quality check — modeled on Postistic's write-back AI agents
 * (Postistic_AI_Agents in the WordPressistic ecosystem), adapted for social
 * posts. Unlike the one-shot caption generator in AiAssistant, every agent
 * here writes its result back onto the target Post so the change is
 * immediately usable, and grounds/records its work through the shared brain
 * (BrainGatewayClient) the same way Postistic's agents do.
 */
class PostAiAgents
{
    public function __construct(
        private readonly AiAssistant $ai,
        private readonly BrainGatewayClient $brain,
    ) {}

    /** Clean up / rewrite a post's base content. Writes to Post::content. */
    public function rewrite(Post $post): array
    {
        if ($this->ai->isFake()) {
            return $this->applyRewrite($post, $this->fakeRewrite($post));
        }

        $messages = [
            ['role' => 'system', 'content' => 'You are a social media copy editor. Tighten grammar, collapse '
                .'redundant whitespace, and remove filler — preserve the author\'s meaning, tone, links, and '
                .'hashtags. Respond with strict JSON only, no markdown fences: {"content": "<cleaned text>"}.'],
        ];
        if ($grounding = $this->groundingMessage($post, (string) $post->content)) {
            $messages[] = $grounding;
        }
        $messages[] = ['role' => 'user', 'content' => "Clean up this post:\n\n".mb_substr((string) $post->content, 0, 4000)];

        $data = $this->decodeJson($this->ai->chat($messages, ['max_tokens' => 1000]));

        return $this->applyRewrite($post, is_array($data) ? (string) ($data['content'] ?? '') : '');
    }

    /** Suggest hashtags for a post. Writes to Post::hashtags. */
    public function optimizeHashtags(Post $post): array
    {
        if ($this->ai->isFake()) {
            return $this->applyHashtags($post, $this->fakeHashtags($post));
        }

        $messages = [
            ['role' => 'system', 'content' => 'You are a social media hashtag strategist. Suggest 3-5 relevant, '
                .'non-generic hashtags for this post. Respond with strict JSON only, no markdown fences: '
                .'{"hashtags": ["#example", ...]}.'],
        ];
        if ($grounding = $this->groundingMessage($post, (string) $post->content)) {
            $messages[] = $grounding;
        }
        $messages[] = ['role' => 'user', 'content' => "Suggest hashtags for:\n\n".mb_substr((string) $post->content, 0, 2000)];

        $data = $this->decodeJson($this->ai->chat($messages, ['max_tokens' => 200]));
        $hashtags = is_array($data) && is_array($data['hashtags'] ?? null) ? $data['hashtags'] : [];

        return $this->applyHashtags($post, $hashtags);
    }

    /** Score readiness to publish. Writes to Post::ai_quality_report — informational only, never blocks publishing itself. */
    public function qualityCheck(Post $post): array
    {
        if ($this->ai->isFake()) {
            return $this->applyQuality($post, $this->fakeQuality($post));
        }

        $messages = [
            ['role' => 'system', 'content' => 'You are a social media quality checker. Score readability/engagement '
                .'potential 0-100, list concrete issues (too long, missing call to action, broken-looking links), '
                .'and say whether the post is ready to publish as-is. Respond with strict JSON only, no markdown '
                .'fences: {"score": 0, "issues": ["..."], "ready": true}.'],
        ];
        if ($grounding = $this->groundingMessage($post, (string) $post->content)) {
            $messages[] = $grounding;
        }
        $messages[] = ['role' => 'user', 'content' => "Review this post:\n\n".mb_substr((string) $post->content, 0, 4000)];

        $data = $this->decodeJson($this->ai->chat($messages, ['max_tokens' => 400]));

        return $this->applyQuality($post, is_array($data) ? $data : []);
    }

    private function applyRewrite(Post $post, string $content): array
    {
        $content = trim($content);
        if ($content === '' || $content === trim((string) $post->content)) {
            return ['applied' => false];
        }

        $post->update(['content' => $content]);
        $this->ingest($post, $content, 'rewrite');

        return ['applied' => true, 'content' => $content];
    }

    private function applyHashtags(Post $post, array $hashtags): array
    {
        $hashtags = array_values(array_unique(array_filter(array_map(
            static fn ($tag) => Str::start((string) preg_replace('/[^A-Za-z0-9_]/', '', (string) $tag), '#'),
            $hashtags
        ), static fn ($tag) => $tag !== '#')));

        if (empty($hashtags)) {
            return ['applied' => false];
        }

        $post->update(['hashtags' => $hashtags]);
        $this->ingest($post, implode(' ', $hashtags), 'hashtags');

        return ['applied' => true, 'hashtags' => $hashtags];
    }

    private function applyQuality(Post $post, array $data): array
    {
        $report = [
            'score' => isset($data['score']) ? max(0, min(100, (int) $data['score'])) : null,
            'ready' => ! empty($data['ready']),
            'issues' => is_array($data['issues'] ?? null) ? array_values(array_map('strval', $data['issues'])) : [],
            'checked_at' => now()->toIso8601String(),
        ];

        $post->update(['ai_quality_report' => $report]);

        // Not ingested into the shared brain: a quality score is an
        // analysis of the post, not reusable brand knowledge like a
        // rewritten caption or its hashtags.
        return ['applied' => true, 'report' => $report];
    }

    /** Retrieve relevant brand-brain context for $query, or null when there's none/unconfigured. */
    private function groundingMessage(Post $post, string $query): ?array
    {
        $team = $post->workspace?->team;
        if (! $team || trim($query) === '') {
            return null;
        }

        $chunks = $this->brain->searchForTeam($team, $query);
        if (empty($chunks)) {
            return null;
        }

        return [
            'role' => 'system',
            'content' => "Relevant knowledge from this workspace's shared brain — use it if helpful, ignore what doesn't apply:\n\n"
                .implode("\n---\n", $chunks),
        ];
    }

    /** Feed a successfully-applied result back into the shared brain for future grounding. Best-effort. */
    private function ingest(Post $post, string $text, string $category): void
    {
        $team = $post->workspace?->team;
        if (! $team) {
            return;
        }

        $this->brain->ingestForTeam($team, $text, "post:{$post->id}", $category);
    }

    /** Parse a model reply as JSON, tolerating a ```json fence some providers add despite instructions. */
    private function decodeJson(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $raw, $m)) {
            $raw = trim($m[1]);
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    private function fakeRewrite(Post $post): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $post->content));
    }

    private function fakeHashtags(Post $post): array
    {
        preg_match_all('/\b[a-zA-Z]{5,}\b/', (string) $post->content, $matches);
        $words = array_slice(array_unique(array_map('strtolower', $matches[0] ?? [])), 0, 3);

        return array_map(static fn ($word) => '#'.$word, $words);
    }

    private function fakeQuality(Post $post): array
    {
        $length = mb_strlen(trim((string) $post->content));

        return [
            'score' => (int) min(100, $length * 2),
            'ready' => $length >= 10,
            'issues' => $length >= 10 ? [] : ['Content is very short.'],
        ];
    }
}
