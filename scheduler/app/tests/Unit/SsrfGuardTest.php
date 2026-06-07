<?php

namespace Tests\Unit;

use App\Support\SsrfGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SsrfGuardTest extends TestCase
{
    private SsrfGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new SsrfGuard();
    }

    #[DataProvider('blockedUrls')]
    public function test_it_blocks_dangerous_urls(string $url): void
    {
        $this->assertFalse($this->guard->isAllowed($url), "Should block: {$url}");
    }

    public static function blockedUrls(): array
    {
        return [
            'cloud metadata'    => ['http://169.254.169.254/latest/meta-data/'],
            'loopback ip'       => ['http://127.0.0.1/admin'],
            'localhost'         => ['http://localhost:8000'],
            'rfc1918 10/8'      => ['http://10.0.0.5'],
            'rfc1918 192.168'   => ['http://192.168.1.1'],
            'internal tld'      => ['http://db.internal/feed'],
            'non-http scheme'   => ['ftp://example.com/file'],
            'file scheme'       => ['file:///etc/passwd'],
            'no scheme'         => ['example.com/feed'],
            'empty'             => [''],
        ];
    }

    #[DataProvider('allowedUrls')]
    public function test_it_allows_public_urls(string $url): void
    {
        $this->assertTrue($this->guard->isAllowed($url), "Should allow: {$url}");
    }

    public static function allowedUrls(): array
    {
        return [
            'public host'  => ['https://blog.example.com/feed'],
            'public ip'    => ['http://8.8.8.8/feed.xml'],
            'http host'    => ['http://example.org/rss'],
        ];
    }

    public function test_is_public_ip_classifies_ranges(): void
    {
        $this->assertTrue($this->guard->isPublicIp('8.8.8.8'));
        $this->assertFalse($this->guard->isPublicIp('10.0.0.1'));
        $this->assertFalse($this->guard->isPublicIp('127.0.0.1'));
        $this->assertFalse($this->guard->isPublicIp('169.254.169.254'));
        $this->assertFalse($this->guard->isPublicIp('::1'));
    }
}
