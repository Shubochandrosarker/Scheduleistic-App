<?php

namespace App\Services;

use App\Models\Team;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Generates per-network captions in the brand voice using a pluggable LLM.
 * Set AI_FAKE=true (or omit credentials) to use the deterministic local stub.
 *
 * When a Team is supplied, generation is grounded in that team's own shared
 * brain (BrainGatewayClient — the wpistic-ai-gateway Brain API) so output
 * reflects the brand's own past content instead of a generic prompt.
 * Grounding is best-effort: an unconfigured/unreachable gateway silently
 * falls back to ungrounded generation.
 */
class AiAssistant
{
    public function __construct(private readonly BrainGatewayClient $brain) {}

    /**
     * Generate a caption for one provider.
     */
    public function caption(string $topic, string $provider = 'default', ?Team $team = null): string
    {
        if ($this->isFake()) {
            return $this->fakeCaption($topic, $provider);
        }

        $prompt = str_replace(':topic', $topic, config("ai.prompts.{$provider}", config('ai.prompts.default')));

        $messages = [
            ['role' => 'system', 'content' => config('ai.system')],
        ];

        if ($team && $grounding = $this->groundingMessage($team, $topic)) {
            $messages[] = $grounding;
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        return trim($this->chat($messages, [], $team, "caption:{$provider}"));
    }

    /**
     * Generate captions for several networks at once.
     *
     * @param  array<int, string>  $providers
     * @return array<string, string>
     */
    public function captions(string $topic, array $providers, ?Team $team = null): array
    {
        $out = [];

        foreach ($providers as $provider) {
            $out[$provider] = $this->caption($topic, $provider, $team);
        }

        return $out;
    }

    /**
     * Low-level chat-completions call shared by caption generation and the
     * post-assist agents (PostAiAgents) — one place owning the HTTP call,
     * model/endpoint config, error handling, and (when a Team is supplied)
     * self-reporting this call's real usage back to the brain gateway so
     * the Brain Home Dashboard sees this brand's true cost/token figures —
     * see BrainGatewayClient::reportUsage()'s docblock for why that call
     * exists at all.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, array $opts = [], ?Team $team = null, ?string $agent = null): string
    {
        $model = $opts['model'] ?? config('ai.model');

        $response = Http::withToken(config('ai.key'))
            ->post(config('ai.endpoint'), array_merge([
                'model' => $model,
                'messages' => $messages,
            ], $opts));

        if ($response->failed()) {
            if ($team) {
                $this->brain->reportUsage($team, $this->providerLabel(), $model, 0, 0, refused: true, agent: $agent);
            }
            throw new PublishException('AI generation failed: '.$response->body(), retryable: false);
        }

        $content = (string) Arr::get($response->json(), 'choices.0.message.content', '');

        if ($team) {
            $inputTokens = (int) Arr::get($response->json(), 'usage.prompt_tokens', $this->estimateTokens(implode(' ', array_column($messages, 'content'))));
            $outputTokens = (int) Arr::get($response->json(), 'usage.completion_tokens', $this->estimateTokens($content));
            $this->brain->reportUsage($team, $this->providerLabel(), $model, $inputTokens, $outputTokens, refused: trim($content) === '', agent: $agent);
        }

        return $content;
    }

    public function isFake(): bool
    {
        return (bool) config('ai.fake') || empty(config('ai.key'));
    }

    /** Relevant brand-brain context for $query, formatted as an extra system message, or null. */
    private function groundingMessage(Team $team, string $query): ?array
    {
        $chunks = $this->brain->searchForTeam($team, $query);
        if (empty($chunks)) {
            return null;
        }

        return [
            'role' => 'system',
            'content' => "Relevant knowledge from this workspace's shared brain — use it if helpful, ignore what doesn't apply, never contradict the topic:\n\n"
                .implode("\n---\n", $chunks),
        ];
    }

    private function fakeCaption(string $topic, string $provider): string
    {
        return ucfirst($provider).": {$topic} — here is a brand-voice draft you can edit before scheduling.";
    }

    /** A short label for which service actually generated the completion, for reportUsage(). */
    private function providerLabel(): string
    {
        return parse_url((string) config('ai.endpoint'), PHP_URL_HOST) ?: 'unknown';
    }

    /** Rough token estimate (4 chars ≈ 1 token) when a provider's response doesn't include real usage. */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
