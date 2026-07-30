<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Reads config/provider_capabilities.php — the single source of truth for what
 * each network integration supports.
 *
 * Cached because it is read on every composer render and inside every publish
 * job. `config()` is already an array lookup, but the merged/normalised shape
 * below is not, and this is hot enough to be worth the cache.
 */
class ProviderCapabilityService
{
    private const CACHE_KEY = 'provider_capabilities.v2';

    private const CACHE_TTL = 3600;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $memo = null;

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        return $this->memo = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            static fn () => config('provider_capabilities', [])
        );
    }

    /**
     * Capabilities for one provider key. Unknown providers get the documented
     * defaults rather than an exception, so a driver added ahead of its config
     * block still publishes with conservative rules.
     *
     * @return array<string, mixed>
     */
    public function for(string $provider): array
    {
        $all = $this->all();

        return $all[$provider] ?? $all['default'] ?? [];
    }

    public function supports(string $provider, string $option): bool
    {
        return (bool) ($this->for($provider)['supports'][$option] ?? false);
    }

    public function characterLimit(string $provider): int
    {
        return (int) ($this->for($provider)['character_limit'] ?? 5000);
    }

    public function requiresMedia(string $provider): bool
    {
        return (bool) ($this->for($provider)['requires_media'] ?? false);
    }

    /**
     * The explanation shown when an option is unavailable. Falls back to a
     * generic sentence so the UI never renders an unexplained disabled control.
     */
    public function note(string $provider, string $key): string
    {
        $note = $this->for($provider)['notes'][$key] ?? null;

        if (is_string($note) && $note !== '') {
            return $note;
        }

        return 'This network does not support that option.';
    }

    /** @return array<string, mixed> */
    public function media(string $provider): array
    {
        return $this->for($provider)['media'] ?? [];
    }

    /** Drop the cache — called after a deploy that changes the definitions. */
    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }
}
