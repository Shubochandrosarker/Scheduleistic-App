<?php

namespace App\Social\Providers;

use App\Models\Channel;
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
