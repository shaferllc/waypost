<?php

namespace Tests\Feature;

use Tests\TestCase;

class WaypostMcpNpmConfigTest extends TestCase
{
    public function test_default_mcp_npm_is_empty_for_local_mode(): void
    {
        if (env('WAYPOST_MCP_NPM_PACKAGE') !== null) {
            $this->markTestSkipped('WAYPOST_MCP_NPM_PACKAGE is set.');
        }

        $this->assertSame('', config('waypost.mcp_npm_package'));
    }
}
