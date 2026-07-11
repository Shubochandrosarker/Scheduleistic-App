<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PostAiAgents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostAiAgentsTest extends TestCase
{
    use RefreshDatabase;

    private function postWithContent(string $content): Post
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $workspace = Workspace::create(['team_id' => $team->id, 'name' => 'Acme']);

        return Post::create([
            'workspace_id' => $workspace->id,
            'content' => $content,
            'status' => Post::STATUS_DRAFT,
        ]);
    }

    public function test_fake_mode_rewrite_normalizes_whitespace_and_writes_back(): void
    {
        config(['ai.fake' => true]);
        $post = $this->postWithContent("Hello   world\n\n\nlet's ship it");

        $result = app(PostAiAgents::class)->rewrite($post);

        $this->assertTrue($result['applied']);
        $this->assertSame("Hello world let's ship it", $post->fresh()->content);
    }

    public function test_fake_mode_rewrite_is_a_no_op_when_content_is_already_clean(): void
    {
        config(['ai.fake' => true]);
        $post = $this->postWithContent('Already clean copy');

        $result = app(PostAiAgents::class)->rewrite($post);

        $this->assertFalse($result['applied']);
        $this->assertSame('Already clean copy', $post->fresh()->content);
    }

    public function test_fake_mode_hashtags_derives_from_content(): void
    {
        config(['ai.fake' => true]);
        $post = $this->postWithContent('Announcing our biggest WordPress plugin launch yet');

        $result = app(PostAiAgents::class)->optimizeHashtags($post);

        $this->assertTrue($result['applied']);
        $this->assertNotEmpty($post->fresh()->hashtags);
        $this->assertStringStartsWith('#', $post->fresh()->hashtags[0]);
    }

    public function test_fake_mode_quality_check_flags_short_content(): void
    {
        config(['ai.fake' => true]);
        $post = $this->postWithContent('Hi');

        app(PostAiAgents::class)->qualityCheck($post);

        $report = $post->fresh()->ai_quality_report;
        $this->assertFalse($report['ready']);
        $this->assertNotEmpty($report['issues']);
    }

    public function test_fake_mode_quality_check_passes_substantial_content(): void
    {
        config(['ai.fake' => true]);
        $post = $this->postWithContent('This is a substantial, well-formed social media post about our launch.');

        app(PostAiAgents::class)->qualityCheck($post);

        $report = $post->fresh()->ai_quality_report;
        $this->assertTrue($report['ready']);
        $this->assertEmpty($report['issues']);
    }

    public function test_live_mode_rewrite_parses_json_and_writes_back(): void
    {
        config(['ai.fake' => false, 'ai.key' => 'test-key']);
        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => '{"content": "Cleaned up copy."}']]]]),
        ]);

        $post = $this->postWithContent('messy   copy');
        $result = app(PostAiAgents::class)->rewrite($post);

        $this->assertTrue($result['applied']);
        $this->assertSame('Cleaned up copy.', $post->fresh()->content);
    }

    public function test_live_mode_tolerates_a_markdown_json_fence(): void
    {
        config(['ai.fake' => false, 'ai.key' => 'test-key']);
        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => "```json\n{\"hashtags\": [\"#wordpress\", \"launch\"]}\n```"]]]]),
        ]);

        $post = $this->postWithContent('Launch day');
        $result = app(PostAiAgents::class)->optimizeHashtags($post);

        $this->assertTrue($result['applied']);
        $this->assertSame(['#wordpress', '#launch'], $post->fresh()->hashtags);
    }

    public function test_empty_model_reply_does_not_wipe_the_post(): void
    {
        config(['ai.fake' => false, 'ai.key' => 'test-key']);
        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => '{"content": ""}']]]]),
        ]);

        $post = $this->postWithContent('Keep me');
        $result = app(PostAiAgents::class)->rewrite($post);

        $this->assertFalse($result['applied']);
        $this->assertSame('Keep me', $post->fresh()->content);
    }
}
