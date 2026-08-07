<?php

namespace App\Services;

/**
 * Recursively strips anything secret-shaped out of an audit-log context
 * array before it is written. Applied automatically by `AuditLog::record()`
 * so no call site can accidentally persist a credential just by passing the
 * wrong array — matching the platform-wide rule that OAuth tokens, password
 * hashes, 2FA secrets, recovery codes, webhook secrets, and API token
 * plaintext never appear in logs, exports, or audit context.
 */
class AuditContextRedactor
{
    /**
     * Substrings matched case-insensitively against each array key. A key
     * containing any of these is redacted regardless of nesting depth or
     * exact name — deliberately broad, since a false positive here only
     * costs a bit of audit detail, while a false negative leaks a secret.
     *
     * @var array<int, string>
     */
    protected const SENSITIVE_KEY_SUBSTRINGS = [
        'password',
        'secret',
        'token',
        'api_key',
        'apikey',
        'recovery_code',
        'private_key',
        'access_token',
        'refresh_token',
    ];

    protected const REDACTED = '[redacted]';

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    public function redact(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    protected function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEY_SUBSTRINGS as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
