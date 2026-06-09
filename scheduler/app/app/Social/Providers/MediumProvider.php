<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/** Medium article cross-posting via integration token. */
class MediumProvider extends AbstractTokenProvider
{
    public function key(): string
    {
        return 'medium';
    }

    public function label(): string
    {
        return 'Medium';
    }

    public function fields(): array
    {
        return [
            ['key' => 'integration_token', 'label' => 'Integration token', 'type' => 'password'],
            ['key' => 'author_id', 'label' => 'Author ID', 'type' => 'text'],
        ];
    }

    public function accountFromCredentials(array $input): ConnectedAccount
    {
        return new ConnectedAccount(
            providerAccountId: $input['author_id'] ?? ('medium-'.substr(sha1($input['integration_token'] ?? ''), 0, 8)),
            name: 'Medium',
            accessToken: $input['integration_token'] ?? '',
            scopes: [],
            meta: ['author_id' => $input['author_id'] ?? null],
        );
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $authorId = $channel->meta['author_id'] ?? $channel->provider_account_id;

        $response = Http::withToken($channel->access_token)
            ->post("https://api.medium.com/v1/users/{$authorId}/posts", [
                'title'         => mb_substr(strtok($payload->content, "\n"), 0, 100),
                'contentFormat' => 'markdown',
                'content'       => $payload->content,
                'publishStatus' => 'public',
            ]);

        if ($response->failed()) {
            throw new PublishException("Medium publish failed: {$response->body()}", retryable: $response->serverError());
        }

        return new PublishResult((string) Arr::get($response->json(), 'data.id', ''), $response->json() ?? []);
    }
}
