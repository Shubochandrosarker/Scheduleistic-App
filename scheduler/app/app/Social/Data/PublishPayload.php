<?php

namespace App\Social\Data;

/**
 * Network-agnostic description of what to publish. Built from a Post + its
 * per-provider PostVersion before being handed to a provider driver.
 */
class PublishPayload
{
    /**
     * @param  array<int, array{url:string, alt?:string}>  $media
     * @param  array<string, mixed>  $options  provider-specific options
     */
    public function __construct(
        public readonly string $content,
        public readonly array $media = [],
        public readonly array $options = [],
    ) {}
}
