<?php

namespace App\Social;

use App\Social\Contracts\SocialProvider;
use App\Social\Providers\FacebookProvider;
use App\Social\Providers\FakeProvider;
use App\Social\Providers\GoogleBusinessProvider;
use App\Social\Providers\InstagramProvider;
use App\Social\Providers\LinkedInCompanyProvider;
use App\Social\Providers\LinkedInProvider;
use InvalidArgumentException;

/**
 * Registry that resolves a provider key to its driver. New networks are
 * registered here (or via extend()) — the rest of the app only ever talks to
 * the SocialProvider contract.
 */
class ProviderManager
{
    /** @var array<string, class-string<SocialProvider>> */
    protected array $drivers = [
        'linkedin'         => LinkedInProvider::class,
        'linkedin_company' => LinkedInCompanyProvider::class,
        'facebook'         => FacebookProvider::class,
        'instagram'        => InstagramProvider::class,
        'google_business'  => GoogleBusinessProvider::class,
    ];

    /** @var array<string, SocialProvider> runtime overrides (used by tests). */
    protected array $resolved = [];

    public function __construct(private readonly bool $fake = false) {}

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->drivers);
    }

    public function driver(string $key): SocialProvider
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        if ($this->fake) {
            return $this->resolved[$key] = new FakeProvider($key);
        }

        if (! isset($this->drivers[$key])) {
            throw new InvalidArgumentException("Unknown social provider [{$key}].");
        }

        return $this->resolved[$key] = app($this->drivers[$key]);
    }

    /** Register or override a driver (e.g. swap in a fake during tests). */
    public function extend(string $key, SocialProvider $provider): void
    {
        $this->resolved[$key] = $provider;
    }
}
