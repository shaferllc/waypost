<?php

namespace Tests\Feature;

use Tests\TestCase;

class WaypostMcpCorsPreflightTest extends TestCase
{
    public function test_mcp_options_preflight_returns_no_store_response_with_cors_headers(): void
    {
        $response = $this->call(
            'OPTIONS',
            '/mcp/waypost',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'https://cursor.example',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type,mcp-session-id,mcp-protocol-version',
            ],
        );

        $response->assertNoContent();
        $this->assertNotEmpty($response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotEmpty($response->headers->get('Access-Control-Allow-Methods'));
    }

    public function test_mcp_options_preflight_includes_private_network_access_for_chromium(): void
    {
        $response = $this->call(
            'OPTIONS',
            '/mcp/waypost',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'vscode-file://vscode-app',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK' => 'true',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
            ],
        );

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Private-Network', 'true');
    }
}
