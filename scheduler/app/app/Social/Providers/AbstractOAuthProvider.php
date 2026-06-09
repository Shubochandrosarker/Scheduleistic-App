<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Contracts\SocialProvider;
use App\Social\Data\ConnectedAccount;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Shared OAuth2 (authorization-code) plumbing for the network drivers.
 * Subclasses declare their endpoints, scopes, account mapping, and publish
 * implementation; everything common lives here.
 */
abstract class AbstractOAuthProvider implements SocialProvider
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract protected function authorizeEndpoint(): string;

    abstract protected function tokenEndpoint(): string;

    /** @return array<int, string> */
    abstract protected function scopes(): array;

    /** Map a token response into a normalized ConnectedAccount. */
    abstract protected function mapAccount(array $tokenResponse): ConnectedAccount;

    public function rateLimit(): array
    {
        return ['max' => 30, 'per_seconds' => 60];
    }

    /**
     * Default: no analytics. Drivers that support it override this.
     *
     * @return array<string, int>
     */
    public function fetchMetrics(Channel $channel, string $providerPostId): array
    {
        return [];
    }

    /** OAuth client credentials, pulled from config/services.php. */
    protected function clientId(): ?string
    {
        return config("services.{$this->key()}.client_id");
    }

    protected function clientSecret(): ?string
    {
        return config("services.{$this->key()}.client_secret");
    }

    /** Whether this OAuth app has credentials configured on this instance. */
    public function isConfigured(): bool
    {
        return ! empty($this->clientId()) && ! empty($this->clientSecret());
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return $this->authorizeEndpoint().'?'.http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->clientId(),
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
            'scope'         => implode(' ', $this->scopes()),
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): ConnectedAccount
    {
        $response = Http::asForm()->post($this->tokenEndpoint(), [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        if ($response->failed()) {
            throw new PublishException(
                "OAuth token exchange failed for {$this->key()}: ".$response->body(),
                retryable: false,
            );
        }

        return $this->mapAccount($response->json());
    }

    public function refreshToken(Channel $channel): void
    {
        if (! $channel->refresh_token) {
            return;
        }

        $response = Http::asForm()->post($this->tokenEndpoint(), [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $channel->refresh_token,
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        if ($response->failed()) {
            $channel->update(['status' => 'expired']);

            return;
        }

        $data = $response->json();

        $channel->update([
            'access_token'     => Arr::get($data, 'access_token', $channel->access_token),
            'refresh_token'    => Arr::get($data, 'refresh_token', $channel->refresh_token),
            'token_expires_at' => isset($data['expires_in'])
                ? Carbon::now()->addSeconds((int) $data['expires_in'])
                : $channel->token_expires_at,
            'status'           => 'connected',
        ]);
    }
}
