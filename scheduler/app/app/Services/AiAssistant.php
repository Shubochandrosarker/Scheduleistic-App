<?php

namespace App\Services;

use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Generates per-network captions in the brand voice using a pluggable LLM.
 * Set AI_FAKE=true (or omit credentials) to use the deterministic local stub.
 */
class AiAssistant
{
    /**
     * Generate a caption for one provider.
     */
    public function caption(string $topic, string $provider = 'default'): string
    {
        if ($this->isFake()) {
            return $this->fakeCaption($topic, $provider);
        }

        $prompt = str_replace(':topic', $topic, config("ai.prompts.{$provider}", config('ai.prompts.default')));

        $response = Http::withToken(config('ai.key'))
            ->post(config('ai.endpoint'), [
                'model'    => config('ai.model'),
                'messages' => [
                    ['role' => 'system', 'content' => config('ai.system')],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new PublishException('AI generation failed: '.$response->body(), retryable: false);
        }

        return trim((string) Arr::get($response->json(), 'choices.0.message.content', ''));
    }

    /**
     * Generate captions for several networks at once.
     *
     * @param  array<int, string>  $providers
     * @return array<string, string>
     */
    public function captions(string $topic, array $providers): array
    {
        $out = [];

        foreach ($providers as $provider) {
            $out[$provider] = $this->caption($topic, $provider);
        }

        return $out;
    }

    public function isFake(): bool
    {
        return (bool) config('ai.fake') || empty(config('ai.key'));
    }

    private function fakeCaption(string $topic, string $provider): string
    {
        return ucfirst($provider).": {$topic} — here is a brand-voice draft you can edit before scheduling.";
    }
}
