<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\Concerns\SwitchesAvailableAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/** Pinterest pin creation. A board id (channel meta) and an image are required. */
class PinterestProvider extends AbstractOAuthProvider
{
    use SwitchesAvailableAccount;

    public function key(): string
    {
        return 'pinterest';
    }

    public function label(): string
    {
        return 'Pinterest';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://www.pinterest.com/oauth/';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://api.pinterest.com/v5/oauth/token';
    }

    protected function scopes(): array
    {
        return ['boards:read', 'pins:read', 'pins:write'];
    }

    /**
     * Pinterest's v5 API documents HTTP Basic client authentication for the
     * token endpoint rather than the client_id/client_secret body params
     * the generic OAuth2 flow (and every other driver here) sends — flagged
     * in the platform-activation audit as needing verification against a
     * real Pinterest app; this reflects Pinterest's documented v5 shape.
     */
    public function exchangeCode(string $code, string $redirectUri): ConnectedAccount
    {
        $response = Http::asForm()
            ->withBasicAuth((string) $this->clientId(), (string) $this->clientSecret())
            ->post($this->tokenEndpoint(), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if ($response->failed()) {
            throw new PublishException(
                'OAuth token exchange failed for pinterest: '.$response->body(),
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

        $response = Http::asForm()
            ->withBasicAuth((string) $this->clientId(), (string) $this->clientSecret())
            ->post($this->tokenEndpoint(), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $channel->refresh_token,
            ]);

        if ($response->failed()) {
            $channel->update(['status' => 'expired']);

            return;
        }

        $data = $response->json();

        $channel->update([
            'access_token' => Arr::get($data, 'access_token', $channel->access_token),
            'refresh_token' => Arr::get($data, 'refresh_token', $channel->refresh_token),
            'token_expires_at' => isset($data['expires_in'])
                ? Carbon::now()->addSeconds((int) $data['expires_in'])
                : $channel->token_expires_at,
            'status' => 'connected',
        ]);
    }

    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        $accessToken = (string) Arr::get($tokenResponse, 'access_token');
        $boards = $this->listBoards($accessToken);

        if ($boards === []) {
            throw new PublishException(
                'No Pinterest boards were found for this account. Create at least one board on '
                    .'Pinterest, then reconnect.',
                retryable: false,
            );
        }

        $chosen = $boards[0];

        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($tokenResponse, 'user_id', 'pinterest'),
            name: 'Pinterest ('.$chosen['name'].')',
            accessToken: $accessToken,
            refreshToken: Arr::get($tokenResponse, 'refresh_token'),
            expiresAt: isset($tokenResponse['expires_in'])
                ? Carbon::now()->addSeconds((int) $tokenResponse['expires_in'])
                : null,
            scopes: $this->scopes(),
            meta: [
                'board_id' => $chosen['id'],
                // Every other board, for the channel-settings "switch
                // board" control — a pure local update, since re-listing
                // boards only ever needs this same account's own token.
                'available_accounts' => $boards,
            ],
        );
    }

    /** @return array<int, array{id: string, name: string}> */
    protected function listBoards(string $accessToken): array
    {
        $items = (array) Arr::get(
            Http::withToken($accessToken)->get('https://api.pinterest.com/v5/boards')->json(),
            'items',
            [],
        );

        return collect($items)
            ->map(fn ($board) => ['id' => (string) Arr::get($board, 'id'), 'name' => (string) Arr::get($board, 'name', 'Board')])
            ->values()
            ->all();
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $board = $channel->meta['board_id'] ?? null;
        $image = $payload->media[0]['url'] ?? null;

        if (! $board || ! $image) {
            throw new PublishException('Pinterest requires a board and an image.', retryable: false);
        }

        $response = Http::withToken($channel->access_token)->post('https://api.pinterest.com/v5/pins', [
            'board_id' => $board,
            'description' => $payload->content,
            'media_source' => ['source_type' => 'image_url', 'url' => $image],
        ]);

        if ($response->failed()) {
            throw new PublishException("Pinterest publish failed: {$response->body()}", retryable: $response->serverError());
        }

        return new PublishResult((string) Arr::get($response->json(), 'id', ''), $response->json() ?? []);
    }

    public function selectAccount(Channel $channel, string $accountId): void
    {
        $this->applyAccountSelection($channel, $accountId, 'board_id');
    }
}
