<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure\Security;

use Bamise\Infrastructure\Security\Sanitizer\HtmlSanitizer;
use Bamise\Infrastructure\Security\Sanitizer\SanitizerConfig;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    public function test_strips_script_tags_by_default(): void
    {
        $sanitizer = new HtmlSanitizer(new SanitizerConfig());

        $result = $sanitizer->sanitize([
            'name' => '<script>alert(1)</script>Ada',
            'nested' => [
                'bio' => '<img src=x onerror=alert(1)>',
            ],
            'count' => 3,
        ]);

        self::assertStringNotContainsString('<script', (string) $result['name']);
        self::assertStringNotContainsString('onerror', (string) $result['nested']['bio']);
        self::assertSame(3, $result['count']);
    }

    public function test_allowlist_keeps_permitted_tags(): void
    {
        $sanitizer = new HtmlSanitizer(new SanitizerConfig(
            allowedTags: ['b', 'i'],
            encodeEntities: false,
        ));

        $result = $sanitizer->sanitize([
            'note' => '<b>ok</b><script>x</script>',
        ]);

        self::assertSame('<b>ok</b>x', $result['note']);
    }
}
