<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_mode_returns_deterministic_captions(): void
    {
        config(['ai.fake' => true]);

        $captions = app(AiAssistant::class)->captions('WordPress speed', ['linkedin', 'instagram']);

        $this->assertArrayHasKey('linkedin', $captions);
        $this->assertArrayHasKey('instagram', $captions);
        $this->assertStringContainsString('WordPress speed', $captions['linkedin']);
    }

    public function test_live_mode_calls_the_llm_and_parses_the_response(): void
    {
        config(['ai.fake' => false, 'ai.key' => 'test-key']);

        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => 'Generated post copy.']]]]),
        ]);

        $caption = app(AiAssistant::class)->caption('a topic', 'linkedin');

        $this->assertSame('Generated post copy.', $caption);
        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_generate_endpoint_returns_captions(): void
    {
        config(['ai.fake' => true]);
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->postJson(route('ai.generate'), ['topic' => 'launch day', 'providers' => ['linkedin']])
            ->assertOk()
            ->assertJsonStructure(['captions' => ['linkedin']]);
    }
}
