<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[Signature('waypost:debug-mcp {--token= : Full Sanctum token (id|secret) to test authenticated initialize}')]
#[Description('Simulate Chromium MCP CORS preflight and Streamable HTTP POSTs via the HTTP kernel (no outbound HTTP)')]
class WaypostDebugMcpCommand extends Command
{
    public function handle(Kernel $kernel): int
    {
        $this->info('Waypost MCP in-process debug');
        $optToken = $this->option('token');
        $this->line('APP_URL: '.config('app.url'));
        $this->line('mcp_enabled: '.(config('waypost.mcp_enabled', true) ? 'true' : 'false'));
        $this->line('mcp_log_requests: '.(config('waypost.mcp_log_requests') ? 'true' : 'false'));
        $this->line('mcp PNA header: '.(config('waypost.mcp_allow_private_network_access_header') ? 'true' : 'false'));
        if (! config('waypost.mcp_enabled', true)) {
            $this->newLine();
            $this->warn('WAYPOST_MCP_ENABLED is false — POST /mcp/waypost (and OPTIONS on that route) return 503 until re-enabled.');
        }
        if (str_starts_with((string) config('app.url'), 'http://')) {
            $this->newLine();
            $this->warn('APP_URL uses http://. Herd/nginx often 301-redirects to HTTPS before PHP runs.');
            $this->warn('Cursor sends OPTIONS to that URL first — it gets HTML (no CORS) → "Error POSTing".');
            $this->warn('Fix: set APP_URL=https://waypost.test (and WAYPOST_PUBLIC_URL if used), php artisan config:clear, then use https in mcp.json.');
        }
        $this->newLine();

        $this->title('1) OPTIONS preflight (like Chromium + Private Network Access)');
        $opt = Request::create('/mcp/waypost', 'OPTIONS', [], [], [], [
            'HTTP_ORIGIN' => 'vscode-file://vscode-app',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK' => 'true',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type,mcp-protocol-version',
        ]);
        /** @var Response $optRes */
        $optRes = $kernel->handle($opt);
        $this->line('HTTP '.$optRes->getStatusCode());
        $this->line('Access-Control-Allow-Origin: '.$optRes->headers->get('Access-Control-Allow-Origin'));
        $this->line('Access-Control-Allow-Private-Network: '.$optRes->headers->get('Access-Control-Allow-Private-Network'));
        $this->line('Access-Control-Allow-Methods: '.$optRes->headers->get('Access-Control-Allow-Methods'));
        if ($optRes->headers->get('Access-Control-Allow-Private-Network') !== 'true') {
            $this->warn('Cursor/Electron may block POST until this header is "true" on preflight.');
        }

        $this->newLine();
        $this->title('1b) PNA-only OPTIONS (no Access-Control-Request-Method — Chrome may send this first)');
        $pnaOnly = Request::create('/mcp/waypost', 'OPTIONS', [], [], [], [
            'HTTP_ORIGIN' => 'vscode-file://vscode-app',
            'HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK' => 'true',
        ]);
        /** @var Response $pnaOnlyRes */
        $pnaOnlyRes = $kernel->handle($pnaOnly);
        $this->line('HTTP '.$pnaOnlyRes->getStatusCode().' (expect 204)');
        $this->line('Access-Control-Allow-Origin: '.$pnaOnlyRes->headers->get('Access-Control-Allow-Origin'));
        $this->line('Access-Control-Allow-Private-Network: '.$pnaOnlyRes->headers->get('Access-Control-Allow-Private-Network'));
        if ($pnaOnlyRes->getStatusCode() === 405) {
            $this->error('PNA-only preflight hit routing (405) — Chromium will not send the MCP POST.');
        }

        $this->newLine();
        $this->title('1c) GET SSE probe (Streamable HTTP optional backchannel; Cursor errors if this is 405)');
        $getHeaders = [
            'HTTP_ACCEPT' => 'text/event-stream',
        ];
        if (is_string($optToken) && trim($optToken) !== '') {
            $getHeaders['HTTP_AUTHORIZATION'] = 'Bearer '.trim($optToken);
        }
        $getReq = Request::create('/mcp/waypost', 'GET', [], [], [], $getHeaders);
        /** @var Response $getRes */
        $getRes = $kernel->handle($getReq);
        $this->line('HTTP '.$getRes->getStatusCode().' (expect 200 with --token, else 401)');
        $this->line('Content-Type: '.$getRes->headers->get('Content-Type'));
        if ($getRes->getStatusCode() === 405) {
            $this->error('GET returned 405 — Cursor/V2 reports "SSE error: Non-200 status code (405)".');
        }

        $this->newLine();
        $this->title('2) POST initialize without Authorization');
        $initBody = $this->initializeJsonPayload();
        $noAuth = Request::create('/mcp/waypost', 'POST', [], [], [], array_merge($this->mcpServerVars(strlen($initBody)), [
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => '2025-11-25',
        ]), $initBody);
        /** @var Response $noAuthRes */
        $noAuthRes = $kernel->handle($noAuth);
        $this->line('HTTP '.$noAuthRes->getStatusCode().' (expect 401)');
        $this->line('Body (first 200 chars): '.substr($noAuthRes->getContent(), 0, 200));

        if (! is_string($optToken) || trim($optToken) === '') {
            $this->newLine();
            $this->comment('Pass --token="your id|secret" to test authenticated initialize + session header.');
            $this->comment('If Cursor fails but (1) shows PNA true and (2) shows 401 here, Cursor may not be sending Authorization on POST.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->title('3) POST initialize with Bearer token');
        $initBody2 = $this->initializeJsonPayload();
        $withAuth = Request::create('/mcp/waypost', 'POST', [], [], [], array_merge($this->mcpServerVars(strlen($initBody2)), [
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_AUTHORIZATION' => 'Bearer '.trim($optToken),
            'HTTP_MCP_PROTOCOL_VERSION' => '2025-11-25',
        ]), $initBody2);
        /** @var Response $authRes */
        $authRes = $kernel->handle($withAuth);
        $this->line('HTTP '.$authRes->getStatusCode().' (expect 200)');
        $sessionId = $authRes->headers->get('MCP-Session-Id');
        $this->line('MCP-Session-Id: '.($sessionId ?? '(missing)'));
        $this->line('Body (first 300 chars): '.substr($authRes->getContent(), 0, 300));

        if ($authRes->getStatusCode() !== 200 || $sessionId === null || $sessionId === '') {
            $this->error('Authenticated initialize failed — token may be wrong or revoked.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('MCP stack OK in-process. If Cursor still errors, compare its URL to APP_URL and ensure mcp.json headers are sent on every POST.');

        return self::SUCCESS;
    }

    private function title(string $line): void
    {
        $this->line('<fg=cyan>'.$line.'</>');
    }

    private function initializeJsonPayload(): string
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'waypost:debug-mcp', 'version' => '1.0'],
            ],
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    private function mcpServerVars(int $contentLength): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONTENT_LENGTH' => (string) $contentLength,
        ];
    }
}
