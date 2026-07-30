<?php

/*
|--------------------------------------------------------------------------
| Network Integration Capabilities
|--------------------------------------------------------------------------
|
| What each network actually supports. The composer reads this to decide
| which options to show, the media validator reads it to accept or reject an
| attachment, and the publish job re-reads it immediately before sending so a
| post edited after scheduling cannot slip through with an invalid payload.
|
| Nothing in Vue may hard-code a provider rule — it asks for these definitions
| and renders whatever it is told. Adding a network means adding a block here
| and a driver class; no UI change is required.
|
| `notes` explains *why* an option is unavailable so the UI can say something
| better than showing a disabled control with no reason.
|
| Sizes are megabytes; durations are seconds. `null` means "no documented
| limit we enforce".
|
*/

$defaults = [
    'character_limit' => 5000,
    'requires_media'  => false,
    'supports'        => [
        'first_comment'     => false,
        'alt_text'          => false,
        'location'          => false,
        'mentions'          => true,
        'link_preview'      => true,
        'links_in_body'     => true,
        'threads'           => false,
        'custom_thumbnail'  => false,
        'video_title'       => false,
        'video_description' => false,
        'board'             => false,
        'privacy'           => false,
        'category'          => false,
        'hashtags'          => true,
    ],
    'media' => [
        'max_images'   => 4,
        'max_videos'   => 1,
        'mixed_media'  => false,
        'image' => [
            'mime'       => ['image/jpeg', 'image/png', 'image/webp'],
            'max_mb'     => 8,
            'min_width'  => 200,
            'min_height' => 200,
            'aspect_min' => 0.3,
            'aspect_max' => 4.0,
        ],
        'video' => [
            'mime'         => ['video/mp4', 'video/quicktime'],
            'max_mb'       => 200,
            'min_duration' => 1,
            'max_duration' => 600,
        ],
    ],
    'notes' => [],
];

$merge = static function (array $overrides) use ($defaults): array {
    $merged = $defaults;

    foreach ($overrides as $key => $value) {
        if ($key === 'supports' || $key === 'notes') {
            $merged[$key] = array_merge($defaults[$key], $value);
        } elseif ($key === 'media') {
            $merged['media'] = array_merge($defaults['media'], $value);
            foreach (['image', 'video'] as $kind) {
                if (isset($value[$kind])) {
                    $merged['media'][$kind] = array_merge($defaults['media'][$kind], $value[$kind]);
                }
            }
        } else {
            $merged[$key] = $value;
        }
    }

    return $merged;
};

return [

    'instagram' => $merge([
        'character_limit' => 2200,
        'requires_media'  => true,
        'supports' => [
            'first_comment' => true,
            'alt_text'      => true,
            'location'      => true,
            'link_preview'  => false,
            'links_in_body' => false,
        ],
        'media' => [
            'max_images'  => 10,
            'mixed_media' => true,
            'image'       => ['aspect_min' => 0.8, 'aspect_max' => 1.91, 'min_width' => 320],
            'video'       => ['max_duration' => 90, 'max_mb' => 100],
        ],
        'notes' => [
            'links_in_body' => 'Instagram does not make links in a caption clickable. Use a link-in-bio page instead.',
            'requires_media' => 'Instagram will not accept a text-only post.',
        ],
    ]),

    'facebook' => $merge([
        'character_limit' => 63206,
        'supports' => [
            'first_comment' => true,
            'alt_text'      => true,
            'location'      => true,
        ],
        'media' => [
            'max_images' => 10,
            'image'      => ['max_mb' => 10],
            'video'      => ['max_mb' => 1024, 'max_duration' => 14400],
        ],
    ]),

    'linkedin' => $merge([
        'character_limit' => 3000,
        'supports' => [
            'alt_text' => true,
        ],
        'media' => [
            'max_images' => 9,
            'image'      => ['max_mb' => 10],
            'video'      => ['max_mb' => 200, 'max_duration' => 600],
        ],
    ]),

    'linkedin_company' => $merge([
        'character_limit' => 3000,
        'supports' => [
            'alt_text' => true,
        ],
        'media' => [
            'max_images' => 9,
            'image'      => ['max_mb' => 10],
            'video'      => ['max_mb' => 200, 'max_duration' => 600],
        ],
    ]),

    'threads' => $merge([
        'character_limit' => 500,
        'supports' => [
            'threads'  => true,
            'alt_text' => true,
        ],
        'media' => [
            'max_images' => 20,
            'video'      => ['max_duration' => 300],
        ],
    ]),

    'bluesky' => $merge([
        'character_limit' => 300,
        'supports' => [
            'threads'  => true,
            'alt_text' => true,
        ],
        'media' => [
            'max_images' => 4,
            'max_videos' => 1,
            'image'      => ['max_mb' => 1],
        ],
        'notes' => [
            'image' => 'Bluesky compresses aggressively and rejects images over 1 MB.',
        ],
    ]),

    'mastodon' => $merge([
        'character_limit' => 500,
        'supports' => [
            'threads'  => true,
            'alt_text' => true,
        ],
        'media' => [
            'max_images'  => 4,
            'mixed_media' => false,
            'image'       => ['max_mb' => 16, 'mime' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif']],
            'video'       => ['max_mb' => 100],
        ],
    ]),

    'tiktok' => $merge([
        'character_limit' => 2200,
        'requires_media'  => true,
        'supports' => [
            'privacy'          => true,
            'custom_thumbnail' => true,
            'link_preview'     => false,
            'links_in_body'    => false,
        ],
        'media' => [
            'max_images' => 0,
            'max_videos' => 1,
            'video'      => ['max_mb' => 500, 'min_duration' => 3, 'max_duration' => 600],
        ],
        'notes' => [
            'max_images'    => 'TikTok publishing through the API is video-only.',
            'links_in_body' => 'TikTok captions do not render clickable links.',
        ],
    ]),

    'youtube' => $merge([
        'character_limit' => 5000,
        'requires_media'  => true,
        'supports' => [
            'video_title'       => true,
            'video_description' => true,
            'custom_thumbnail'  => true,
            'privacy'           => true,
            'category'          => true,
        ],
        'media' => [
            'max_images' => 0,
            'max_videos' => 1,
            'video'      => ['max_mb' => 2048, 'max_duration' => 43200],
        ],
        'notes' => [
            'video_title' => 'YouTube requires a title of 100 characters or fewer.',
        ],
    ]),

    'pinterest' => $merge([
        'character_limit' => 500,
        'requires_media'  => true,
        'supports' => [
            'board'    => true,
            'alt_text' => true,
        ],
        'media' => [
            'max_images' => 1,
            'max_videos' => 1,
            'image'      => ['max_mb' => 20, 'aspect_min' => 0.5, 'aspect_max' => 1.0],
            'video'      => ['max_duration' => 900],
        ],
        'notes' => [
            'board' => 'Every Pin must be saved to a board. Pick one before scheduling.',
            'image' => 'Pinterest favours vertical images — 2:3 (1000×1500) performs best.',
        ],
    ]),

    'google_business' => $merge([
        'character_limit' => 1500,
        'supports' => [
            'location'      => true,
            'mentions'      => false,
            'hashtags'      => false,
        ],
        'media' => [
            'max_images' => 1,
            'max_videos' => 0,
            'image'      => ['min_width' => 250, 'min_height' => 250, 'max_mb' => 5],
        ],
        'notes' => [
            'hashtags' => 'Google Business Profile posts do not index hashtags.',
            'mentions' => 'Google Business Profile has no @mention concept.',
        ],
    ]),

    'medium' => $merge([
        'character_limit' => 100000,
        'supports' => [
            'video_title' => true,
            'mentions'    => false,
        ],
        'media' => [
            'max_images' => 20,
            'max_videos' => 0,
        ],
    ]),

    'wordpress' => $merge([
        'character_limit' => 100000,
        'supports' => [
            'video_title' => true,
            'category'    => true,
            'mentions'    => false,
        ],
        'media' => [
            'max_images' => 20,
            'max_videos' => 0,
            'image'      => ['max_mb' => 32],
        ],
    ]),

    'fake' => $merge([
        'character_limit' => 5000,
        'supports' => [
            'first_comment' => true,
            'alt_text'      => true,
            'threads'       => true,
        ],
    ]),

    // Fallback for any driver without an explicit block.
    'default' => $defaults,

];
