<?php

namespace Tests\Feature;

use Tests\TestCase;

class WaypostMcpNpmConfigTest extends TestCase
{
    public function test_default_mcp_npm_spec_matches_package_json(): void
    {
        if (env('WAYPOST_MCP_NPM_PACKAGE') !== null) {
            $this->markTestSkipped('WAYPOST_MCP_NPM_PACKAGE is set; this test only checks the package.json default.');
        }

        $path = base_path('mcp/waypost-server/package.json');
        $this->assertFileExists($path);
        $pkg = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($pkg);
        $this->assertArrayHasKey('name', $pkg);
        $this->assertArrayHasKey('version', $pkg);
        $expected = $pkg['name'].'@'.$pkg['version'];
        $this->assertSame($expected, config('waypost.mcp_npm_package'));
    }
}
