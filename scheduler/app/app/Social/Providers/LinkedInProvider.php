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

/**
 * LinkedIn personal profile publishing via the UGC Posts API.
 */
class LinkedInProvider extends AbstractOAuthProvider
{
    public function key(): string
    {
        return 'linkedin';
    }

    public function label(): string
    {
        return 'LinkedIn (Personal)';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    protected function scopes(): array
    {
        return ['openid', 'profile', 'w_member_social'];
    }

    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        $accessToken = Arr::get($tokenResponse, 'access_token');

        // Fetch the OpenID Connect userinfo to identify the member.
        $info = Http::withToken($accessToken)
            ->get('https://api.linkedin.com/v2/userinfo')
            ->json();

        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($info, 'sub'),
            name: (string) Arr::get($info, 'name', 'LinkedIn Member'),
            accessToken: $accessToken,
            refreshToken: Arr::get($tokenResponse, 'refresh_token'),
            expiresAt: isset($tokenResponse['expires_in'])
                ? Carbon::now()->addSeconds((int) $tokenResponse['expires_in'])
                : null,
            avatar: Arr::get($info, 'picture'),
            scopes: $this->scopes(),
        );
    }

    /** The author URN this driver posts as. Overridden by the company driver. */
    protected function authorUrn(Channel $channel): string
    {
        return 'urn:li:person:'.$channel->provider_account_id;
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $response = Http::withToken($channel->access_token)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->post('https://api.linkedin.com/v2/ugcPosts', [
                'author'          => $this->authorUrn($channel),
                'lifecycleState'  => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary'    => ['text' => $payload->content],
                        'shareMediaCategory' => 'NONE',
                    ],
                ],
                'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
            ]);

        if ($response->failed()) {
            throw new PublishException(
                "LinkedIn publish failed: {$response->status()} {$response->body()}",
                retryable: $response->status() === 429 || $response->serverError(),
                retryAfter: (int) $response->header('Retry-After') ?: null,
            );
        }

        return new PublishResult(
            providerPostId: (string) ($response->header('x-restli-id') ?: Arr::get($response->json(), 'id', '')),
            raw: $response->json() ?? [],
        );
    }
}
