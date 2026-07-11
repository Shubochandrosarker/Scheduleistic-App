<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BrainGatewayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrainGatewayClientTest extends TestCase
{
    use RefreshDatabase;

    private function configureGateway(): void
    {
        config([
            'ai.brain_gateway.enabled' => true,
            'ai.brain_gateway.url' => 'https://gateway.test',
            'ai.brain_gateway.secret' => 'test-secret',
            'ai.brain_gateway.fake' => false,
        ]);
    }

    public function test_unconfigured_gateway_is_fake_and_returns_nothing(): void
    {
        config(['ai.brain_gateway.enabled' => false]);

        $client = app(BrainGatewayClient::class);
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $this->assertTrue($client->isFake());
        $this->assertSame([], $client->searchForTeam($team, 'topic'));
        $this->assertFalse($client->ingestForTeam($team, 'some text'));
    }

    public function test_search_signs_the_request_and_parses_chunks(): void
    {
        $this->configureGateway();

        Http::fake([
            '*' => Http::response([
                'chunks' => [['id' => 'c1', 'text' => 'Brand voice is confident.', 'source' => 'past-post', 'score' => 0.9, 'tier' => 'product']],
                'confidence' => 0.9,
            ]),
        ]);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $chunks = app(BrainGatewayClient::class)->searchForTeam($team, 'launch day post', 5);

        $this->assertSame(['Brand voice is confident.'], $chunks);

        Http::assertSent(function ($request) use ($team) {
            $principal = json_decode($this->b64UrlDecode($request->header('X-WPistic-Principal')[0]), true);

            return $request->url() === 'https://gateway.test/v1/brain/search'
                && $request->hasHeader('X-WPistic-Signature')
                && $principal['productId'] === 'scheduleistic'
                && $principal['tenantId'] === (string) $team->id;
        });
    }

    public function test_ingest_posts_a_signed_body_and_reports_ok(): void
    {
        $this->configureGateway();

        Http::fake([
            '*' => Http::response(['ok' => true, 'chunksCreated' => 1, 'queuedForShared' => false]),
        ]);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $ok = app(BrainGatewayClient::class)->ingestForTeam($team, 'Some brand content', 'post:1', 'rewrite');

        $this->assertTrue($ok);
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/v1/brain/ingest'
            && $request['text'] === 'Some brand content'
            && $request['category'] === 'rewrite');
    }

    public function test_failed_gateway_response_degrades_silently(): void
    {
        $this->configureGateway();

        Http::fake(['*' => Http::response(['error' => 'server_error'], 500)]);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $client = app(BrainGatewayClient::class);

        $this->assertSame([], $client->searchForTeam($team, 'topic'));
        $this->assertFalse($client->ingestForTeam($team, 'text'));
    }

    public function test_report_usage_posts_a_signed_body_and_reports_ok(): void
    {
        $this->configureGateway();

        Http::fake(['*' => Http::response(['ok' => true])]);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $ok = app(BrainGatewayClient::class)->reportUsage(
            $team, 'openrouter.ai', 'openai/gpt-4o-mini', 120, 45, refused: false, agent: 'caption:linkedin',
        );

        $this->assertTrue($ok);
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/v1/brain/usage'
            && $request['provider'] === 'openrouter.ai'
            && $request['model'] === 'openai/gpt-4o-mini'
            && $request['inputTokens'] === 120
            && $request['outputTokens'] === 45
            && $request['agent'] === 'caption:linkedin');
    }

    public function test_report_usage_is_a_no_op_when_unconfigured(): void
    {
        config(['ai.brain_gateway.enabled' => false]);
        Http::fake();

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $ok = app(BrainGatewayClient::class)->reportUsage($team, 'openrouter.ai', 'gpt-4o-mini', 10, 10);

        $this->assertFalse($ok);
        Http::assertNothingSent();
    }

    public function test_tier_maps_to_the_gateways_vocabulary(): void
    {
        $this->configureGateway();

        Http::fake(['*' => Http::response(['chunks' => [], 'confidence' => 0])]);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $team->update(['plan' => 'scale']);

        app(BrainGatewayClient::class)->searchForTeam($team, 'topic');

        Http::assertSent(function ($request) {
            $principal = json_decode($this->b64UrlDecode($request->header('X-WPistic-Principal')[0]), true);

            return $principal['tier'] === 'agency'; // 'scale' has no gateway-side equivalent tier
        });
    }

    private function b64UrlDecode(string $s): string
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }

        return base64_decode($s);
    }
}
