<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DomainVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_succeeds_when_the_txt_token_is_present(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill([
            'custom_domain'       => 'app.agency.com',
            'custom_domain_token' => 'scheduleistic-verify-abc123',
        ])->save();

        // Inject a fake DNS resolver that returns the expected token.
        $service = new DomainVerificationService(
            fn (string $host) => $host === '_scheduleistic.app.agency.com'
                ? ['scheduleistic-verify-abc123']
                : [],
        );

        $this->assertTrue($service->verify($team));
        $this->assertTrue($team->fresh()->customDomainVerified());
    }

    public function test_verification_fails_without_the_token(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill([
            'custom_domain'       => 'app.agency.com',
            'custom_domain_token' => 'scheduleistic-verify-abc123',
        ])->save();

        $service = new DomainVerificationService(fn (string $host) => ['some-other-value']);

        $this->assertFalse($service->verify($team));
        $this->assertFalse($team->fresh()->customDomainVerified());
    }

    public function test_tls_check_approves_verified_domains_only(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->currentTeam->forceFill([
            'custom_domain'             => 'app.agency.com',
            'custom_domain_verified_at' => now(),
        ])->save();

        $this->get('/tls/check?domain=app.agency.com')->assertOk();
        $this->get('/tls/check?domain=evil.example.com')->assertForbidden();
        $this->get('/tls/check')->assertStatus(400);
    }

    public function test_tls_check_approves_the_platform_domain(): void
    {
        config(['app.url' => 'https://app.scheduleistic.test']);

        $this->get('/tls/check?domain=app.scheduleistic.test')->assertOk();
    }

    public function test_guest_on_a_custom_domain_gets_tenant_branding(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->currentTeam->forceFill([
            'branding'                  => ['name' => 'AcmeAgency Social'],
            'custom_domain'             => 'social.acme.com',
            'custom_domain_verified_at' => now(),
        ])->save();

        // A guest hitting the login page on the custom domain sees Acme branding.
        $response = $this->get('http://social.acme.com/login');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('branding.name', 'AcmeAgency Social'));
    }

    public function test_command_verifies_pending_domains(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->currentTeam->forceFill([
            'custom_domain'       => 'app.agency.com',
            'custom_domain_token' => 'tok-xyz',
        ])->save();

        // Bind a fake resolver into the container for the command run.
        $this->app->bind(DomainVerificationService::class, fn () => new DomainVerificationService(
            fn (string $host) => ['tok-xyz'],
        ));

        $this->artisan('domains:verify')->assertSuccessful();
        $this->assertTrue($user->currentTeam->fresh()->customDomainVerified());
    }
}
