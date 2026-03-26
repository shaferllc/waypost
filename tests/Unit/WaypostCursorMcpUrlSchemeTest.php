<?php

namespace Tests\Unit;

use App\Support\WaypostCursorArtifacts;
use Tests\TestCase;

class WaypostCursorMcpUrlSchemeTest extends TestCase
{
    public function test_mcp_url_upgrades_http_dot_test_to_https_by_default(): void
    {
        config(['app.url' => 'http://waypost.test', 'waypost.public_url' => '']);
        config(['waypost.mcp_upgrade_http_to_https' => null]);

        $this->assertSame('https://waypost.test/mcp/waypost', WaypostCursorArtifacts::mcpHttpUrl());
        $this->assertSame('https://waypost.test/mcp/waypost/reachable', WaypostCursorArtifacts::mcpReachabilityUrl());
    }

    public function test_mcp_url_stays_http_when_upgrade_disabled(): void
    {
        config(['app.url' => 'http://waypost.test', 'waypost.public_url' => '']);
        config(['waypost.mcp_upgrade_http_to_https' => false]);

        $this->assertSame('http://waypost.test/mcp/waypost', WaypostCursorArtifacts::mcpHttpUrl());
    }

    public function test_mcp_url_does_not_upgrade_http_localhost_by_default(): void
    {
        config(['app.url' => 'http://localhost', 'waypost.public_url' => '']);
        config(['waypost.mcp_upgrade_http_to_https' => null]);

        $this->assertSame('http://localhost/mcp/waypost', WaypostCursorArtifacts::mcpHttpUrl());
    }
}
