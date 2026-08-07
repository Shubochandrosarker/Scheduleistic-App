<?php

namespace App\Social\Providers\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta's Graph API returns a short-lived (≈1-2 hour) user access token from
 * the standard authorization-code exchange. Exchanging it for a long-lived
 * (≈60-day) token before deriving a Page/IG-account token matters beyond
 * the user token's own lifetime: a Page token derived from a long-lived
 * user token is itself effectively non-expiring, while one derived from a
 * short-lived user token inherits that short lifetime — so skipping this
 * step silently shortens the life of every downstream channel token too.
 *
 * Shared by FacebookProvider and InstagramProvider (both exchange through
 * `graph.facebook.com`); Threads has its own distinct long-lived-token
 * endpoint and grant type, so it does not use this trait.
 */
trait ExchangesMetaLongLivedToken
{
    /**
     * @return array{token: string, expiresAt: ?Carbon}
     */
    protected function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::get(static::GRAPH.'/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'fb_exchange_token' => $shortLivedToken,
        ]);

        if ($response->failed()) {
            // Best-effort: publishing still works with the short-lived token
            // today, it will simply need reconnecting sooner than a
            // long-lived one would. Losing this exchange must never block
            // the connect flow outright.
            Log::warning("Meta long-lived token exchange failed for {$this->key()}: {$response->body()}");

            return ['token' => $shortLivedToken, 'expiresAt' => null];
        }

        $data = $response->json();

        return [
            'token' => (string) Arr::get($data, 'access_token', $shortLivedToken),
            'expiresAt' => isset($data['expires_in'])
                ? Carbon::now()->addSeconds((int) $data['expires_in'])
                : null,
        ];
    }
}
