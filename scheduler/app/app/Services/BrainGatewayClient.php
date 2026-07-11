<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client for wpistic-ai-gateway's cross-product Brain API (POST
 * /v1/brain/search, POST /v1/brain/ingest) — lets Scheduleistic's AI
 * features ground their output in a team's own knowledge (past captions,
 * brand voice) and feed successful output back into it, the same way the
 * Postistic WordPress plugin's Postistic_Brain_Client consumes this gateway
 * (see WordPressistic-Core-System-Developments/cloudflare/wpistic-ai-gateway
 * and its docs/BRAIN-API.md).
 *
 * Every call here is best-effort and never throws: a misconfigured or
 * unreachable gateway degrades AI features to working ungrounded, it never
 * fails them. Set BRAIN_GATEWAY_FAKE=true (or leave the URL/secret empty)
 * to use the deterministic local no-op — same convention as
 * AiAssistant::isFake().
 */
class BrainGatewayClient
{
    /** This product's identity on the gateway (principal.productId). */
    private const PRODUCT = 'scheduleistic';

    public function isFake(): bool
    {
        return (bool) config('ai.brain_gateway.fake')
            || ! config('ai.brain_gateway.enabled')
            || empty(config('ai.brain_gateway.url'))
            || empty(config('ai.brain_gateway.secret'));
    }

    /**
     * Search the shared brain for knowledge relevant to $query, scoped to
     * this team's tenant namespace.
     *
     * @return string[] Chunk texts — empty when unconfigured, unreachable, or no match.
     */
    public function searchForTeam(Team $team, string $query, int $k = 6): array
    {
        $query = trim($query);
        if ($this->isFake() || $query === '') {
            return [];
        }

        $data = $this->request($team, '/v1/brain/search', [
            'query' => mb_substr($query, 0, 4000),
            'k' => max(1, min(20, $k)),
        ]);

        if (! is_array($data) || empty($data['chunks']) || ! is_array($data['chunks'])) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($chunk) => isset($chunk['text']) ? (string) $chunk['text'] : '',
            $data['chunks']
        )));
    }

    /**
     * Store a piece of knowledge in the shared brain, scoped to this team's
     * tenant namespace — e.g. a caption an agent just cleaned up, so later
     * generations for the same brand can draw on it.
     */
    public function ingestForTeam(Team $team, string $text, ?string $source = null, ?string $category = null): bool
    {
        $text = trim($text);
        if ($this->isFake() || $text === '') {
            return false;
        }

        $body = ['text' => mb_substr($text, 0, 2_000_000)];
        if ($source) {
            $body['source'] = mb_substr($source, 0, 200);
        }
        if ($category) {
            $body['category'] = $category;
        }

        $data = $this->request($team, '/v1/brain/ingest', $body);

        return is_array($data) && ! empty($data['ok']);
    }

    /** Shared request plumbing: sign + POST + decode. Swallows all failures to null. */
    private function request(Team $team, string $path, array $body): ?array
    {
        $url = rtrim((string) config('ai.brain_gateway.url'), '/').$path;

        try {
            $response = Http::withHeaders($this->signedHeaders($team))
                ->timeout(10)
                ->post($url, $body);
        } catch (Throwable $e) {
            Log::info('wpistic brain gateway request failed', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * The signed X-WPistic-* headers wpistic-ai-gateway's authenticate()
     * expects (see that repo's src/auth.ts) — the same envelope the
     * Postistic WordPress plugin's brain client sends, extended with
     * productId/tenantId for this cross-product Brain API. tenantId is this
     * team's own numeric id: Scheduleistic already has a stable per-tenant
     * identifier, unlike Postistic (one WP install = one tenant) which had
     * to mint and persist a UUID for the same purpose.
     */
    private function signedHeaders(Team $team): array
    {
        $principal = [
            'userId' => 0,
            'tier' => $this->tierFor($team),
            'emailVerified' => true,
            'storageTarget' => 'cloud',
            'sharedLearning' => false,
            'productId' => self::PRODUCT,
            'tenantId' => (string) $team->id,
        ];

        $principalRaw = $this->base64UrlEncode(json_encode($principal));
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$principalRaw, (string) config('ai.brain_gateway.secret'));

        return [
            'Content-Type' => 'application/json',
            'X-WPistic-Principal' => $principalRaw,
            'X-WPistic-Timestamp' => $timestamp,
            'X-WPistic-Signature' => $signature,
        ];
    }

    /** Maps Scheduleistic's plan keys onto the gateway's tier vocabulary (free/starter/pro/agency). */
    private function tierFor(Team $team): string
    {
        return match ($team->plan) {
            'free' => 'free',
            'pro' => 'pro',
            'agency', 'scale' => 'agency',
            default => 'free',
        };
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
