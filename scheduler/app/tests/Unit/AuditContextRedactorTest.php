<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditContextRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditContextRedactorTest extends TestCase
{
    use RefreshDatabase;

    protected function redactor(): AuditContextRedactor
    {
        return new AuditContextRedactor;
    }

    public function test_sensitive_top_level_keys_are_redacted(): void
    {
        $result = $this->redactor()->redact([
            'password' => 'hunter2',
            'api_key' => 'sk_live_abc',
            'access_token' => 'abc123',
            'name' => 'Acme Inc',
        ]);

        $this->assertSame('[redacted]', $result['password']);
        $this->assertSame('[redacted]', $result['api_key']);
        $this->assertSame('[redacted]', $result['access_token']);
        $this->assertSame('Acme Inc', $result['name']);
    }

    public function test_sensitive_keys_are_redacted_regardless_of_nesting_depth(): void
    {
        $result = $this->redactor()->redact([
            'before' => ['webhook_secret' => 'whsec_old', 'url' => 'https://a.test'],
            'after' => ['webhook_secret' => 'whsec_new', 'url' => 'https://b.test'],
        ]);

        $this->assertSame('[redacted]', $result['before']['webhook_secret']);
        $this->assertSame('[redacted]', $result['after']['webhook_secret']);
        $this->assertSame('https://a.test', $result['before']['url']);
        $this->assertSame('https://b.test', $result['after']['url']);
    }

    public function test_key_matching_is_case_insensitive_and_matches_by_substring(): void
    {
        $result = $this->redactor()->redact([
            'Two_Factor_Recovery_Codes' => ['abc', 'def'],
            'STRIPE_SECRET' => 'sk_live',
            'stripePriceId' => 'price_123', // "price" is not sensitive, only "secret"/etc would be
        ]);

        $this->assertSame('[redacted]', $result['Two_Factor_Recovery_Codes']);
        $this->assertSame('[redacted]', $result['STRIPE_SECRET']);
        $this->assertSame('price_123', $result['stripePriceId']);
    }

    /** AuditLog::record() must apply the redactor automatically — no call site should need to remember to. */
    public function test_audit_log_record_redacts_context_automatically(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        AuditLog::record('webhook.secret_rotated', null, [
            'before_secret' => 'whsec_old_value',
            'endpoint' => 'https://example.test/hook',
        ]);

        $row = AuditLog::latest()->first();

        $this->assertSame('[redacted]', $row->context['before_secret']);
        $this->assertSame('https://example.test/hook', $row->context['endpoint']);
    }
}
