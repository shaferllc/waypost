<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\WaypostProxyApiTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('waypost')]
#[Version('1.0.0')]
class WaypostHttpServer extends Server
{
    protected string $instructions = <<<'MARKDOWN'
Waypost MCP connects this assistant to your Waypost account over HTTPS (no local npm server).

Authenticate with a **project API Bearer token** (from Waypost → project → Sync). Project-scoped tokens may only call `/api` paths for that project.

**Tool: `waypost_http_request`** — call the Waypost JSON API under `/api`:
- `method`: GET, POST, PATCH, or DELETE
- `path`: path under `/api`, starting with `/` (e.g. `/projects/12/tasks`)
- `query`: optional object of query parameters
- `json_body`: optional JSON object for POST/PATCH

Prefer setting the token in your editor environment (e.g. `WAYPOST_API_TOKEN`) and referencing it in MCP headers rather than committing tokens to the repo.
MARKDOWN;

    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
    ];

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        WaypostProxyApiTool::class,
    ];
}
