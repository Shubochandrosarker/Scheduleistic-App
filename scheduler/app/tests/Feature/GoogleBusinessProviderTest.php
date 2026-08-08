<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use App\Social\Data\PublishPayload;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\GoogleBusinessProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Before this, nothing ever resolved a Google Business location — every
 * publish attempt on a freshly-connected channel threw "no location
 * configured". mapAccount() now lists every location across every account
 * the user manages and auto-selects the first, closing that gap.
 */
class GoogleBusinessProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function persistedChannel(array $attributes = []): Channel
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Brand']);

        return $workspace->channels()->create(array_merge([
            'provider' => 'google_business',
            'name' => 'GBP',
            'access_token' => 'tok',
        ], $attributes));
    }

    protected function fakeAccountsAndLocations(array $accounts): void
    {
        Http::fake([
            // The standard authorization_code exchange every OAuth2 driver
            // does first (AbstractOAuthProvider::exchangeCode()).
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'user-token']),
            'mybusiness.googleapis.com/v4/accounts' => Http::response([
                'accounts' => collect($accounts)->keys()->map(fn ($i) => ['name' => "accounts/{$i}"])->values()->all(),
            ]),
            'mybusiness.googleapis.com/v4/accounts/*/locations' => function ($request) use ($accounts) {
                preg_match('#/accounts/(\d+)/locations#', $request->url(), $matches);
                $index = (int) ($matches[1] ?? 0);

                return Http::response(['locations' => $accounts[$index] ?? []]);
            },
        ]);
    }

    public function test_the_first_location_across_every_account_is_selected(): void
    {
        $this->fakeAccountsAndLocations([
            0 => [['name' => 'accounts/0/locations/1', 'locationName' => 'Downtown Store']],
        ]);

        $account = (new GoogleBusinessProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        $this->assertSame('Downtown Store', $account->name);
        $this->assertSame('accounts/0/locations/1', $account->meta['location_name']);
        $this->assertCount(1, $account->meta['available_accounts']);
    }

    public function test_every_location_across_multiple_accounts_is_recorded(): void
    {
        $this->fakeAccountsAndLocations([
            0 => [['name' => 'accounts/0/locations/1', 'locationName' => 'Downtown Store']],
            1 => [['name' => 'accounts/1/locations/9', 'locationName' => 'Uptown Store']],
        ]);

        $account = (new GoogleBusinessProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        $ids = collect($account->meta['available_accounts'])->pluck('id');
        $this->assertTrue($ids->contains('accounts/0/locations/1'));
        $this->assertTrue($ids->contains('accounts/1/locations/9'));
    }

    public function test_connecting_an_account_with_no_locations_fails_clearly(): void
    {
        $this->fakeAccountsAndLocations([0 => []]);

        $this->expectException(PublishException::class);
        $this->expectExceptionMessageMatches('/No Google Business Profile locations/');

        (new GoogleBusinessProvider)->exchangeCode('auth-code', 'https://app.test/callback');
    }

    public function test_publish_requires_a_configured_location(): void
    {
        $channel = new Channel(['provider' => 'google_business', 'access_token' => 'tok', 'meta' => []]);

        $this->expectException(PublishException::class);

        (new GoogleBusinessProvider)->publish($channel, new PublishPayload('Hello'));
    }

    public function test_publish_attaches_a_photo_when_media_is_present(): void
    {
        Http::fake(['mybusiness.googleapis.com/*' => Http::response(['name' => 'accounts/0/locations/1/localPosts/1'])]);

        $channel = new Channel([
            'provider' => 'google_business', 'access_token' => 'tok',
            'meta' => ['location_name' => 'accounts/0/locations/1'],
        ]);

        (new GoogleBusinessProvider)->publish(
            $channel,
            new PublishPayload('New arrivals', [['url' => 'https://cdn.test/photo.jpg']]),
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'localPosts')
            && $request['media'][0]['sourceUrl'] === 'https://cdn.test/photo.jpg'
            && $request['media'][0]['mediaFormat'] === 'PHOTO');
    }

    public function test_switching_to_a_different_available_location_is_a_local_update(): void
    {
        Http::fake(); // no HTTP call should be needed to switch

        $channel = $this->persistedChannel([
            'meta' => [
                'location_name' => 'accounts/0/locations/1',
                'available_accounts' => [
                    ['id' => 'accounts/0/locations/1', 'name' => 'Downtown Store'],
                    ['id' => 'accounts/0/locations/2', 'name' => 'Uptown Store'],
                ],
            ],
        ]);

        (new GoogleBusinessProvider)->selectAccount($channel, 'accounts/0/locations/2');

        $channel->refresh();
        $this->assertSame('accounts/0/locations/2', $channel->meta['location_name']);
        $this->assertSame('Uptown Store', $channel->name);
        Http::assertNothingSent();
    }

    public function test_switching_to_an_id_that_was_never_offered_is_ignored(): void
    {
        $channel = $this->persistedChannel([
            'meta' => [
                'location_name' => 'accounts/0/locations/1',
                'available_accounts' => [['id' => 'accounts/0/locations/1', 'name' => 'Downtown Store']],
            ],
        ]);

        (new GoogleBusinessProvider)->selectAccount($channel, 'not-a-real-location');

        $this->assertSame('accounts/0/locations/1', $channel->fresh()->meta['location_name']);
    }
}
