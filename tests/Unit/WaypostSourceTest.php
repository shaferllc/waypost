<?php

namespace Tests\Unit;

use App\Support\WaypostSource;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WaypostSourceTest extends TestCase
{
    public static function normalizeProvider(): array
    {
        return [
            'empty to api' => [null, 'api'],
            'ai preserved' => ['ai', 'ai'],
            'mcp preserved' => ['MCP', 'mcp'],
            'windsurf preserved' => ['windsurf', 'windsurf'],
            'unknown to api' => ['hacker', 'api'],
        ];
    }

    public function test_extra_client_sources_from_config_are_allowed(): void
    {
        config(['waypost.extra_client_sources' => ['my_custom_bot']]);

        $this->assertContains('my_custom_bot', WaypostSource::allowedSources());
        $this->assertSame('my_custom_bot', WaypostSource::normalize('my_custom_bot'));
    }

    #[DataProvider('normalizeProvider')]
    public function test_normalize(?string $input, string $expected): void
    {
        $this->assertSame($expected, WaypostSource::normalize($input));
    }
}
