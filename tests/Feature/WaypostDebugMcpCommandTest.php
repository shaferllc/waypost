<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WaypostDebugMcpCommandTest extends TestCase
{
    public function test_debug_mcp_command_reports_pna_header_on_preflight(): void
    {
        Artisan::call('waypost:debug-mcp');
        $out = Artisan::output();

        $this->assertStringContainsString('Access-Control-Allow-Private-Network: true', $out);
        $this->assertStringContainsString('HTTP 401', $out);
    }
}
