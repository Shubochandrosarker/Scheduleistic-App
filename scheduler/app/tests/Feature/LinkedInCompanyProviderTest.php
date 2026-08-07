<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use App\Social\Data\PublishPayload;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\LinkedInCompanyProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Before this, connecting a LinkedIn Company Page never resolved which
 * organization the member administers at all — it inherited the personal
 * driver's mapAccount() (a person's /v2/userinfo), so posting fell back to
 * treating the connecting *person's* id as the organization id, which it
 * essentially never is.
 */
class LinkedInCompanyProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.linkedin_company.client_id' => 'app-id', 'services.linkedin_company.client_secret' => 'app-secret']);
    }

    protected function persistedChannel(array $attributes = []): Channel
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Brand']);

        return $workspace->channels()->create(array_merge([
            'provider' => 'linkedin_company',
            'name' => 'Company',
            'access_token' => 'tok',
        ], $attributes));
    }

    protected function fakeConnect(array $elements): void
    {
        Http::fake([
            'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'member-token']),
            'api.linkedin.com/v2/organizationAcls*' => Http::response(['elements' => $elements]),
        ]);
    }

    public function test_the_first_administered_organization_is_selected(): void
    {
        $this->fakeConnect([
            [
                'organization' => 'urn:li:organization:111',
                'organization~' => ['id' => 111, 'localizedName' => 'Acme Corp'],
            ],
            [
                'organization' => 'urn:li:organization:222',
                'organization~' => ['id' => 222, 'localizedName' => 'Beta Inc'],
            ],
        ]);

        $account = (new LinkedInCompanyProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        $this->assertSame('111', $account->providerAccountId);
        $this->assertSame('Acme Corp', $account->name);
        $this->assertCount(2, $account->meta['available_accounts']);
        $this->assertSame('222', $account->meta['available_accounts'][1]['id']);
    }

    public function test_a_response_missing_the_resolved_projection_falls_back_to_parsing_the_urn(): void
    {
        // Same shape LinkedIn returns when the ~ projection can't be resolved
        // for some reason — only the bare URN reference is present.
        $this->fakeConnect([
            ['organization' => 'urn:li:organization:333'],
        ]);

        $account = (new LinkedInCompanyProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        // Never double-prefixed (e.g. "urn:li:organization:urn:li:organization:333").
        $this->assertSame('333', $account->providerAccountId);
    }

    public function test_connecting_a_member_who_administers_no_organization_fails_clearly(): void
    {
        $this->fakeConnect([]);

        $this->expectException(PublishException::class);
        $this->expectExceptionMessageMatches('/does not administer any Company Page/');

        (new LinkedInCompanyProvider)->exchangeCode('auth-code', 'https://app.test/callback');
    }

    public function test_publish_posts_as_the_organization_urn_not_the_person(): void
    {
        Http::fake([
            'api.linkedin.com/v2/ugcPosts' => Http::response([], 201, ['x-restli-id' => 'urn:li:share:1']),
        ]);

        $channel = new Channel([
            'provider' => 'linkedin_company',
            'provider_account_id' => 'person-id-should-not-be-used',
            'access_token' => 'tok',
            'meta' => ['organization_id' => '111'],
        ]);

        (new LinkedInCompanyProvider)->publish($channel, new PublishPayload('Company update'));

        Http::assertSent(fn ($request) => ($request['author'] ?? null) === 'urn:li:organization:111');
    }

    public function test_switching_to_a_different_administered_organization_is_a_local_update(): void
    {
        Http::fake();

        $channel = $this->persistedChannel([
            'meta' => [
                'organization_id' => '111',
                'available_accounts' => [
                    ['id' => '111', 'name' => 'Acme Corp'],
                    ['id' => '222', 'name' => 'Beta Inc'],
                ],
            ],
        ]);

        (new LinkedInCompanyProvider)->selectAccount($channel, '222');

        $channel->refresh();
        $this->assertSame('222', $channel->meta['organization_id']);
        $this->assertSame('Beta Inc', $channel->name);
        Http::assertNothingSent();
    }
}
