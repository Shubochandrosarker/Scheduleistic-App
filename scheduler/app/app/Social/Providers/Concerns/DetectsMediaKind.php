<?php

namespace App\Social\Providers\Concerns;

/**
 * Classifies a `PublishPayload` media item as an image or a video.
 *
 * Mirrors `PublishPostJob::describeMedia()`'s own fallback exactly: prefer
 * an explicit `kind`, otherwise infer it from the URL's extension. Kept as
 * a single shared implementation so "what counts as a video" never drifts
 * between the engine's pre-publish validation and a driver's own publish().
 */
trait DetectsMediaKind
{
    /** @param  array<string, mixed>  $item */
    protected function mediaKind(array $item): string
    {
        if (! empty($item['kind'])) {
            return $item['kind'] === 'video' ? 'video' : 'image';
        }

        $url = (string) ($item['url'] ?? '');

        return preg_match('/\.(mp4|mov|webm)(\?|$)/i', $url) ? 'video' : 'image';
    }
}
