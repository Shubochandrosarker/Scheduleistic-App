<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/** YouTube community / video metadata via the Google APIs (Data API v3). */
class YouTubeProvider extends AbstractOAuthProvider
{
    public function key(): string
    {
        return 'youtube';
    }

    public function label(): string
    {
        return 'YouTube';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function scopes(): array
    {
        return ['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube'];
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return $this->authorizeEndpoint().'?'.http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->clientId(),
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
            'scope'         => implode(' ', $this->scopes()),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
    }

    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($tokenResponse, 'sub', 'youtube'),
            name: 'YouTube',
            accessToken: (string) Arr::get($tokenResponse, 'access_token'),
            refreshToken: Arr::get($tokenResponse, 'refresh_token'),
            expiresAt: isset($tokenResponse['expires_in'])
                ? Carbon::now()->addSeconds((int) $tokenResponse['expires_in'])
                : null,
            scopes: $this->scopes(),
        );
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        // Video upload is a resumable, multi-step process; here we register the
        // upload intent. The worker performs the binary upload out of band.
        $response = Http::withToken($channel->access_token)
            ->post('https://www.googleapis.com/youtube/v3/videos?part=snippet,status', [
                'snippet' => ['title' => mb_substr($payload->content, 0, 100), 'description' => $payload->content],
                'status'  => ['privacyStatus' => 'private'],
            ]);

        if ($response->failed()) {
            throw new PublishException("YouTube publish failed: {$response->body()}", retryable: $response->serverError());
        }

        return new PublishResult((string) Arr::get($response->json(), 'id', ''), $response->json() ?? []);
    }
}
