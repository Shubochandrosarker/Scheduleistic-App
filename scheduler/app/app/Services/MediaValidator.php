<?php

namespace App\Services;

/**
 * Provider-aware media validation.
 *
 * Runs twice on purpose:
 *
 *  1. In the composer, so the author sees problems before scheduling.
 *  2. Inside PublishPostJob, immediately before the driver call — because a
 *     post can be edited (or a provider's rules can change) between scheduling
 *     and publishing, and a payload the network will reject should fail fast
 *     with a readable reason instead of burning retries.
 *
 * Errors block publishing. Warnings do not — they carry a suggestion the
 * author can act on (crop, compress, trim).
 */
class MediaValidator
{
    public function __construct(private readonly ProviderCapabilityService $capabilities) {}

    /**
     * @param  array<int, array{
     *     kind?: string, mime?: string, size?: int, width?: int|null,
     *     height?: int|null, duration?: float|null, name?: string
     * }>  $assets
     * @return array<int, array{level:string, code:string, message:string, suggestion:?string, asset:?string}>
     */
    public function validate(string $provider, array $assets, ?string $content = null): array
    {
        $rules  = $this->capabilities->media($provider);
        $issues = [];

        $images = array_values(array_filter($assets, fn ($a) => $this->kind($a) === 'image'));
        $videos = array_values(array_filter($assets, fn ($a) => $this->kind($a) === 'video'));

        // --- Counts -----------------------------------------------------

        $maxImages = $rules['max_images'] ?? 0;
        $maxVideos = $rules['max_videos'] ?? 0;

        if (count($images) > $maxImages) {
            $issues[] = $this->issue(
                'error',
                'too_many_images',
                $maxImages === 0
                    ? $this->capabilities->note($provider, 'max_images')
                    : sprintf('%s accepts at most %d image(s); %d attached.', $this->label($provider), $maxImages, count($images)),
                $maxImages === 0 ? null : 'Remove the extra images or split this into multiple posts.'
            );
        }

        if (count($videos) > $maxVideos) {
            $issues[] = $this->issue(
                'error',
                'too_many_videos',
                $maxVideos === 0
                    ? $this->capabilities->note($provider, 'max_videos')
                    : sprintf('%s accepts at most %d video(s); %d attached.', $this->label($provider), $maxVideos, count($videos)),
                null
            );
        }

        if ($images && $videos && ! ($rules['mixed_media'] ?? false)) {
            $issues[] = $this->issue(
                'error',
                'mixed_media',
                sprintf('%s cannot publish images and video in the same post.', $this->label($provider)),
                'Split this into one image post and one video post.'
            );
        }

        if ($this->capabilities->requiresMedia($provider) && ! $assets) {
            $issues[] = $this->issue(
                'error',
                'media_required',
                $this->capabilities->note($provider, 'requires_media'),
                'Attach an image or video.'
            );
        }

        // --- Caption length ---------------------------------------------

        if ($content !== null) {
            $limit  = $this->capabilities->characterLimit($provider);
            $length = mb_strlen($content);

            if ($length > $limit) {
                $issues[] = $this->issue(
                    'error',
                    'caption_too_long',
                    sprintf('Caption is %d characters; %s allows %d.', $length, $this->label($provider), $limit),
                    sprintf('Trim %d character(s).', $length - $limit)
                );
            }
        }

        // --- Per-asset ---------------------------------------------------

        foreach ($assets as $asset) {
            $kind = $this->kind($asset);
            $spec = $rules[$kind] ?? null;

            if (! is_array($spec)) {
                continue;
            }

            $issues = array_merge($issues, $this->validateAsset($provider, $asset, $kind, $spec));
        }

        return $issues;
    }

    /** True when nothing blocks publishing (warnings are allowed through). */
    public function passes(string $provider, array $assets, ?string $content = null): bool
    {
        return $this->errors($provider, $assets, $content) === [];
    }

    /** @return array<int, array<string, mixed>> only the blocking issues */
    public function errors(string $provider, array $assets, ?string $content = null): array
    {
        return array_values(array_filter(
            $this->validate($provider, $assets, $content),
            static fn (array $issue) => $issue['level'] === 'error'
        ));
    }

    /**
     * @param  array<string, mixed>  $asset
     * @param  array<string, mixed>  $spec
     * @return array<int, array<string, mixed>>
     */
    protected function validateAsset(string $provider, array $asset, string $kind, array $spec): array
    {
        $issues = [];
        $name   = $asset['name'] ?? null;

        $mime = $asset['mime'] ?? null;
        if ($mime && ! empty($spec['mime']) && ! in_array($mime, $spec['mime'], true)) {
            $issues[] = $this->issue(
                'error',
                'mime_unsupported',
                sprintf('%s does not accept %s files.', $this->label($provider), $mime),
                'Convert to '.implode(' or ', array_map(fn ($m) => strtoupper(explode('/', $m)[1] ?? $m), $spec['mime'])).'.',
                $name
            );
        }

        $size = (int) ($asset['size'] ?? 0);
        $maxMb = $spec['max_mb'] ?? null;
        if ($size > 0 && $maxMb !== null && $size > $maxMb * 1024 * 1024) {
            $issues[] = $this->issue(
                'error',
                'file_too_large',
                sprintf('File is %.1f MB; %s allows %d MB.', $size / 1048576, $this->label($provider), $maxMb),
                'Compress the file, or let Scheduleistic generate an optimised variant.',
                $name
            );
        }

        if ($kind === 'image') {
            $width  = (int) ($asset['width'] ?? 0);
            $height = (int) ($asset['height'] ?? 0);

            if ($width > 0 && $height > 0) {
                if ($width < ($spec['min_width'] ?? 0) || $height < ($spec['min_height'] ?? 0)) {
                    $issues[] = $this->issue(
                        'error',
                        'image_too_small',
                        sprintf(
                            'Image is %d×%d; %s needs at least %d×%d.',
                            $width, $height, $this->label($provider),
                            $spec['min_width'] ?? 0, $spec['min_height'] ?? 0
                        ),
                        'Upload a larger source image — upscaling a small one will look soft.',
                        $name
                    );
                }

                $ratio = $width / max($height, 1);
                $min   = $spec['aspect_min'] ?? null;
                $max   = $spec['aspect_max'] ?? null;

                if (($min !== null && $ratio < $min - 0.01) || ($max !== null && $ratio > $max + 0.01)) {
                    $issues[] = $this->issue(
                        'warning',
                        'aspect_ratio',
                        sprintf(
                            'Aspect ratio %.2f is outside %s\'s %.2f–%.2f range; the network will crop it.',
                            $ratio, $this->label($provider), $min ?? 0, $max ?? 99
                        ),
                        $this->cropSuggestion($width, $height, $min, $max),
                        $name
                    );
                }
            }
        }

        if ($kind === 'video') {
            $duration = $asset['duration'] ?? null;

            if ($duration !== null) {
                if (isset($spec['max_duration']) && $duration > $spec['max_duration']) {
                    $issues[] = $this->issue(
                        'error',
                        'video_too_long',
                        sprintf('Video is %ds; %s allows %ds.', (int) $duration, $this->label($provider), $spec['max_duration']),
                        'Trim the video before scheduling.',
                        $name
                    );
                }

                if (isset($spec['min_duration']) && $duration < $spec['min_duration']) {
                    $issues[] = $this->issue(
                        'error',
                        'video_too_short',
                        sprintf('Video is %ds; %s requires at least %ds.', (int) $duration, $this->label($provider), $spec['min_duration']),
                        null,
                        $name
                    );
                }
            }
        }

        return $issues;
    }

    /** A concrete target size the author can crop to, rather than "fix the ratio". */
    protected function cropSuggestion(int $width, int $height, ?float $min, ?float $max): string
    {
        $ratio  = $width / max($height, 1);
        $target = $ratio < ($min ?? 0) ? $min : $max;

        if (! $target) {
            return 'Crop the image to a supported aspect ratio.';
        }

        // Keep the longest edge and derive the other from the target ratio.
        return $ratio < ($min ?? 0)
            ? sprintf('Crop to %d×%d to reach %.2f:1.', $width, (int) round($width / $target), $target)
            : sprintf('Crop to %d×%d to reach %.2f:1.', (int) round($height * $target), $height, $target);
    }

    protected function kind(array $asset): string
    {
        if (! empty($asset['kind'])) {
            return $asset['kind'] === 'video' ? 'video' : 'image';
        }

        return str_starts_with((string) ($asset['mime'] ?? ''), 'video/') ? 'video' : 'image';
    }

    protected function label(string $provider): string
    {
        return (string) (config("scheduleistic.providers.{$provider}.label")
            ?? ucfirst(str_replace('_', ' ', $provider)));
    }

    /** @return array{level:string, code:string, message:string, suggestion:?string, asset:?string} */
    protected function issue(string $level, string $code, string $message, ?string $suggestion = null, ?string $asset = null): array
    {
        return [
            'level'      => $level,
            'code'       => $code,
            'message'    => $message,
            'suggestion' => $suggestion,
            'asset'      => $asset,
        ];
    }
}
