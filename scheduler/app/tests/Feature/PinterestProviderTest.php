<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\PinterestProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pinterest's v5 API documents HTTP Basic client authentication for the
 * token endpoint (flagged in the platform-activation audit as needing
 * verification, not a certainty) — and, before this, nothing ever resolved
 * a board, so every publish attempt on a freshly-connected channel threw
 * "requires a board".
 */
class PinterestProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.pinterest.client_id' => 'app-id', 'services.pinterest.client_secret' => 'app-secret']);
    }

    protected function persistedChannel(array $attributes = []): Channel
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Brand']);

        return $workspace->channels()->create(array_merge([
            'provider' => 'pinterest',
            'name' => 'Pinterest',
            'access_token' => 'tok',
        ], $attributes));
    }

    protected function fakeConnect(array $boards): void
    {
        Http::fake([
            'api.pinterest.com/v5/oauth/token' => Http::response([
                'access_token' => 'user-token', 'refresh_token' => 'refresh', 'expires_in' => 2_592_000, 'user_id' => 'user-1',
            ]),
            'api.pinterest.com/v5/boards' => Http::response(['items' => $boards]),
        ]);
    }

    public function test_the_token_exchange_uses_http_basic_auth_not_body_params(): void
    {
        $this->fakeConnect([['id' => 'board-1', 'name' => 'Recipes']]);

        (new PinterestProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        Http::assertSent(function ($request) {
            $header = $request->header('Authorization')[0] ?? '';

            return str_starts_with($header, 'Basic ')
                && base64_decode(substr($header, 6)) === 'app-id:app-secret'
                && ! isset($request['client_id']);
        });
    }

    public function test_the_first_board_is_selected_and_tracks_its_own_expiry(): void
    {
        $this->fakeConnect([
            ['id' => 'board-1', 'name' => 'Recipes'],
            ['id' => 'board-2', 'name' => 'Travel'],
        ]);

        $account = (new PinterestProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        $this->assertSame('board-1', $account->meta['board_id']);
        $this->assertSame('Pinterest (Recipes)', $account->name);
        $this->assertNotNull($account->expiresAt);
        $this->assertCount(2, $account->meta['available_accounts']);
    }

    public function test_connecting_an_account_with_no_boards_fails_clearly(): void
    {
        $this->fakeConnect([]);

        $this->expectException(PublishException::class);
        $this->expectExceptionMessageMatches('/No Pinterest boards/');

        (new PinterestProvider)->exchangeCode('auth-code', 'https://app.test/callback');
    }

    public function test_refresh_uses_http_basic_auth_and_updates_the_channel(): void
    {
        Http::fake([
            'api.pinterest.com/v5/oauth/token' => Http::response(['access_token' => 'new-token', 'expires_in' => 2_592_000]),
        ]);

        $channel = $this->persistedChannel(['refresh_token' => 'old-refresh']);

        (new PinterestProvider)->refreshToken($channel);

        Http::assertSent(fn ($request) => str_starts_with($request->header('Authorization')[0] ?? '', 'Basic '));
        $this->assertSame('new-token', $channel->fresh()->access_token);
    }

    public function test_switching_to_a_different_board_is_a_local_update(): void
    {
        Http::fake();

        $channel = $this->persistedChannel([
            'meta' => [
                'board_id' => 'board-1',
                'available_accounts' => [
                    ['id' => 'board-1', 'name' => 'Recipes'],
                    ['id' => 'board-2', 'name' => 'Travel'],
                ],
            ],
        ]);

        (new PinterestProvider)->selectAccount($channel, 'board-2');

        $channel->refresh();
        $this->assertSame('board-2', $channel->meta['board_id']);
        $this->assertSame('Travel', $channel->name);
        Http::assertNothingSent();
    }
}
