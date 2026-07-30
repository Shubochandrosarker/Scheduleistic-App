<?php

namespace Tests\Unit;

use App\Services\MediaValidator;
use Tests\TestCase;

/**
 * MediaValidator is what stands between a user and a provider rejection, and
 * it runs again inside the publish job, so its rules are pinned here rather
 * than only exercised end-to-end.
 */
class MediaValidatorTest extends TestCase
{
    protected function validator(): MediaValidator
    {
        return app(MediaValidator::class);
    }

    protected function image(array $overrides = []): array
    {
        return array_merge([
            'kind'   => 'image',
            'mime'   => 'image/jpeg',
            'size'   => 500_000,
            'width'  => 1080,
            'height' => 1080,
            'name'   => 'photo.jpg',
        ], $overrides);
    }

    protected function video(array $overrides = []): array
    {
        return array_merge([
            'kind'     => 'video',
            'mime'     => 'video/mp4',
            'size'     => 5_000_000,
            'duration' => 30,
            'name'     => 'clip.mp4',
        ], $overrides);
    }

    protected function codes(array $issues): array
    {
        return array_column($issues, 'code');
    }

    public function test_a_valid_image_post_passes(): void
    {
        $this->assertTrue($this->validator()->passes('facebook', [$this->image()], 'Hello world'));
    }

    public function test_instagram_rejects_a_text_only_post(): void
    {
        $issues = $this->validator()->errors('instagram', [], 'Just words');

        $this->assertContains('media_required', $this->codes($issues));
    }

    public function test_tiktok_rejects_images(): void
    {
        $issues = $this->validator()->errors('tiktok', [$this->image()]);

        $this->assertContains('too_many_images', $this->codes($issues));
    }

    public function test_mixed_media_is_rejected_where_the_network_forbids_it(): void
    {
        $issues = $this->validator()->errors('linkedin', [$this->image(), $this->video()]);

        $this->assertContains('mixed_media', $this->codes($issues));
    }

    public function test_instagram_allows_mixed_media_carousels(): void
    {
        $issues = $this->validator()->errors('instagram', [$this->image(), $this->video(['duration' => 30])]);

        $this->assertNotContains('mixed_media', $this->codes($issues));
    }

    public function test_a_caption_over_the_network_limit_is_an_error(): void
    {
        $issues = $this->validator()->errors('bluesky', [], str_repeat('a', 400));

        $this->assertContains('caption_too_long', $this->codes($issues));
    }

    public function test_an_unsupported_mime_type_is_rejected(): void
    {
        $issues = $this->validator()->errors('instagram', [$this->image(['mime' => 'image/tiff'])]);

        $this->assertContains('mime_unsupported', $this->codes($issues));
    }

    public function test_an_oversized_file_is_rejected_with_the_network_limit_named(): void
    {
        // Bluesky documents a 1 MB ceiling.
        $issues = $this->validator()->errors('bluesky', [$this->image(['size' => 4 * 1024 * 1024])]);

        $this->assertContains('file_too_large', $this->codes($issues));
        $this->assertStringContainsString('1 MB', $issues[0]['message']);
    }

    public function test_an_undersized_image_is_rejected(): void
    {
        $issues = $this->validator()->errors('instagram', [$this->image(['width' => 100, 'height' => 100])]);

        $this->assertContains('image_too_small', $this->codes($issues));
    }

    public function test_a_video_over_the_duration_limit_is_rejected(): void
    {
        $issues = $this->validator()->errors('instagram', [$this->video(['duration' => 600])]);

        $this->assertContains('video_too_long', $this->codes($issues));
    }

    public function test_an_out_of_range_aspect_ratio_warns_rather_than_blocks(): void
    {
        // 3:1 is outside Instagram's 0.8–1.91 range but the network crops
        // rather than refusing, so this must not stop a publish.
        $all = $this->validator()->validate('instagram', [$this->image(['width' => 1800, 'height' => 600])]);
        $warnings = array_filter($all, fn ($i) => $i['level'] === 'warning');

        $this->assertNotEmpty($warnings);
        $this->assertNotContains('aspect_ratio', $this->codes($this->validator()->errors('instagram', [$this->image(['width' => 1800, 'height' => 600])])));
    }

    public function test_a_warning_carries_an_actionable_suggestion(): void
    {
        $all = $this->validator()->validate('instagram', [$this->image(['width' => 1800, 'height' => 600])]);
        $aspect = collect($all)->firstWhere('code', 'aspect_ratio');

        $this->assertNotNull($aspect);
        $this->assertNotNull($aspect['suggestion']);
        $this->assertStringContainsString('Crop', $aspect['suggestion']);
    }

    public function test_an_unknown_provider_falls_back_to_conservative_defaults(): void
    {
        // No config block: must not throw, and must still enforce something.
        $issues = $this->validator()->errors('a_network_we_have_not_configured', [
            $this->image(), $this->image(), $this->image(), $this->image(), $this->image(),
        ]);

        $this->assertContains('too_many_images', $this->codes($issues));
    }

    public function test_google_business_explains_why_hashtags_are_unavailable(): void
    {
        $capabilities = app(\App\Services\ProviderCapabilityService::class);

        $this->assertFalse($capabilities->supports('google_business', 'hashtags'));
        $this->assertStringContainsString('hashtags', $capabilities->note('google_business', 'hashtags'));
    }
}
